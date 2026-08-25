<?php

namespace App\Actions\Finance\Cash;

use App\Actions\Operational\OperationalAction;
use App\Models\CashShift;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CloseCashShift extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, CashShift $cashShift, array $data): CashShift
    {
        $unit = $this->unit($actor, $context, 'cash_shift.close');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        return DB::transaction(function () use ($actor, $context, $cashShift, $data, $tenantId, $unitId): CashShift {
            /** @var CashShift $lockedShift */
            $lockedShift = CashShift::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($cashShift->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedShift->status !== 'open') {
                throw ValidationException::withMessages([
                    'cash_shift' => 'Este turno de caixa já foi encerrado.',
                ]);
            }

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $lockedShift->lock_version) {
                throw new ConflictHttpException("O turno de caixa #{$lockedShift->getKey()} foi modificado concorrentemente.");
            }

            if (! isset($data['final_amount_cents']) || ! is_numeric($data['final_amount_cents'])) {
                throw ValidationException::withMessages([
                    'final_amount_cents' => 'O valor final apurado em caixa é obrigatório.',
                ]);
            }

            $finalAmountCents = max(0, (int) $data['final_amount_cents']);
            $differenceCents = $finalAmountCents - $lockedShift->expected_amount_cents;

            $notes = isset($data['notes']) && trim((string) $data['notes']) !== ''
                ? trim((string) $data['notes'])
                : $lockedShift->notes;

            $lockedShift->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_user_id' => $actor->getKey(),
                'final_amount_cents' => $finalAmountCents,
                'difference_cents' => $differenceCents,
                'notes' => $notes,
                'lock_version' => $lockedShift->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'cash_shift.closed', $lockedShift, [
                'cash_shift_id' => $lockedShift->getKey(),
                'closed_by_user_id' => $actor->getKey(),
                'final_amount_cents' => $finalAmountCents,
                'difference_cents' => $differenceCents,
                'expected_amount_cents' => $lockedShift->expected_amount_cents,
                'lock_version' => $lockedShift->lock_version,
            ]);

            return $lockedShift;
        }, 5);
    }
}
