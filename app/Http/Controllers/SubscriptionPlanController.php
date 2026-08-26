<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\Subscriptions\CreateSubscriptionPlan;
use App\Actions\Marketing\Subscriptions\DeactivateSubscriptionPlan;
use App\Actions\Marketing\Subscriptions\ReactivateSubscriptionPlan;
use App\Actions\Marketing\Subscriptions\UpdateSubscriptionPlan;
use App\Http\Requests\SubscriptionPlanRequest;
use App\Models\CustomerSubscription;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SubscriptionPlanController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', SubscriptionPlan::class);

        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status', 'active');

        $plans = SubscriptionPlan::query()
            ->with(['services:id,name,price_cents'])
            ->withCount(['customerSubscriptions'])
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $activeSubscribersCount = CustomerSubscription::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('status', 'active')
            ->count();

        $estimatedMonthlyRevenueCents = (int) CustomerSubscription::query()
            ->where('customer_subscriptions.tenant_id', $context->tenant->getKey())
            ->where('customer_subscriptions.unit_id', $context->unit?->getKey())
            ->where('customer_subscriptions.status', 'active')
            ->whereNull('customer_subscriptions.deleted_at')
            ->selectRaw('SUM(CASE
                WHEN customer_subscriptions.billing_cycle = \'monthly\' THEN customer_subscriptions.price_cents
                WHEN customer_subscriptions.billing_cycle = \'quarterly\' THEN customer_subscriptions.price_cents / 3
                WHEN customer_subscriptions.billing_cycle = \'yearly\' THEN customer_subscriptions.price_cents / 12
                ELSE 0
            END) as total')
            ->value('total');

        $serviceOptions = Service::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'price_cents'])
            ->map(fn (Service $srv) => [
                'id' => $srv->id,
                'name' => $srv->name,
                'price_cents' => $srv->price_cents,
            ])
            ->values()
            ->all();

        return Inertia::render('subscriptions/index', [
            'plans' => $plans,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'serviceOptions' => $serviceOptions,
            'metrics' => [
                'total_active_subscribers' => $activeSubscribersCount,
                'estimated_monthly_revenue_cents' => $estimatedMonthlyRevenueCents,
            ],
        ]);
    }

    public function show(SubscriptionPlan $subscriptionPlan, TenantContext $context): Response
    {
        Gate::authorize('view', $subscriptionPlan);

        $subscriptionPlan->load(['services:id,name,price_cents,duration_minutes']);

        $serviceOptions = Service::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'price_cents'])
            ->map(fn (Service $srv) => [
                'id' => $srv->id,
                'name' => $srv->name,
                'price_cents' => $srv->price_cents,
            ])
            ->values()
            ->all();

        $subscribers = $subscriptionPlan->customerSubscriptions()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->with(['customer:id,name,phone,email'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('subscriptions/show', [
            'plan' => $subscriptionPlan,
            'serviceOptions' => $serviceOptions,
            'subscribers' => $subscribers,
        ]);
    }

    public function store(SubscriptionPlanRequest $request, TenantContext $context, CreateSubscriptionPlan $createPlan): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createPlan, $request, $context, $data): array {
            $plan = $createPlan->handle($request->user(), $context, $data);

            return ['resource_id' => $plan->getKey(), 'resource_type' => 'subscription_plan'];
        });

        $plan = SubscriptionPlan::query()->findOrFail($reference['resource_id']);

        return to_route('subscriptions.show', $plan)->with('success', 'Plano de assinatura criado com sucesso.');
    }

    public function update(SubscriptionPlanRequest $request, TenantContext $context, SubscriptionPlan $subscriptionPlan, UpdateSubscriptionPlan $updatePlan): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updatePlan, $request, $context, $subscriptionPlan, $data): array {
            $updated = $updatePlan->handle(
                $request->user(),
                $context,
                $subscriptionPlan,
                $data,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'subscription_plan'];
        });

        $plan = SubscriptionPlan::query()->findOrFail($reference['resource_id']);

        return to_route('subscriptions.show', $plan)->with('success', 'Plano de assinatura atualizado com sucesso.');
    }

    public function destroy(SubscriptionPlanRequest $request, TenantContext $context, SubscriptionPlan $subscriptionPlan, DeactivateSubscriptionPlan $deactivatePlan): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($deactivatePlan, $request, $context, $subscriptionPlan, $data): array {
            $deactivated = $deactivatePlan->handle(
                $request->user(),
                $context,
                $subscriptionPlan,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            return ['resource_id' => $deactivated->getKey(), 'resource_type' => 'subscription_plan'];
        });

        $plan = SubscriptionPlan::query()->findOrFail($reference['resource_id']);

        return to_route('subscriptions.show', $plan)->with('success', 'Plano de assinatura desativado.');
    }

    public function reactivate(SubscriptionPlanRequest $request, TenantContext $context, SubscriptionPlan $subscriptionPlan, ReactivateSubscriptionPlan $reactivatePlan): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reactivatePlan, $request, $context, $subscriptionPlan, $data): array {
            $reactivated = $reactivatePlan->handle(
                $request->user(),
                $context,
                $subscriptionPlan,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            return ['resource_id' => $reactivated->getKey(), 'resource_type' => 'subscription_plan'];
        });

        $plan = SubscriptionPlan::query()->findOrFail($reference['resource_id']);

        return to_route('subscriptions.show', $plan)->with('success', 'Plano de assinatura reativado.');
    }
}
