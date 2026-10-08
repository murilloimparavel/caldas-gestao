<?php

namespace App\Actions\Closing;

use App\Actions\Operational\OperationalAction;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\ClosingSessionPayment;
use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReverseClosingSessionPayment extends OperationalAction
{
    /** @param array{reason: string} $data */
    public function handle(User $actor, TenantContext $context, ClosingSessionPayment $payment, array $data): ClosingSessionPayment
    {
        $unit = $context->unit;
        if ($unit === null || ! $context->user->is($actor) || (! $this->authorization->can($actor, $context, 'sale.close', $unit) && ! $this->authorization->can($actor, $context, 'sale.manage', $unit))) {
            throw new AuthorizationException('O operador não possui permissão para estornar recebimentos.');
        }

        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo do estorno.']);
        }

        return DB::transaction(function () use ($actor, $context, $payment, $reason, $unit): ClosingSessionPayment {
            $original = ClosingSessionPayment::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($original->is_reversal) {
                throw ValidationException::withMessages(['payment' => 'Um estorno não pode ser estornado novamente.']);
            }
            if (ClosingSessionPayment::query()->where('reversal_of_id', $original->getKey())->exists()) {
                throw ValidationException::withMessages(['payment' => 'Este recebimento já possui um estorno registrado.']);
            }

            $shift = null;
            if ($original->payment_method === 'cash' && $original->cash_shift_id !== null) {
                $shift = CashShift::query()->where('tenant_id', $original->tenant_id)->where('unit_id', $original->unit_id)->whereKey($original->cash_shift_id)->lockForUpdate()->first();
                if ($shift?->status === 'open' && $shift->expected_amount_cents < $original->amount_cents) {
                    throw ValidationException::withMessages(['payment' => 'O saldo esperado do turno não comporta o estorno.']);
                }
            }

            $closingSession = $original->closingSession()->with('sales.items')->lockForUpdate()->firstOrFail();
            $sessionPayments = ClosingSessionPayment::query()
                ->where('tenant_id', $original->tenant_id)
                ->where('unit_id', $original->unit_id)
                ->where('closing_session_id', $original->closing_session_id)
                ->get(['amount_cents', 'is_reversal']);
            $netReceivedAfterReversal = $sessionPayments->sum(
                static fn (ClosingSessionPayment $sessionPayment): int => $sessionPayment->is_reversal
                    ? -$sessionPayment->amount_cents
                    : $sessionPayment->amount_cents,
            ) - $original->amount_cents;
            $packageItems = $closingSession->sales
                ->flatMap(fn ($sale) => $sale->items)
                ->filter(fn (SaleItem $item): bool => $item->item_type === 'package' && $item->customer_package_id !== null);

            foreach ($packageItems as $packageItem) {
                /** @var CustomerPackage $package */
                $package = CustomerPackage::query()
                    ->where('tenant_id', $original->tenant_id)
                    ->where('unit_id', $original->unit_id)
                    ->whereKey($packageItem->customer_package_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $hasIrreversibleUsage = PackageUsage::query()
                    ->where('tenant_id', $original->tenant_id)
                    ->where('unit_id', $original->unit_id)
                    ->where('customer_package_id', $package->getKey())
                    ->whereNull('reversed_at')
                    ->exists();
                $hasReservation = PackageUsageReservation::query()
                    ->where('tenant_id', $original->tenant_id)
                    ->where('unit_id', $original->unit_id)
                    ->where('customer_package_id', $package->getKey())
                    ->whereIn('status', ['reserved', 'consumed'])
                    ->exists();

                if ($hasIrreversibleUsage || $hasReservation || $package->remaining_sessions < $package->total_sessions) {
                    throw ValidationException::withMessages([
                        'payment' => 'O recebimento não pode ser estornado porque o pacote já possui consumo ou reserva registrada.',
                    ]);
                }

                if ($package->status === 'active' && $netReceivedAfterReversal < $closingSession->final_total_cents) {
                    $package->forceFill([
                        'status' => 'cancelled',
                        'lock_version' => $package->lock_version + 1,
                    ])->save();

                    $this->events->record($actor, $context, 'customer_package.cancelled', $package, [
                        'customer_package_id' => $package->getKey(),
                        'closing_session_id' => $closingSession->getKey(),
                        'original_payment_id' => $original->getKey(),
                        'reason' => $reason,
                    ]);
                }
            }

            $reversal = ClosingSessionPayment::query()->create([
                'id' => (string) Str::uuid7(), 'tenant_id' => $original->tenant_id, 'unit_id' => $original->unit_id,
                'closing_session_id' => $original->closing_session_id, 'cash_shift_id' => $original->cash_shift_id,
                'payment_method' => $original->payment_method, 'amount_cents' => $original->amount_cents,
                'tendered_cents' => $original->tendered_cents, 'change_cents' => $original->change_cents,
                'recorded_by_user_id' => $actor->getKey(), 'recorded_at' => now(), 'reversal_of_id' => $original->getKey(),
                'is_reversal' => true, 'reversal_reason' => $reason,
            ]);

            if ($shift?->status === 'open') {
                CashMovement::query()->create([
                    'id' => (string) Str::uuid7(), 'tenant_id' => $shift->tenant_id, 'unit_id' => $shift->unit_id,
                    'cash_shift_id' => $shift->getKey(), 'type' => 'sale_reversal_outflow', 'amount_cents' => $original->amount_cents,
                    'reason' => 'Estorno de recebimento: '.$reason, 'reference_type' => 'closing_session_payment',
                    'reference_id' => $original->getKey(), 'user_id' => $actor->getKey(),
                ]);
                $shift->forceFill(['expected_amount_cents' => $shift->expected_amount_cents - $original->amount_cents, 'lock_version' => $shift->lock_version + 1])->save();
            }

            $this->events->record($actor, $context, 'closing_session_payment.reversed', $reversal, [
                'original_payment_id' => $original->getKey(), 'reversal_payment_id' => $reversal->getKey(),
                'amount_cents' => $original->amount_cents, 'reason' => $reason, 'cash_shift_adjusted' => $shift?->status === 'open',
            ]);

            return $reversal;
        }, 5);
    }
}
