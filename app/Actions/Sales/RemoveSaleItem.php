<?php

namespace App\Actions\Sales;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
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

        if ($item->sale_id !== $sale->getKey() || $item->tenant_id !== $tenantId || $item->unit_id !== $unitId) {
            throw new AuthorizationException('O item não pertence a esta comanda.');
        }

        return DB::transaction(function () use ($actor, $context, $sale, $item, $expectedVersion, $tenantId, $unitId): Sale {
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

            /** @var PackageUsageReservation|null $reservation */
            $reservation = PackageUsageReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('sale_item_id', $lockedItem->getKey())
                ->lockForUpdate()
                ->first();

            if ($reservation !== null && $reservation->status === 'reserved') {
                $reservation->forceFill([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => 'Item removido da comanda '.$lockedSale->getKey(),
                ])->save();

                $this->events->record($actor, $context, 'package_usage.reservation_released', $reservation, [
                    'package_usage_id' => $reservation->getKey(),
                    'quantity' => $reservation->sessions_reserved,
                    'reason_code' => 'sale_item_removed',
                ]);
            } elseif ($reservation !== null && $reservation->status === 'consumed') {
                /** @var PackageUsage|null $packageUsage */
                $packageUsage = PackageUsage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($reservation->package_usage_id)
                    ->lockForUpdate()
                    ->first();

                if ($packageUsage !== null && $packageUsage->reversed_at === null) {
                    /** @var CustomerPackage $package */
                    $package = CustomerPackage::query()
                        ->where('tenant_id', $tenantId)
                        ->where('unit_id', $unitId)
                        ->whereKey($packageUsage->customer_package_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $restoredSessions = $package->remaining_sessions + $packageUsage->sessions_consumed;
                    if ($restoredSessions > $package->total_sessions) {
                        throw ValidationException::withMessages([
                            'item' => 'O saldo do pacote está inconsistente; não foi possível remover este item.',
                        ]);
                    }

                    $package->forceFill([
                        'remaining_sessions' => $restoredSessions,
                        'status' => 'active',
                        'lock_version' => $package->lock_version + 1,
                    ])->save();

                    if ($packageUsage->service_id !== null) {
                        CustomerPackageService::query()
                            ->where('tenant_id', $tenantId)
                            ->where('unit_id', $unitId)
                            ->where('customer_package_id', $package->getKey())
                            ->where('service_id', $packageUsage->service_id)
                            ->increment('remaining_quantity', $packageUsage->sessions_consumed);
                    }

                    $packageUsage->forceFill([
                        'reversed_at' => now(),
                        'reversed_by_user_id' => $actor->getKey(),
                        'reversal_reason' => 'Item removido da comanda '.$lockedSale->getKey(),
                    ])->save();

                    $this->events->record($actor, $context, 'package_usage.reversed', $packageUsage, [
                        'customer_package_id' => $package->getKey(),
                        'package_usage_id' => $packageUsage->getKey(),
                        'sessions_consumed' => $packageUsage->sessions_consumed,
                        'reason_code' => 'sale_item_removed',
                    ]);
                }
            }

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
