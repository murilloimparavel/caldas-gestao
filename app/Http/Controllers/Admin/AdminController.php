<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreatePlatformTenant;
use App\Actions\Admin\UpdatePlatformSubscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTenantRequest;
use App\Http\Requests\Admin\PlatformSubscriptionRequest;
use App\Http\Requests\Admin\UpdateTenantStatusRequest;
use App\Models\AuditEvent;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Support\AuditEventWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

final class AdminController extends Controller
{
    public function login(Request $request): Response
    {
        $request->session()->put('admin_login_intent', true);

        return Inertia::render('auth/admin-login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function dashboard(): Response
    {
        $tenants = Tenant::query()->with([
            'subscriptions' => fn ($query) => $query->with('plan')->latest(),
            'memberships' => fn ($query) => $query->where('status', 'active'),
        ])->get();
        $attention = [];
        foreach ($tenants as $tenant) {
            $subscription = $tenant->subscriptions->first();
            if ($tenant->status->value === 'suspended') {
                $attention[] = ['type' => 'suspended_tenant', 'tenantId' => $tenant->getKey(), 'tenant' => $tenant->name, 'message' => 'Conta suspensa.'];
            }
            $subscriptionEndsAt = $subscription?->ends_at;
            if ($subscription?->status === 'trial' && $subscriptionEndsAt !== null && CarbonImmutable::parse($subscriptionEndsAt)->lte(now()->addDays(7))) {
                $attention[] = ['type' => 'trial_ending', 'tenantId' => $tenant->getKey(), 'tenant' => $tenant->name, 'dueAt' => CarbonImmutable::parse($subscriptionEndsAt)->toISOString(), 'message' => 'Período de teste próximo do fim.'];
            }
            if (in_array($subscription?->status, ['grace', 'expired'], true)) {
                $attention[] = ['type' => 'subscription_due', 'tenantId' => $tenant->getKey(), 'tenant' => $tenant->name, 'message' => 'Assinatura vencida ou em carência.'];
            }
            if ($tenant->memberships->isEmpty()) {
                $attention[] = ['type' => 'onboarding_pending', 'tenantId' => $tenant->getKey(), 'tenant' => $tenant->name, 'message' => 'Nenhum usuário ativo concluiu o onboarding.'];
            }
            $userLimit = data_get($subscription?->plan?->limits, 'users');
            $activeUsers = $tenant->memberships->count();
            if (is_numeric($userLimit) && (int) $userLimit > 0 && $activeUsers >= ((int) $userLimit * 0.8)) {
                $attention[] = ['type' => 'limit_near', 'tenantId' => $tenant->getKey(), 'tenant' => $tenant->name, 'message' => "Usuários próximos do limite ({$activeUsers}/{$userLimit})."];
            }
        }

        $alerts = array_map(static fn (array $item): array => [
            'id' => $item['type'].'-'.$item['tenantId'],
            'title' => $item['tenant'],
            'detail' => $item['message'],
            'severity' => in_array($item['type'], ['suspended_tenant', 'subscription_due'], true) ? 'high' : ($item['type'] === 'onboarding_pending' ? 'low' : 'medium'),
            'href' => route('admin.tenants.show', $item['tenantId']),
        ], $attention);

        return Inertia::render('admin/dashboard', [
            'metrics' => [
                'tenants' => Tenant::query()->count(),
                'active_tenants' => Tenant::query()->where('status', 'active')->count(),
                'subscriptions' => TenantSubscription::query()->count(),
                'active_subscriptions' => TenantSubscription::query()->whereIn('status', ['trial', 'active', 'grace'])->count(),
            ],
            'recentTenants' => Tenant::query()
                ->withCount(['memberships' => fn ($query) => $query->where('status', 'active')])
                ->with(['memberships' => fn ($query) => $query->with('user:id,name,email')->where('status', 'active')])
                ->latest()
                ->limit(10)
                ->get(),
            'plans' => PlatformPlan::query()->where('is_active', true)->orderBy('price_cents')->get(['id', 'key', 'name', 'price_cents', 'billing_cycle', 'trial_days']),
            'alerts' => $alerts,
        ]);
    }

    public function tenants(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $tenants = Tenant::query()
            ->withCount(['memberships' => fn ($query) => $query->where('status', 'active')])
            ->with(['memberships' => fn ($query) => $query->with('user:id,name,email')->where('status', 'active'), 'subscriptions.plan'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->latest()->paginate(25)->withQueryString();

        return Inertia::render('admin/tenants', ['tenants' => $tenants, 'plans' => PlatformPlan::query()->where('is_active', true)->orderBy('price_cents')->get(['id', 'name', 'price_cents', 'billing_cycle']), 'filters' => ['search' => $search]]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/clients/create', [
            'plans' => PlatformPlan::query()->where('is_active', true)->orderBy('price_cents')->get()
                ->map(fn (PlatformPlan $plan): array => ['id' => $plan->id, 'name' => $plan->name, 'priceCents' => $plan->price_cents]),
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load(['memberships.user:id,name,email', 'memberships.membershipRoles.role', 'subscriptions.plan']);
        $tenant->loadCount(['memberships' => fn ($query) => $query->where('status', 'active')]);
        $subscription = $tenant->subscriptions->sortByDesc('created_at')->first();
        $owner = $tenant->memberships->firstWhere('status.value', 'active')?->user;
        $subscriptionPlan = $subscription?->plan;
        $billingCycle = $subscription === null
            ? $subscriptionPlan?->billing_cycle
            : $subscription->billing_cycle;
        $formatDate = static fn (mixed $value): ?string => $value === null
            ? null
            : CarbonImmutable::parse((string) $value)->toISOString();

        return Inertia::render('platform/clients/show', [
            'client' => [
                'id' => $tenant->id, 'name' => $tenant->name, 'company' => $tenant->legal_name,
                'email' => $owner->email ?? '', 'status' => $subscription->status ?? $tenant->status->value,
                'plan' => $subscriptionPlan->name ?? 'Sem plano', 'monthlyValueCents' => $subscriptionPlan->price_cents ?? 0,
                'members' => $tenant->memberships_count, 'createdAt' => $tenant->created_at?->toISOString(),
                'renewsAt' => $formatDate($subscription?->ends_at),
                'subscription' => ['planId' => $subscription?->platform_plan_id, 'status' => $subscription?->status, 'billingCycle' => $billingCycle, 'startedAt' => $formatDate($subscription?->starts_at), 'endsAt' => $formatDate($subscription?->ends_at), 'renewsAt' => $formatDate($subscription?->next_billing_at) ?? $formatDate($subscription?->ends_at), 'seats' => $tenant->memberships_count, 'paymentMethod' => $subscription?->provider],
            ],
            'users' => $tenant->memberships->map(fn ($membership): array => ['id' => $membership->getKey(), 'name' => $membership->user?->name, 'email' => $membership->user?->email, 'status' => $membership->status->value, 'role' => $membership->membershipRoles->first()?->role?->key]),
            'plans' => PlatformPlan::query()->where('is_active', true)->orderBy('price_cents')->get(['id', 'name', 'price_cents', 'billing_cycle', 'trial_days']),
            'subscriptionRecord' => $subscription,
            'audit' => AuditEvent::query()->with('actor:id,name')->where('tenant_id', $tenant->getKey())->latest('occurred_at')->limit(25)->get()->map(fn (AuditEvent $event): array => ['id' => $event->getKey(), 'action' => $event->action, 'createdAt' => $formatDate($event->occurred_at), 'actor' => $event->actor?->name]),
        ]);
    }

    public function storeTenant(CreateTenantRequest $request, CreatePlatformTenant $create): RedirectResponse
    {
        $tenant = $create->handle($request->user(), $request->validated());

        return to_route('admin.tenants')->with('success', "Cliente {$tenant->name} criado com sucesso.");
    }

    public function updateSubscription(PlatformSubscriptionRequest $request, Tenant $tenant, UpdatePlatformSubscription $update): RedirectResponse
    {
        $update->handle($request->user(), $tenant, $request->validated());

        return back()->with('success', 'Assinatura atualizada.');
    }

    public function updateTenantStatus(UpdateTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        DB::transaction(function () use ($request, $tenant): void {
            $tenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $before = $tenant->status->value;
            $tenant->update(['status' => $request->validated('status'), 'lock_version' => $tenant->lock_version + 1]);
            app(AuditEventWriter::class)->record(['actor_user_id' => $request->user()?->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.tenant.status_updated', 'resource_type' => 'tenant', 'resource_id' => $tenant->getKey(), 'metadata' => ['from_status' => $before, 'to_status' => $tenant->status->value]]);
        });

        return back()->with('success', 'Status do cliente atualizado.');
    }

    public function audit(Request $request): Response
    {
        return Inertia::render('admin/audit', ['events' => AuditEvent::query()->with('actor:id,name,email')->latest('occurred_at')->paginate(50)->withQueryString()]);
    }

    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        DB::transaction(function () use ($request, $tenant): void {
            $tenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $tenant->update(['status' => 'suspended', 'lock_version' => $tenant->lock_version + 1]);
            app(AuditEventWriter::class)->record(['actor_user_id' => $request->user()?->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.tenant.suspended', 'resource_type' => 'tenant', 'resource_id' => $tenant->getKey()]);
        });

        return back()->with('success', 'Cliente suspenso.');
    }

    public function activate(Request $request, Tenant $tenant): RedirectResponse
    {
        DB::transaction(function () use ($request, $tenant): void {
            $tenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $tenant->update(['status' => 'active', 'lock_version' => $tenant->lock_version + 1]);
            app(AuditEventWriter::class)->record(['actor_user_id' => $request->user()?->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.tenant.activated', 'resource_type' => 'tenant', 'resource_id' => $tenant->getKey()]);
        });

        return back()->with('success', 'Cliente reativado.');
    }
}
