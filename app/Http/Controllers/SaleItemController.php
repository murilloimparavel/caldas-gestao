<?php

namespace App\Http\Controllers;

use App\Actions\Sales\AddSaleItem;
use App\Actions\Sales\RemoveSaleItem;
use App\Http\Requests\SaleItemRequest;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class SaleItemController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function store(SaleItemRequest $request, TenantContext $context, Sale $sale, AddSaleItem $addSaleItem): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($addSaleItem, $request, $context, $sale, $data): array {
            $item = $addSaleItem->handle($request->user(), $context, $sale, $data);

            return ['resource_id' => $item->getKey(), 'resource_type' => 'sale-item'];
        });

        return to_route('sales.show', $sale)->with('success', 'Item adicionado à comanda com sucesso.');
    }

    public function destroy(Request $request, TenantContext $context, Sale $sale, SaleItem $item, RemoveSaleItem $removeSaleItem): RedirectResponse
    {
        Gate::authorize('update', $sale);

        $expectedVersion = $request->has('lock_version') ? (int) $request->input('lock_version') : null;
        $data = ['lock_version' => $expectedVersion];

        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($removeSaleItem, $request, $context, $sale, $item, $expectedVersion): array {
            $updatedSale = $removeSaleItem->handle($request->user(), $context, $sale, $item, $expectedVersion);

            return ['resource_id' => $updatedSale->getKey(), 'resource_type' => 'sale'];
        });

        return to_route('sales.show', $sale)->with('success', 'Item removido da comanda com sucesso.');
    }
}
