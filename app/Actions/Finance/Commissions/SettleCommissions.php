<?php

namespace App\Actions\Finance\Commissions;

use App\Actions\Operational\OperationalAction;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Professional;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SettleCommissions extends OperationalAction
{
    /**
     * @param  array{
     *     accrual_ids?: list<string>|null,
     *     paid_at?: string|null,
     *     notes?: string|null,
     *     period_start?: string|null,
     *     period_end?: string|null,
     * }  $data
     */
    public function handle(User $actor, TenantContext $context, Professional $professional, array $data): CommissionSettlement
    {
        $unit = $this->unit($actor, $context, 'commission.settle');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($professional->tenant_id !== $tenantId || $professional->unit_id !== $unitId) {
            throw ValidationException::withMessages([
                'professional_id' => 'O profissional pertence a outra unidade ou workspace.',
            ]);
        }

        return DB::transaction(function () use ($actor, $context, $professional, $data, $tenantId, $unitId): CommissionSettlement {
            $query = CommissionAccrual::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('professional_id', $professional->getKey())
                ->where('status', 'accrued');

            if (! empty($data['accrual_ids'])) {
                $query->whereIn('id', $data['accrual_ids']);
            }

            /** @var Collection<int, CommissionAccrual> $accruals */
            $accruals = $query->orderBy('created_at')->lockForUpdate()->get();

            if ($accruals->isEmpty()) {
                throw ValidationException::withMessages([
                    'professional_id' => 'Não existem comissões acumuladas pendentes de liquidação para este profissional.',
                ]);
            }

            $totalAmountCents = (int) $accruals->sum('commission_amount_cents');
            $periodStart = ! empty($data['period_start']) ? Carbon::parse($data['period_start']) : $accruals->min('created_at');
            $periodEnd = ! empty($data['period_end']) ? Carbon::parse($data['period_end']) : $accruals->max('created_at');
            $paidAt = ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();

            /** @var CommissionSettlement $settlement */
            $settlement = CommissionSettlement::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'professional_id' => $professional->getKey(),
                'total_amount_cents' => $totalAmountCents,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'paid_at' => $paidAt,
                'user_id' => $actor->getKey(),
                'notes' => $data['notes'] ?? null,
                'lock_version' => 1,
            ]);

            foreach ($accruals as $accrual) {
                $accrual->forceFill([
                    'status' => 'settled',
                    'settled_at' => $paidAt,
                    'settlement_id' => $settlement->getKey(),
                    'lock_version' => $accrual->lock_version + 1,
                ])->save();
            }

            $this->events->record($actor, $context, 'commission_settlement.completed', $settlement, [
                'commission_settlement_id' => $settlement->getKey(),
                'professional_id' => $settlement->professional_id,
                'total_amount_cents' => $settlement->total_amount_cents,
                'period_start' => $settlement->period_start?->toISOString(),
                'period_end' => $settlement->period_end?->toISOString(),
                'paid_at' => $settlement->paid_at->toISOString(),
                'accrual_ids' => $accruals->pluck('id')->all(),
                'lock_version' => $settlement->lock_version,
            ]);

            return $settlement;
        }, 5);
    }
}
