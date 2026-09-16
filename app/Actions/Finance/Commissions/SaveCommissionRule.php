<?php

namespace App\Actions\Finance\Commissions;

use App\Actions\Operational\OperationalAction;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SaveCommissionRule extends OperationalAction
{
    /**
     * @param  array{
     *     professional_id?: string|null,
     *     service_id?: string|null,
     *     service_ids?: list<string>,
     *     product_id?: string|null,
     *     category_id?: string|null,
     *     scope?: string,
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

                $this->ensureNoConflicts($tenantId, $unitId, $data['professional_id'] ?? $lockedRule->professional_id, [
                    'service_id' => array_key_exists('service_id', $data) ? $data['service_id'] : $lockedRule->service_id,
                    'product_id' => array_key_exists('product_id', $data) ? $data['product_id'] : $lockedRule->product_id,
                    'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $lockedRule->category_id,
                    'scope' => $data['scope'] ?? $lockedRule->scope,
                ], $lockedRule->getKey());

                $lockedRule->forceFill([
                    'professional_id' => $data['professional_id'] ?? $lockedRule->professional_id,
                    'service_id' => array_key_exists('service_id', $data) ? $data['service_id'] : $lockedRule->service_id,
                    'product_id' => array_key_exists('product_id', $data) ? $data['product_id'] : $lockedRule->product_id,
                    'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $lockedRule->category_id,
                    'scope' => $data['scope'] ?? $lockedRule->scope,
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
                    'category_id' => $lockedRule->category_id,
                    'rate_type' => $lockedRule->type,
                    'rate_value' => $lockedRule->value_rate,
                    'value_rate' => $lockedRule->value_rate,
                    'is_active' => $lockedRule->is_active,
                    'lock_version' => $lockedRule->lock_version,
                ]);

                return $lockedRule;
            }

            $scope = $data['scope'] ?? (($data['service_id'] ?? null) !== null
                ? 'service'
                : (($data['product_id'] ?? null) !== null ? 'product' : (($data['category_id'] ?? null) !== null ? 'service_category' : 'all')));

            $this->ensureNoConflicts($tenantId, $unitId, $data['professional_id'] ?? null, [
                'service_id' => $data['service_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'scope' => $scope,
            ]);

            /** @var CommissionRule $createdRule */
            $createdRule = CommissionRule::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'professional_id' => $data['professional_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'scope' => $scope,
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
                'category_id' => $createdRule->category_id,
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
     * @param  array{
     *     professional_id?: string|null,
     *     service_id?: string|null,
     *     service_ids?: list<string>,
     *     product_id?: string|null,
     *     category_id?: string|null,
     *     scope?: string,
     *     item_type?: string,
     *     type?: string,
     *     value_rate: int,
     *     is_active?: bool,
     *     lock_version?: int
     * }  $data
     * @param  list<string>  $categoryIds
     * @return list<CommissionRule>
     */
    public function handleManyCategories(User $actor, TenantContext $context, array $categoryIds, array $data): array
    {
        return DB::transaction(function () use ($actor, $context, $categoryIds, $data): array {
            $scope = $data['scope'] ?? (($data['item_type'] ?? 'service') === 'product' ? 'product_category' : 'service_category');
            $rules = [];

            foreach ($categoryIds as $categoryId) {
                $rules[] = $this->handle($actor, $context, [
                    ...$data,
                    'scope' => $scope,
                    'category_id' => $categoryId,
                ]);
            }

            return $rules;
        });
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
            $this->ensureNoConflictsForServices($tenantId, $unitId, $data['professional_id'] ?? null, $serviceIds);
            $rules = [];

            foreach ($serviceIds as $serviceId) {
                /** @var CommissionRule $rule */
                $rule = CommissionRule::query()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenantId,
                    'unit_id' => $unitId,
                    'professional_id' => $data['professional_id'] ?? null,
                    'service_id' => $serviceId,
                    'scope' => 'service',
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

    /**
     * @param  list<string>  $productIds
     * @param  array{professional_id?: string|null, type?: string, value_rate: int, is_active?: bool}  $data
     * @return list<CommissionRule>
     */
    public function handleManyProducts(User $actor, TenantContext $context, array $productIds, array $data): array
    {
        $unit = $this->unit($actor, $context, 'commission.manage');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        return DB::transaction(function () use ($actor, $context, $productIds, $data, $tenantId, $unitId): array {
            $conflictingRules = CommissionRule::query()
                ->with('product:id,name')
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('professional_id', $data['professional_id'] ?? null)
                ->where('scope', 'product')
                ->whereNull('service_id')
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get();

            if ($conflictingRules->isNotEmpty()) {
                $names = $conflictingRules->pluck('product.name')->filter()->unique()->implode(', ');

                throw ValidationException::withMessages([
                    'product_ids' => "Já existe uma regra de comissão para: {$names}.",
                ]);
            }

            $rules = [];

            foreach ($productIds as $productId) {
                /** @var CommissionRule $rule */
                $rule = CommissionRule::query()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenantId,
                    'unit_id' => $unitId,
                    'professional_id' => $data['professional_id'] ?? null,
                    'product_id' => $productId,
                    'scope' => 'product',
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

    /**
     * @param  array{service_id?: string|null, product_id?: string|null, category_id?: string|null, scope?: string}  $item
     */
    private function ensureNoConflicts(string $tenantId, string $unitId, ?string $professionalId, array $item, ?string $exceptRuleId = null): void
    {
        $query = CommissionRule::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('professional_id', $professionalId)
            ->where('scope', $item['scope'] ?? 'all')
            ->where('service_id', $item['service_id'] ?? null)
            ->where('product_id', $item['product_id'] ?? null)
            ->where('category_id', $item['category_id'] ?? null)
            ->when($exceptRuleId !== null, fn ($query) => $query->where('id', '!=', $exceptRuleId))
            ->lockForUpdate();

        if ($query->exists()) {
            $itemName = ($item['service_id'] ?? null) !== null
                ? Service::query()->whereKey($item['service_id'])->value('name')
                : (($item['product_id'] ?? null) !== null
                    ? Product::query()->whereKey($item['product_id'])->value('name')
                : match ($item['scope'] ?? 'all') {
                    'service_category', 'product_category' => 'a categoria selecionada',
                    'service' => 'Todos os serviços',
                    'product' => 'Todos os produtos',
                    default => 'Todos os itens',
                });

            $errorKey = ($item['service_id'] ?? null) !== null
                ? 'service_id'
                : (($item['product_id'] ?? null) !== null ? 'product_id' : (($item['category_id'] ?? null) !== null ? 'category_id' : 'scope'));

            throw ValidationException::withMessages([
                $errorKey => "Já existe uma regra de comissão para {$itemName} neste profissional.",
            ]);
        }
    }

    /**
     * @param  list<string>  $serviceIds
     */
    private function ensureNoConflictsForServices(string $tenantId, string $unitId, ?string $professionalId, array $serviceIds): void
    {
        $conflictingRules = CommissionRule::query()
            ->with('service:id,name')
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('professional_id', $professionalId)
            ->where('scope', 'service')
            ->whereNull('product_id')
            ->whereIn('service_id', $serviceIds)
            ->lockForUpdate()
            ->get();

        if ($conflictingRules->isNotEmpty()) {
            $names = $conflictingRules->pluck('service.name')->filter()->unique()->implode(', ');

            throw ValidationException::withMessages([
                'service_ids' => "Já existe uma regra de comissão para: {$names}.",
            ]);
        }
    }
}
