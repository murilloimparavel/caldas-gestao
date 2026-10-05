<?php

namespace App\Actions\Sales;

use App\Actions\Operational\OperationalAction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RemoveSaleItem extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, Sale $sale, SaleItem $item, ?int $expectedVersion = null): Sale
    {
        $unit = $this->unit($actor, $context, 'sale.manage');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($sale->tenant_id !== $tenantId || $sale->unit_id !== $unitId) {
            throw new AuthorizationException('A comanda pertence a outra unidade ou workspace.');
        }

        $this->assertOwnSale($context, $sale);

        if ($item->sale_id !== $sale->getKey() || $item->tenant_id !== $tenantId || $item->unit_id !== $unitId) {
            throw new AuthorizationException('O item não pertence a esta comanda.');
        }

        return DB::transaction(function () use ($actor, $context, $sale, $item, $expectedVersion): Sale {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($expectedVersion !== null && $lockedSale->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('A comanda foi modificada concorrentemente.');
            }

            if (! in_array($lockedSale->status, ['draft', 'open', 'ready_to_bill'], true)) {
                throw ValidationException::withMessages([
                    'sale' => 'Apenas itens de comandas em aberto ou em rascunho podem ser removidos.',
                ]);
            }

            /** @var SaleItem $lockedItem */
            $lockedItem = SaleItem::query()
                ->whereKey($item->getKey())
                ->where('sale_id', $lockedSale->getKey())
                ->firstOrFail();

            $lockedItem->delete();

            $totalAmountCents = (int) $lockedSale->items()->sum('total_cents');
            $finalAmountCents = max(0, $totalAmountCents - (int) $lockedSale->discount_amount_cents);

            $lockedSale->forceFill([
                'total_amount_cents' => $totalAmountCents,
                'final_amount_cents' => $finalAmountCents,
                'lock_version' => $lockedSale->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'sale.item_removed', $lockedSale, [
                'sale_item_id' => $item->getKey(),
                'total_amount_cents' => $lockedSale->total_amount_cents,
                'final_amount_cents' => $lockedSale->final_amount_cents,
                'lock_version' => $lockedSale->lock_version,
            ]);

            return $lockedSale;
        }, 5);
    }
}
