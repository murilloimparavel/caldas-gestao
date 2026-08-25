<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\RecordInventoryMovement;
use App\Http\Requests\InventoryMovementRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', InventoryMovement::class);

        $unitId = $context->unit?->getKey();
        $tenantId = $context->tenant->getKey();

        $productId = trim((string) $request->string('product_id'));
        $type = trim((string) $request->string('type'));
        $date = trim((string) $request->string('date'));

        $movements = InventoryMovement::query()
            ->with(['product:id,name,unit_of_measure,sku,current_stock,min_stock', 'user:id,name'])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->when($productId !== '', fn ($query) => $query->where('product_id', $productId))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($date !== '', fn ($query) => $query->whereDate('created_at', $date))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->orderBy('name')
            ->get(['id', 'name', 'current_stock', 'min_stock', 'unit_of_measure', 'lock_version', 'cost_price_cents'])
            ->values()
            ->all();

        return Inertia::render('inventory/index', [
            'movements' => $movements,
            'products' => $products,
            'filters' => [
                'product_id' => $productId,
                'type' => $type,
                'date' => $date,
            ],
        ]);
    }

    public function store(InventoryMovementRequest $request, TenantContext $context, RecordInventoryMovement $recordInventoryMovement): RedirectResponse
    {
        $data = $request->validated();
        $tenantId = $context->tenant->getKey();
        $unitId = $context->unit?->getKey();

        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->whereKey($data['product_id'])
            ->firstOrFail();

        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($recordInventoryMovement, $request, $context, $product, $data): array {
            $movement = $recordInventoryMovement->handle($request->user(), $context, $product, $data);

            return ['resource_id' => $movement->getKey(), 'resource_type' => 'inventory'];
        });

        return back()->with('success', 'Movimentação de estoque registrada com sucesso.');
    }
}
