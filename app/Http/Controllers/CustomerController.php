<?php

namespace App\Http\Controllers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\DeactivateCustomer;
use App\Actions\Customers\ReactivateCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Actions\Marketing\Retention\MarkCustomerAtRisk;
use App\Actions\Marketing\Retention\ReactivateCustomerRetention;
use App\Actions\Marketing\Retention\UpdateCustomerCommunicationPreference;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\RetentionCustomerRequest;
use App\Http\Requests\UpdateCustomerCommunicationPreferenceRequest;
use App\Models\Customer;
use App\Models\CustomerCommunicationPreference;
use App\Models\FinancialObligation;
use App\Models\PackageTemplate;
use App\Models\SubscriptionPlan;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', Customer::class);
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status', 'active');
        $customers = Customer::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->when($status === 'active', fn ($query) => $query->where('status', 'active'))
            ->when($status === 'inactive', fn ($query) => $query->where('status', 'inactive'))
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(Customer $customer): Response
    {
        Gate::authorize('view', $customer);

        $canLoadPackageFinancialObligations = Gate::allows('viewAny', FinancialObligation::class)
            && FinancialObligation::hasCustomerPackageLink();

        $customer->load([
            'appointments' => fn ($query) => $query
                ->with('professional:id,name')
                ->orderBy('starts_at', 'desc')
                ->limit(20),
            'sales' => fn ($query) => $query
                ->with([
                    'items',
                    'saleCategory:id,name',
                ])
                ->orderBy('created_at', 'desc'),
            'customerPackages' => fn ($query) => $query
                ->where('status', '!=', 'archived')
                ->with([
                    'packageTemplate.services:id,name,price_cents',
                    'serviceBalances.service:id,name',
                    'usages.user:id,name',
                ])
                ->when($canLoadPackageFinancialObligations, fn ($query) => $query->with('financialObligation:id,customer_package_id,amount_cents,status,paid_date,payment_method'))
                ->orderBy('created_at', 'desc'),
            'subscriptions' => fn ($query) => $query
                ->with('plan:id,name,price_cents,billing_cycle')
                ->orderByDesc('created_at'),
        ]);

        $hasPackageTemplates = PackageTemplate::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('unit_id', $customer->unit_id)
            ->where('is_active', true)
            ->exists();

        $hasPlanOptions = SubscriptionPlan::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('unit_id', $customer->unit_id)
            ->where('is_active', true)
            ->exists();

        $subscriptions = $customer->subscriptions;
        $activeSubscription = $subscriptions->first(fn ($subscription): bool => in_array($subscription->status, ['active', 'paused'], true));

        $totalSpentCents = (int) $customer->sales
            ->where('status', 'finalized')
            ->sum('final_amount_cents');

        $totalVisits = $customer->appointments()
            ->whereIn('status', ['confirmed', 'checked_in', 'in_service', 'completed'])
            ->count();

        return Inertia::render('customers/show', [
            'customer' => $customer,
            'hasPackageTemplates' => $hasPackageTemplates,
            'active_subscription' => $activeSubscription,
            'subscription_history' => $subscriptions,
            'hasPlanOptions' => $hasPlanOptions,
            'metrics' => [
                'total_spent_cents' => $totalSpentCents,
                'total_visits' => $totalVisits,
            ],
            'can_view_finance' => Gate::allows('viewAny', FinancialObligation::class),
        ]);
    }

    public function store(CustomerRequest $request, TenantContext $context, CreateCustomer $createCustomer): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createCustomer, $request, $context, $data): array {
            $customer = $createCustomer->handle($request->user(), $context, $data);

            return ['resource_id' => $customer->getKey(), 'resource_type' => 'customer'];
        });
        $customer = Customer::query()->findOrFail($reference['resource_id']);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $customer->id,
                'name' => $customer->name,
                'customer' => $customer,
            ], 201);
        }

        return to_route('customers.show', $customer)->with('success', 'Cliente cadastrado.');
    }

    public function update(CustomerRequest $request, TenantContext $context, Customer $customer, UpdateCustomer $updateCustomer): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updateCustomer, $request, $context, $customer, $data): array {
            $updated = $updateCustomer->handle($request->user(), $context, $customer, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'customer'];
        });
        $customer = Customer::query()->findOrFail($reference['resource_id']);

        return to_route('customers.show', $customer)->with('success', 'Cliente atualizado.');
    }

    public function destroy(CustomerRequest $request, TenantContext $context, Customer $customer, DeactivateCustomer $deactivateCustomer): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($deactivateCustomer, $request, $context, $customer, $data): array {
            $deactivated = $deactivateCustomer->handle($request->user(), $context, $customer, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $deactivated->getKey(), 'resource_type' => 'customer'];
        });

        return to_route('customers.index')->with('success', 'Cliente inativado.');
    }

    public function reactivate(CustomerRequest $request, TenantContext $context, Customer $customer, ReactivateCustomer $reactivateCustomer): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reactivateCustomer, $request, $context, $customer, $data): array {
            $reactivated = $reactivateCustomer->handle($request->user(), $context, $customer, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $reactivated->getKey(), 'resource_type' => 'customer'];
        });

        return to_route('customers.show', $customer)->with('success', 'Cliente reativado.');
    }

    public function inactive(RetentionCustomerRequest $request, TenantContext $context): JsonResponse
    {
        $days = (int) $request->validated('days', 90);

        return response()->json([
            'days' => $days,
            'customers' => $this->inactiveCustomers($context, $days),
        ]);
    }

    public function retentionIndex(RetentionCustomerRequest $request, TenantContext $context): Response
    {
        $days = (int) $request->validated('days', 90);

        return Inertia::render('retention/inactive', [
            'days' => $days,
            'customers' => $this->inactiveCustomers($context, $days),
        ]);
    }

    public function updateCommunicationPreference(UpdateCustomerCommunicationPreferenceRequest $request, TenantContext $context, Customer $customer, UpdateCustomerCommunicationPreference $update): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($update, $request, $context, $customer, $data): array {
            $preference = $update->handle($request->user(), $context, $customer, $data);

            return ['resource_id' => $preference->getKey(), 'resource_type' => 'customer_communication_preference'];
        });
        $preference = CustomerCommunicationPreference::query()->findOrFail($reference['resource_id']);
        if ($request->wantsJson()) {
            return response()->json(['preference' => $preference]);
        }

        return back()->with('success', 'Preferência de comunicação atualizada.');
    }

    public function markAtRisk(RetentionCustomerRequest $request, TenantContext $context, Customer $customer, MarkCustomerAtRisk $mark): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($mark, $request, $context, $customer, $data): array {
            $updated = $mark->handle($request->user(), $context, $customer, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'customer'];
        });
        $updated = Customer::query()->findOrFail($reference['resource_id']);

        return $request->wantsJson() ? response()->json(['customer' => $updated]) : back()->with('success', 'Cliente marcado para retenção.');
    }

    public function reactivateRetention(RetentionCustomerRequest $request, TenantContext $context, Customer $customer, ReactivateCustomerRetention $reactivate): RedirectResponse|JsonResponse
    {
        $reference = $this->mutation->execute($request, $context, $request->user(), $request->validated(), function () use ($reactivate, $request, $context, $customer): array {
            $updated = $reactivate->handle($request->user(), $context, $customer);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'customer'];
        });
        $updated = Customer::query()->findOrFail($reference['resource_id']);

        return $request->wantsJson() ? response()->json(['customer' => $updated]) : back()->with('success', 'Cliente reativado na retenção.');
    }

    /** @return Collection<int, Customer> */
    private function inactiveCustomers(TenantContext $context, int $days): Collection
    {
        return Customer::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('status', 'active')
            ->inactiveFor($days)
            ->with([
                'communicationPreferences' => fn ($query) => $query->select([
                    'id',
                    'customer_id',
                    'channel',
                    'opted_in',
                    'consented_at',
                    'revoked_at',
                ]),
            ])
            ->orderBy('last_activity_at')
            ->get([
                'id',
                'name',
                'email',
                'phone',
                'last_activity_at',
                'retention_status',
            ]);
    }
}
