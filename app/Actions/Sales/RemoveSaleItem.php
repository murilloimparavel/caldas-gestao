<?php

namespace App\Actions\Sales;

use App\Actions\Marketing\Packages\MutateCustomerPackageBalance;
use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RemoveSaleItem extends OperationalAction
{
    public function __construct(
        AuthorizationService $authorization = new AuthorizationService,
        IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        private readonly MutateCustomerPackageBalance $packageBalance = new MutateCustomerPackageBalance,
    ) {
        parent::__construct($authorization, $events);
    }

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

            if ($reservation !== null && (
                $reservation->sale_id !== $lockedSale->getKey()
                || ($lockedItem->customer_package_id !== null && $reservation->customer_package_id !== $lockedItem->customer_package_id)
                || ($lockedItem->service_id !== null && $reservation->service_id !== $lockedItem->service_id)
            )) {
                throw ValidationException::withMessages([
                    'item' => 'A reserva não corresponde ao item da comanda.',
                ]);
            }

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
                if ($reservation->package_usage_id === null) {
                    throw ValidationException::withMessages([
                        'item' => 'A reserva consumida não possui lançamento de uso associado.',
                    ]);
                }

                /** @var PackageUsage|null $packageUsage */
                $packageUsage = PackageUsage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($reservation->package_usage_id)
                    ->lockForUpdate()
                    ->first();

                if ($packageUsage === null) {
                    throw ValidationException::withMessages([
                        'item' => 'A reserva consumida aponta para um lançamento de uso inexistente.',
                    ]);
                }

                if ($packageUsage->reversed_at !== null) {
                    throw new ConflictHttpException('O lançamento de uso já foi estornado.');
                }

                if (
                    $packageUsage->customer_package_id !== $reservation->customer_package_id
                    || $packageUsage->sale_id !== $reservation->sale_id
                    || $packageUsage->sale_item_id !== $reservation->sale_item_id
                    || $packageUsage->service_id !== $reservation->service_id
                    || $packageUsage->sessions_consumed !== $reservation->sessions_reserved
                ) {
                    throw ValidationException::withMessages([
                        'item' => 'A reserva não corresponde ao lançamento de uso do pacote.',
                    ]);
                }

                /** @var CustomerPackage $package */
                $package = CustomerPackage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($packageUsage->customer_package_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                try {
                    $package = $this->packageBalance->restore(
                        $package,
                        $tenantId,
                        $unitId,
                        $packageUsage->service_id,
                        $packageUsage->sessions_consumed,
                        $package->status === 'review_required' ? 'review_required' : 'active',
                        $packageUsage->getKey(),
                    );
                } catch (ConflictHttpException $exception) {
                    throw ValidationException::withMessages([
                        'item' => str_contains($exception->getMessage(), 'service')
                            ? 'O saldo do serviço no pacote está inconsistente; não foi possível remover este item.'
                            : 'O saldo do pacote está inconsistente; não foi possível remover este item.',
                    ]);
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

            if ($lockedItem->item_type === 'package' && $lockedItem->customer_package_id !== null) {
                /** @var CustomerPackage|null $pendingPackage */
                $pendingPackage = CustomerPackage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($lockedItem->customer_package_id)
                    ->lockForUpdate()
                    ->first();

                if ($pendingPackage?->status === 'pending') {
                    $pendingPackage->forceFill([
                        'sale_id' => null,
                        'lock_version' => $pendingPackage->lock_version + 1,
                    ])->save();

                    $this->events->record($actor, $context, 'customer_package.sale_unlinked', $pendingPackage, [
                        'customer_package_id' => $pendingPackage->getKey(),
                        'sale_id' => $lockedSale->getKey(),
                        'sale_item_id' => $lockedItem->getKey(),
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
