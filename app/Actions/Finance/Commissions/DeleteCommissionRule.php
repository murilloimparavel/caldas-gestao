<?php

namespace App\Actions\Finance\Commissions;

use App\Actions\Operational\OperationalAction;
use App\Models\CommissionRule;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class DeleteCommissionRule extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, CommissionRule $rule): void
    {
        $unit = $this->unit($actor, $context, 'commission.manage');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        DB::transaction(function () use ($actor, $context, $rule, $tenantId, $unitId): void {
            /** @var CommissionRule $lockedRule */
            $lockedRule = CommissionRule::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($rule->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $ruleId = $lockedRule->getKey();

            $lockedRule->delete();

            $this->events->record($actor, $context, 'commission_rule.deleted', $lockedRule, [
                'commission_rule_id' => $ruleId,
                'professional_id' => $lockedRule->professional_id,
            ]);
        }, 5);
    }
}
