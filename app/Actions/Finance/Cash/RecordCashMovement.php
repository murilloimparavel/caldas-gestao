<?php

namespace App\Actions\Finance\Cash;

use App\Actions\Operational\OperationalAction;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RecordCashMovement extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, CashShift $cashShift, array $data): CashMovement
    {
        $unit = $this->unit($actor, $context, 'cash_shift.move');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        return DB::transaction(function () use ($actor, $context, $cashShift, $data, $tenantId, $unitId): CashMovement {
            /** @var CashShift $lockedShift */
            $lockedShift = CashShift::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($cashShift->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedShift->status !== 'open') {
                throw ValidationException::withMessages([
                    'cash_shift' => 'Não é possível movimentar um caixa que já foi encerrado.',
                ]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $lockedShift->lock_version) {
                throw new ConflictHttpException("O turno de caixa #{$lockedShift->getKey()} foi modificado concorrentemente.");
            }

            $type = (string) ($data['type'] ?? '');
            if (! in_array($type, ['supply', 'bleed', 'sale_inflow', 'commission_outflow', 'expense_outflow'], true)) {
                throw ValidationException::withMessages([
                    'type' => 'O tipo de movimentação de caixa é inválido.',
                ]);
            }

            $amountCents = (int) ($data['amount_cents'] ?? 0);
            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'O valor da movimentação deve ser maior que zero.',
                ]);
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'O motivo da movimentação é obrigatório.',
                ]);
            }

            $isInflow = in_array($type, ['supply', 'sale_inflow'], true);
            $newExpectedAmountCents = $isInflow
                ? $lockedShift->expected_amount_cents + $amountCents
                : $lockedShift->expected_amount_cents - $amountCents;

            if ($newExpectedAmountCents < 0) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'O valor da saída excede o saldo esperado disponível em caixa.',
                ]);
            }

            /** @var CashMovement $movement */
            $movement = CashMovement::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'cash_shift_id' => $lockedShift->getKey(),
                'type' => $type,
                'amount_cents' => $amountCents,
                'reason' => $reason,
                'reference_type' => isset($data['reference_type']) && trim((string) $data['reference_type']) !== '' ? trim((string) $data['reference_type']) : null,
                'reference_id' => isset($data['reference_id']) && trim((string) $data['reference_id']) !== '' ? trim((string) $data['reference_id']) : null,
                'user_id' => $actor->getKey(),
            ]);

            $lockedShift->forceFill([
                'expected_amount_cents' => $newExpectedAmountCents,
                'lock_version' => $lockedShift->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'cash_shift.moved', $lockedShift, [
                'cash_shift_id' => $lockedShift->getKey(),
                'cash_movement_id' => $movement->getKey(),
                'amount_cents' => $amountCents,
                'expected_amount_cents' => $newExpectedAmountCents,
                'lock_version' => $lockedShift->lock_version,
            ]);

            return $movement;
        }, 5);
    }
}
