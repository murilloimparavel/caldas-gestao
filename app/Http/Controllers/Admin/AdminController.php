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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdminController extends Controller
{
    public function dashboard(): Response
    {
        $tenants = Tenant::query()->with(['subscriptions' => fn ($query) => $query->with('plan')->latest(), 'memberships'])->get();
        $attention = [];
        foreach ($tenants as $tenant) {
            $subscription = $tenant->subscriptions->first();
            if ($tenant->status->value === 'suspended') {
                $attention[] = ['type' => 'suspended_tenant', 'tenantId' => $tenant->getKey(), 'tenant' => $tenant->name, 'message' => 'Conta suspensa.'];
            }
            if ($subscription?->status === 'trial' && $subscription->ends_at !== null && $subscription->ends_at->lte(now()->addDays(7))) {
                $attention[] = ['type' => 'trial_ending', 'tenantId' => $tenant->getKey(), 'tenant' => $tenant->name, 'dueAt' => $subscription->ends_at->toISOString(), 'message' => 'Período de teste próximo do fim.'];
            }
            if (in_array($subscription?->status, ['grace', 'expired'], true)) {
                $attention[] = ['type' => 'subscription_due', 'tenantId' => $tenant->getKey(), 'tenant' => $tenant->name, 'message' => 'Assinatura vencida ou em carência.'];
            }
            if ($tenant->memberships->where('status.value', 'active')->isEmpty()) {
                $attention[] = ['type' => 'onboarding_pending', 'tenantId' => $tenant->getKey(), 'tenant' => $tenant->name, 'message' => 'Nenhum usuário ativo concluiu o onboarding.'];
            }
            $userLimit = data_get($subscription?->plan?->limits, 'users');
            $activeUsers = $tenant->memberships->where('status.value', 'active')->count();
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
            'recentTenants' => Tenant::query()->withCount('memberships')->with('memberships.user:id,name,email')->latest()->limit(10)->get(),
            'plans' => PlatformPlan::query()->where('is_active', true)->orderBy('price_cents')->get(['id', 'key', 'name', 'price_cents', 'billing_cycle', 'trial_days']),
            'alerts' => $alerts,
        ]);
    }

    public function tenants(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $tenants = Tenant::query()->withCount('memberships')->with(['memberships' => fn ($query) => $query->with('user:id,name,email')->where('status', 'active'), 'subscriptions.plan'])
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
        $subscription = $tenant->subscriptions->sortByDesc('created_at')->first();
        $owner = $tenant->memberships->firstWhere('status.value', 'active')?->user;

        return Inertia::render('platform/clients/show', [
            'client' => [
                'id' => $tenant->id, 'name' => $tenant->name, 'company' => $tenant->legal_name,
                'email' => $owner?->email ?? '', 'status' => $subscription?->status ?? $tenant->status->value,
                'plan' => $subscription?->plan?->name ?? 'Sem plano', 'monthlyValueCents' => $subscription?->plan?->price_cents ?? 0,
                'members' => $tenant->memberships->count(), 'createdAt' => $tenant->created_at?->toISOString(),
                'renewsAt' => $subscription?->ends_at?->toISOString(),
                'subscription' => ['planId' => $subscription?->platform_plan_id, 'status' => $subscription?->status, 'billingCycle' => $subscription?->billing_cycle ?? $subscription?->plan?->billing_cycle, 'startedAt' => $subscription?->starts_at?->toISOString(), 'endsAt' => $subscription?->ends_at?->toISOString(), 'renewsAt' => $subscription?->next_billing_at?->toISOString() ?? $subscription?->ends_at?->toISOString(), 'seats' => $tenant->memberships->count(), 'paymentMethod' => $subscription?->provider],
            ],
            'users' => $tenant->memberships->map(fn ($membership): array => ['id' => $membership->getKey(), 'name' => $membership->user?->name, 'email' => $membership->user?->email, 'status' => $membership->status->value, 'role' => $membership->membershipRoles->first()?->role?->key]),
            'plans' => PlatformPlan::query()->where('is_active', true)->orderBy('price_cents')->get(['id', 'name', 'price_cents', 'billing_cycle', 'trial_days']),
            'subscriptionRecord' => $subscription,
            'audit' => AuditEvent::query()->with('actor:id,name')->where('tenant_id', $tenant->getKey())->latest('occurred_at')->limit(25)->get()->map(fn (AuditEvent $event): array => ['id' => $event->getKey(), 'action' => $event->action, 'createdAt' => $event->occurred_at?->toISOString(), 'actor' => $event->actor?->name]),
        ]);
    }

    public function storeTenant(CreateTenantRequest $request, CreatePlatformTenant $create): RedirectResponse
    {
        $tenant = $create->handle($request->validated());
        app(AuditEventWriter::class)->record(['actor_user_id' => $request->user()?->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.tenant.created', 'resource_type' => 'tenant', 'resource_id' => $tenant->getKey()]);

        return to_route('admin.tenants')->with('success', "Cliente {$tenant->name} criado com sucesso.");
    }

    public function updateSubscription(PlatformSubscriptionRequest $request, Tenant $tenant, UpdatePlatformSubscription $update): RedirectResponse
    {
        $update->handle($request->user(), $tenant, $request->validated());

        return back()->with('success', 'Assinatura atualizada.');
    }

    public function updateTenantStatus(UpdateTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        $before = $tenant->status->value;
        $tenant->update(['status' => $request->validated('status'), 'lock_version' => $tenant->lock_version + 1]);
        app(AuditEventWriter::class)->record(['actor_user_id' => $request->user()?->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.tenant.status_updated', 'resource_type' => 'tenant', 'resource_id' => $tenant->getKey(), 'metadata' => ['from_status' => $before, 'to_status' => $tenant->status->value]]);

        return back()->with('success', 'Status do cliente atualizado.');
    }

    public function audit(Request $request): Response
    {
        return Inertia::render('admin/audit', ['events' => AuditEvent::query()->with('actor:id,name,email')->latest('occurred_at')->paginate(50)->withQueryString()]);
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'suspended', 'lock_version' => $tenant->lock_version + 1]);
        app(AuditEventWriter::class)->record(['actor_user_id' => request()->user()?->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.tenant.suspended', 'resource_type' => 'tenant', 'resource_id' => $tenant->getKey()]);

        return back()->with('success', 'Cliente suspenso.');
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'active', 'lock_version' => $tenant->lock_version + 1]);
        app(AuditEventWriter::class)->record(['actor_user_id' => request()->user()?->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.tenant.activated', 'resource_type' => 'tenant', 'resource_id' => $tenant->getKey()]);

        return back()->with('success', 'Cliente reativado.');
    }
}
