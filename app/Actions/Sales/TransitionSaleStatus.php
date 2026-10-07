<?php

namespace App\Actions\Sales;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\PackageUsageReservation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleStatusHistory;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TransitionSaleStatus extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, Sale $sale, string $toStatus, ?string $reason = null, ?int $expectedVersion = null): Sale
    {
        $unit = $this->unit($actor, $context, 'sale.manage');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($sale->tenant_id !== $tenantId || $sale->unit_id !== $unitId) {
            throw new AuthorizationException('A comanda pertence a outra unidade ou workspace.');
        }

        return DB::transaction(function () use ($actor, $context, $sale, $toStatus, $reason, $expectedVersion, $tenantId, $unitId): Sale {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($expectedVersion !== null && $lockedSale->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('A comanda foi modificada concorrentemente.');
            }

            $fromStatus = $lockedSale->status;

            $allowedTransitions = [
                'draft' => ['open', 'cancelled'],
                'open' => ['ready_to_bill', 'cancelled'],
                'ready_to_bill' => ['open', 'cancelled'],
            ];

            if (! isset($allowedTransitions[$fromStatus]) || ! in_array($toStatus, $allowedTransitions[$fromStatus], true)) {
                throw ValidationException::withMessages([
                    'status' => "Transição de status inválida de '{$fromStatus}' para '{$toStatus}'.",
                ]);
            }

            if ($toStatus === 'cancelled') {
                $reservations = PackageUsageReservation::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('sale_id', $lockedSale->getKey())
                    ->where('status', 'reserved')
                    ->lockForUpdate()
                    ->get();

                foreach ($reservations as $reservation) {
                    $reservation->forceFill([
                        'status' => 'released',
                        'released_at' => now(),
                        'release_reason' => 'Comanda cancelada',
                    ])->save();

                    $this->events->record($actor, $context, 'package_usage.reservation_released', $reservation, [
                        'package_usage_id' => $reservation->getKey(),
                        'quantity' => $reservation->sessions_reserved,
                        'reason_code' => 'sale_cancelled',
                    ]);
                }

                $packageItems = SaleItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('sale_id', $lockedSale->getKey())
                    ->where('item_type', 'package')
                    ->whereNotNull('customer_package_id')
                    ->lockForUpdate()
                    ->get();

                $packageIds = $packageItems->pluck('customer_package_id');
                $directPackageIds = CustomerPackage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('sale_id', $lockedSale->getKey())
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->pluck('id');

                foreach ($packageIds->merge($directPackageIds)->unique() as $packageId) {
                    $package = CustomerPackage::query()
                        ->where('tenant_id', $tenantId)
                        ->where('unit_id', $unitId)
                        ->whereKey($packageId)
                        ->lockForUpdate()
                        ->first();

                    if ($package?->status !== 'pending') {
                        continue;
                    }

                    $package->forceFill([
                        'status' => 'cancelled',
                        'lock_version' => $package->lock_version + 1,
                    ])->save();

                    $this->events->record($actor, $context, 'customer_package.cancelled', $package, [
                        'customer_package_id' => $package->getKey(),
                        'sale_id' => $lockedSale->getKey(),
                        'sale_item_id' => $packageItems->firstWhere('customer_package_id', $package->getKey())?->getKey(),
                        'reason' => 'Comanda cancelada',
                    ]);
                }
            }

            SaleStatusHistory::query()->create([
                'id' => (string) Str::uuid7(),
                'sale_id' => $lockedSale->getKey(),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'user_id' => $actor->getKey(),
                'reason' => $reason ?? 'Transição de status',
            ]);

            $lockedSale->forceFill([
                'status' => $toStatus,
                'lock_version' => $lockedSale->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'sale.status_changed', $lockedSale, [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'lock_version' => $lockedSale->lock_version,
            ]);

            return $lockedSale;
        }, 5);
    }
}
