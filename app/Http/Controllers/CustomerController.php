<?php

namespace App\Http\Controllers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\DeactivateCustomer;
use App\Actions\Customers\ReactivateCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
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

        $customer->load([
            'appointments' => fn ($query) => $query
                ->with('professional:id,name')
                ->orderBy('starts_at', 'desc')
                ->limit(20),
        ]);

        return Inertia::render('customers/show', ['customer' => $customer]);
    }

    public function store(CustomerRequest $request, TenantContext $context, CreateCustomer $createCustomer): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createCustomer, $request, $context, $data): array {
            $customer = $createCustomer->handle($request->user(), $context, $data);

            return ['resource_id' => $customer->getKey(), 'resource_type' => 'customer'];
        });
        $customer = Customer::query()->findOrFail($reference['resource_id']);

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
}
