<?php

namespace App\Actions\Finance\Cash;

use App\Actions\Operational\OperationalAction;
use App\Models\CashShift;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OpenCashShift extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): CashShift
    {
        $unit = $this->unit($actor, $context, 'cash_shift.open');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        return DB::transaction(function () use ($actor, $context, $data, $tenantId, $unitId): CashShift {
            $existingOpen = CashShift::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('opened_by_user_id', $actor->getKey())
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existingOpen !== null) {
                throw ValidationException::withMessages([
                    'cash_shift' => 'Já existe um turno de caixa aberto para este operador nesta unidade.',
                ]);
            }

            $initialAmountCents = max(0, (int) ($data['initial_amount_cents'] ?? 0));
            $notes = isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null;

            /** @var CashShift $shift */
            $shift = CashShift::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'opened_by_user_id' => $actor->getKey(),
                'closed_by_user_id' => null,
                'initial_amount_cents' => $initialAmountCents,
                'expected_amount_cents' => $initialAmountCents,
                'final_amount_cents' => null,
                'difference_cents' => null,
                'status' => 'open',
                'opened_at' => now(),
                'closed_at' => null,
                'notes' => $notes,
                'lock_version' => 1,
            ]);

            $this->events->record($actor, $context, 'cash_shift.opened', $shift, [
                'cash_shift_id' => $shift->getKey(),
                'opened_by_user_id' => $actor->getKey(),
                'initial_amount_cents' => $initialAmountCents,
                'expected_amount_cents' => $initialAmountCents,
            ]);

            return $shift;
        }, 5);
    }
}
