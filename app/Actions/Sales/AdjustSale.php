<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\RecordInventoryMovement;
use App\Actions\Operational\OperationalAction;
use App\Models\ClosingSession;
use App\Models\ClosingSessionPayment;
use App\Models\CommissionAccrual;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleStatusHistory;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdjustSale extends OperationalAction
{
    public function __construct(
        AuthorizationService $authorization = new AuthorizationService,
        IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        private readonly RecordInventoryMovement $recordInventoryMovement = new RecordInventoryMovement,
    ) {
        parent::__construct($authorization, $events);
    }

    public function handle(User $actor, TenantContext $context, Sale $sale, string $reason, ?int $expectedVersion = null): Sale
    {
        $unit = $context->unit;

        if ($unit === null || ! $context->user->is($actor) || (! $this->authorization->can($actor, $context, 'sale.adjust', $unit) && ! $this->authorization->can($actor, $context, 'sale.manage', $unit))) {
            throw new AuthorizationException('O operador não possui permissão para estornar comandas.');
        }

        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($sale->tenant_id !== $tenantId || $sale->unit_id !== $unitId) {
            throw new AuthorizationException('A comanda pertence a outra unidade ou workspace.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'O motivo do estorno é obrigatório.',
            ]);
        }

        return DB::transaction(function () use ($actor, $context, $sale, $reason, $expectedVersion, $tenantId, $unitId): Sale {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()
                ->with('items')
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedVersion !== null && $lockedSale->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('A comanda foi modificada concorrentemente.');
            }

            if ($lockedSale->status !== 'finalized') {
                throw ValidationException::withMessages([
                    'status' => "A comanda possui status '{$lockedSale->status}' e apenas comandas finalizadas podem ser estornadas.",
                ]);
            }

            $settledAccrual = CommissionAccrual::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('sale_id', $lockedSale->getKey())
                ->where('status', 'settled')
                ->lockForUpdate()
                ->first();

            if ($settledAccrual !== null) {
                throw ValidationException::withMessages([
                    'sale' => 'A comanda possui comissão já paga; reconcilie a comissão antes de estornar.',
                ]);
            }

            $soldPackageItems = $lockedSale->items->filter(
                static fn (SaleItem $item): bool => $item->item_type === 'package' && $item->customer_package_id !== null,
            );

            if ($soldPackageItems->isNotEmpty()) {
                $soldPackages = CustomerPackage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereIn('id', $soldPackageItems->pluck('customer_package_id')->unique())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $packageWasCancelled = $soldPackageItems->every(
                    static fn (SaleItem $item): bool => $soldPackages->get($item->customer_package_id)?->status === 'cancelled',
                );
                $closingSessions = $lockedSale->closingSessions()
                    ->with('payments')
                    ->lockForUpdate()
                    ->get();
                $hasNetReceived = $closingSessions->isEmpty() || $closingSessions->contains(
                    static fn (ClosingSession $closingSession): bool => $closingSession->payments->sum(
                        static fn (ClosingSessionPayment $payment): int => $payment->is_reversal
                            ? -$payment->amount_cents
                            : $payment->amount_cents,
                    ) > 0,
                );

                if (! $packageWasCancelled || $hasNetReceived) {
                    throw ValidationException::withMessages([
                        'sale' => 'Para ajustar uma comanda que vendeu pacote, estorne integralmente os recebimentos e confirme o cancelamento do pacote primeiro.',
                    ]);
                }
            }

            // 1. Reposição de estoque via RecordInventoryMovement (adjustment_gain)
            foreach ($lockedSale->items as $item) {
                if ($item->item_type === 'product' && $item->product_id !== null) {
                    /** @var Product $product */
                    $product = Product::query()
                        ->where('tenant_id', $tenantId)
                        ->where('unit_id', $unitId)
                        ->whereKey($item->product_id)
                        ->firstOrFail();

                    $this->recordInventoryMovement->handle($actor, $context, $product, [
                        'type' => 'adjustment_gain',
                        'quantity' => $item->quantity,
                        'reason' => 'Estorno compensatório da comanda #'.$lockedSale->getKey().': '.$reason,
                        'reference_type' => 'sale',
                        'reference_id' => $lockedSale->getKey(),
                    ]);
                }
            }

            // 2. Cancelamento de comissões apuradas (accrued -> cancelled)
            /** @var Collection<int, CommissionAccrual> $accruals */
            $accruals = CommissionAccrual::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('sale_id', $lockedSale->getKey())
                ->where('status', 'accrued')
                ->lockForUpdate()
                ->get();

            foreach ($accruals as $accrual) {
                $accrual->forceFill([
                    'status' => 'cancelled',
                    'lock_version' => $accrual->lock_version + 1,
                ])->save();

                $this->events->record($actor, $context, 'commission_accrual.cancelled', $accrual, [
                    'commission_accrual_id' => $accrual->getKey(),
                    'professional_id' => $accrual->professional_id,
                    'sale_id' => $accrual->sale_id,
                    'sale_item_id' => $accrual->sale_item_id,
                    'gross_amount_cents' => $accrual->gross_amount_cents,
                    'rate_type' => $accrual->rate_type,
                    'rate_value' => $accrual->rate_value,
                    'commission_amount_cents' => $accrual->commission_amount_cents,
                    'status' => 'cancelled',
                    'lock_version' => $accrual->lock_version,
                ]);
            }

            $reservations = PackageUsageReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('sale_id', $lockedSale->getKey())
                ->where('status', 'consumed')
                ->orderBy('customer_package_id')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                if ($reservation->package_usage_id === null) {
                    throw ValidationException::withMessages([
                        'sale' => 'A reserva consumida não possui lançamento de uso associado.',
                    ]);
                }

                $usage = PackageUsage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($reservation->package_usage_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($usage->reversed_at !== null) {
                    $reservation->forceFill([
                        'status' => 'released',
                        'released_at' => now(),
                        'release_reason' => 'Comanda estornada: '.$reason,
                    ])->save();

                    continue;
                }

                $package = CustomerPackage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($reservation->customer_package_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $restoredSessions = $package->remaining_sessions + $usage->sessions_consumed;

                if ($restoredSessions > $package->total_sessions) {
                    throw ValidationException::withMessages([
                        'sale' => 'O saldo do pacote está inconsistente; não foi possível estornar o consumo.',
                    ]);
                }

                if ($usage->service_id !== null) {
                    $serviceBalance = CustomerPackageService::query()
                        ->where('tenant_id', $tenantId)
                        ->where('unit_id', $unitId)
                        ->where('customer_package_id', $package->getKey())
                        ->where('service_id', $usage->service_id)
                        ->lockForUpdate()
                        ->first();

                    if ($serviceBalance === null || $serviceBalance->remaining_quantity + $usage->sessions_consumed > $serviceBalance->allocated_quantity) {
                        throw ValidationException::withMessages([
                            'sale' => 'O saldo do serviço no pacote está inconsistente; não foi possível estornar o consumo.',
                        ]);
                    }

                    $serviceBalance->increment('remaining_quantity', $usage->sessions_consumed);
                }
                $package->forceFill([
                    'remaining_sessions' => $restoredSessions,
                    'status' => $package->status === 'cancelled'
                        ? 'cancelled'
                        : ($package->expires_at?->endOfDay()->isPast() === true ? 'expired' : 'active'),
                    'lock_version' => $package->lock_version + 1,
                ])->save();
                $usage->forceFill([
                    'reversed_at' => now(),
                    'reversed_by_user_id' => $actor->getKey(),
                    'reversal_reason' => 'Estorno da comanda '.$lockedSale->getKey().': '.$reason,
                ])->save();
                $reservation->forceFill([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => 'Comanda estornada: '.$reason,
                ])->save();

                $this->events->record($actor, $context, 'package_usage.reversed', $usage, [
                    'customer_package_id' => $package->getKey(),
                    'package_usage_id' => $usage->getKey(),
                    'sessions_consumed' => $usage->sessions_consumed,
                    'reason_code' => 'sale_adjusted',
                    'sale_id' => $lockedSale->getKey(),
                ]);
            }

            // 3. Registro do histórico de transição
            SaleStatusHistory::query()->create([
                'id' => (string) Str::uuid7(),
                'sale_id' => $lockedSale->getKey(),
                'from_status' => 'finalized',
                'to_status' => 'adjusted',
                'user_id' => $actor->getKey(),
                'reason' => $reason,
            ]);

            // 4. Atualização da comanda
            $lockedSale->forceFill([
                'status' => 'adjusted',
                'lock_version' => $lockedSale->lock_version + 1,
            ])->save();

            // 5. Registro de auditoria
            $this->events->record($actor, $context, 'sale.adjusted', $lockedSale, [
                'sale_id' => $lockedSale->getKey(),
                'from_status' => 'finalized',
                'to_status' => 'adjusted',
                'reason' => $reason,
                'lock_version' => $lockedSale->lock_version,
            ]);

            return $lockedSale;
        }, 5);
    }
}
