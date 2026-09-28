<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreatePlatformTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateTenantRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Http\Requests\Admin\UpdateTenantStatusRequest;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AdminController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('admin/dashboard', [
            'metrics' => [
                'tenants' => Tenant::query()->count(),
                'active_tenants' => Tenant::query()->where('status', 'active')->count(),
                'subscriptions' => TenantSubscription::query()->count(),
                'active_subscriptions' => TenantSubscription::query()->whereIn('status', ['trial', 'active', 'grace'])->count(),
            ],
            'recentTenants' => Tenant::query()->withCount('memberships')->with('memberships.user:id,name,email')->latest()->limit(10)->get(),
            'plans' => PlatformPlan::query()->where('is_active', true)->orderBy('price_cents')->get(['id', 'key', 'name', 'price_cents', 'billing_cycle', 'trial_days']),
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
        $tenant->load(['memberships.user:id,name,email', 'subscriptions.plan']);
        $subscription = $tenant->subscriptions->sortByDesc('created_at')->first();
        $owner = $tenant->memberships->firstWhere('status.value', 'active')?->user;

        return Inertia::render('platform/clients/show', [
            'client' => [
                'id' => $tenant->id, 'name' => $tenant->name, 'company' => $tenant->legal_name,
                'email' => $owner?->email ?? '', 'status' => $subscription?->status ?? $tenant->status->value,
                'plan' => $subscription?->plan?->name ?? 'Sem plano', 'monthlyValueCents' => $subscription?->plan?->price_cents ?? 0,
                'members' => $tenant->memberships->count(), 'createdAt' => $tenant->created_at?->toISOString(),
                'renewsAt' => $subscription?->ends_at?->toISOString(),
                'subscription' => ['startedAt' => $subscription?->starts_at?->toISOString(), 'renewsAt' => $subscription?->ends_at?->toISOString(), 'seats' => $tenant->memberships->count(), 'paymentMethod' => $subscription?->provider],
            ],
        ]);
    }

    public function storeTenant(CreateTenantRequest $request, CreatePlatformTenant $create): RedirectResponse
    {
        $tenant = $create->handle($request->validated());
        return to_route('admin.tenants')->with('success', "Cliente {$tenant->name} criado com sucesso.");
    }

    public function updateSubscription(UpdateSubscriptionRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($tenant, $data): void {
            $subscription = $tenant->subscriptions()->latest()->lockForUpdate()->first();
            $attributes = ['platform_plan_id' => $data['plan_id'], 'status' => $data['status'], 'starts_at' => $subscription?->starts_at ?? now()];
            if ($subscription === null) { $tenant->subscriptions()->create($attributes); } else { $subscription->update($attributes); }
        }, 5);
        return back()->with('success', 'Assinatura atualizada.');
    }

    public function updateTenantStatus(UpdateTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => $request->validated('status'), 'lock_version' => $tenant->lock_version + 1]);
        return back()->with('success', 'Status do cliente atualizado.');
    }

    public function sendAccess(Tenant $tenant): RedirectResponse
    {
        return back()->with('success', 'O acesso será enviado ao responsável.');
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'suspended', 'lock_version' => $tenant->lock_version + 1]);
        return back()->with('success', 'Cliente suspenso.');
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'active', 'lock_version' => $tenant->lock_version + 1]);
        return back()->with('success', 'Cliente reativado.');
    }
}
