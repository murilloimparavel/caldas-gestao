<?php

namespace App\Http\Controllers;

use App\Actions\Suppliers\CreateSupplier;
use App\Actions\Suppliers\DeactivateSupplier;
use App\Actions\Suppliers\ReactivateSupplier;
use App\Actions\Suppliers\UpdateSupplier;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', Supplier::class);
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status', 'active');

        $suppliers = Supplier::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where(function ($query) use ($context): void {
                $query->whereNull('unit_id')
                    ->orWhere('unit_id', $context->unit?->getKey());
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(Supplier $supplier, TenantContext $context): Response
    {
        Gate::authorize('view', $supplier);

        return Inertia::render('suppliers/show', [
            'supplier' => $supplier,
        ]);
    }

    public function store(SupplierRequest $request, TenantContext $context, CreateSupplier $createSupplier): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createSupplier, $request, $context, $data): array {
            $supplier = $createSupplier->handle($request->user(), $context, $data);

            return ['resource_id' => $supplier->getKey(), 'resource_type' => 'supplier'];
        });
        $supplier = Supplier::query()->findOrFail($reference['resource_id']);

        return to_route('suppliers.show', $supplier)->with('success', 'Fornecedor cadastrado com sucesso.');
    }

    public function update(SupplierRequest $request, TenantContext $context, Supplier $supplier, UpdateSupplier $updateSupplier): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updateSupplier, $request, $context, $supplier, $data): array {
            $updated = $updateSupplier->handle($request->user(), $context, $supplier, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'supplier'];
        });
        $supplier = Supplier::query()->findOrFail($reference['resource_id']);

        return to_route('suppliers.show', $supplier)->with('success', 'Fornecedor atualizado com sucesso.');
    }

    public function destroy(SupplierRequest $request, TenantContext $context, Supplier $supplier, DeactivateSupplier $deactivateSupplier): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($deactivateSupplier, $request, $context, $supplier, $data): array {
            $deactivated = $deactivateSupplier->handle($request->user(), $context, $supplier, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $deactivated->getKey(), 'resource_type' => 'supplier'];
        });

        return to_route('suppliers.index')->with('success', 'Fornecedor inativado com sucesso.');
    }

    public function reactivate(SupplierRequest $request, TenantContext $context, Supplier $supplier, ReactivateSupplier $reactivateSupplier): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reactivateSupplier, $request, $context, $supplier, $data): array {
            $reactivated = $reactivateSupplier->handle($request->user(), $context, $supplier, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $reactivated->getKey(), 'resource_type' => 'supplier'];
        });

        return to_route('suppliers.show', $supplier)->with('success', 'Fornecedor reativado com sucesso.');
    }
}
