<?php

namespace App\Actions\Finance\Commissions;

use App\Actions\Operational\OperationalAction;
use App\Models\CommissionRule;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SaveCommissionRule extends OperationalAction
{
    /**
     * @param  array{
     *     professional_id?: string|null,
     *     service_id?: string|null,
     *     service_ids?: list<string>,
     *     product_id?: string|null,
     *     type?: string,
     *     value_rate: int,
     *     is_active?: bool,
     *     lock_version?: int
     * }  $data
     */
    public function handle(User $actor, TenantContext $context, array $data, ?CommissionRule $rule = null): CommissionRule
    {
        $unit = $this->unit($actor, $context, 'commission.manage');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        return DB::transaction(function () use ($actor, $context, $data, $rule, $tenantId, $unitId): CommissionRule {
            if ($rule !== null) {
                /** @var CommissionRule $lockedRule */
                $lockedRule = CommissionRule::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($rule->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (isset($data['lock_version']) && $lockedRule->lock_version !== (int) $data['lock_version']) {
                    throw new ConflictHttpException('A regra de comissão foi modificada concorrentemente.');
                }

                $lockedRule->forceFill([
                    'professional_id' => $data['professional_id'] ?? $lockedRule->professional_id,
                    'service_id' => array_key_exists('service_id', $data) ? $data['service_id'] : $lockedRule->service_id,
                    'product_id' => array_key_exists('product_id', $data) ? $data['product_id'] : $lockedRule->product_id,
                    'type' => $data['type'] ?? $lockedRule->type,
                    'value_rate' => $data['value_rate'],
                    'is_active' => $data['is_active'] ?? $lockedRule->is_active,
                    'lock_version' => $lockedRule->lock_version + 1,
                ])->save();

                $this->events->record($actor, $context, 'commission_rule.updated', $lockedRule, [
                    'commission_rule_id' => $lockedRule->getKey(),
                    'professional_id' => $lockedRule->professional_id,
                    'service_id' => $lockedRule->service_id,
                    'product_id' => $lockedRule->product_id,
                    'rate_type' => $lockedRule->type,
                    'rate_value' => $lockedRule->value_rate,
                    'value_rate' => $lockedRule->value_rate,
                    'is_active' => $lockedRule->is_active,
                    'lock_version' => $lockedRule->lock_version,
                ]);

                return $lockedRule;
            }

            /** @var CommissionRule $createdRule */
            $createdRule = CommissionRule::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'professional_id' => $data['professional_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'type' => $data['type'] ?? 'percentage',
                'value_rate' => $data['value_rate'],
                'is_active' => $data['is_active'] ?? true,
                'lock_version' => 1,
            ]);

            $this->events->record($actor, $context, 'commission_rule.created', $createdRule, [
                'commission_rule_id' => $createdRule->getKey(),
                'professional_id' => $createdRule->professional_id,
                'service_id' => $createdRule->service_id,
                'product_id' => $createdRule->product_id,
                'rate_type' => $createdRule->type,
                'rate_value' => $createdRule->value_rate,
                'value_rate' => $createdRule->value_rate,
                'is_active' => $createdRule->is_active,
                'lock_version' => $createdRule->lock_version,
            ]);

            return $createdRule;
        }, 5);
    }

    /**
     * @param  list<string>  $serviceIds
     * @param  array{professional_id?: string|null, type?: string, value_rate: int, is_active?: bool}  $data
     * @return list<CommissionRule>
     */
    public function handleMany(User $actor, TenantContext $context, array $serviceIds, array $data): array
    {
        $unit = $this->unit($actor, $context, 'commission.manage');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        return DB::transaction(function () use ($actor, $context, $serviceIds, $data, $tenantId, $unitId): array {
            $rules = [];

            foreach ($serviceIds as $serviceId) {
                /** @var CommissionRule $rule */
                $rule = CommissionRule::query()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenantId,
                    'unit_id' => $unitId,
                    'professional_id' => $data['professional_id'] ?? null,
                    'service_id' => $serviceId,
                    'type' => $data['type'] ?? 'percentage',
                    'value_rate' => $data['value_rate'],
                    'is_active' => $data['is_active'] ?? true,
                    'lock_version' => 1,
                ]);

                $this->events->record($actor, $context, 'commission_rule.created', $rule, [
                    'commission_rule_id' => $rule->getKey(),
                    'professional_id' => $rule->professional_id,
                    'service_id' => $rule->service_id,
                    'product_id' => $rule->product_id,
                    'rate_type' => $rule->type,
                    'rate_value' => $rule->value_rate,
                    'value_rate' => $rule->value_rate,
                    'is_active' => $rule->is_active,
                    'lock_version' => $rule->lock_version,
                ]);
                $rules[] = $rule;
            }

            return $rules;
        }, 5);
    }
}
