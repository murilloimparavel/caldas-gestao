<?php

namespace App\Actions\Finance\Commissions;

use App\Actions\Operational\OperationalAction;
use App\Models\CommissionAccrual;
use App\Models\CommissionRule;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class AccrueCommissionsForSale extends OperationalAction
{
    /**
     * @return Collection<int, CommissionAccrual>
     */
    public function handle(User $actor, TenantContext $context, Sale $sale): Collection
    {
        $tenantId = $context->tenant->getKey();
        $unitId = $sale->unit_id;

        /** @var Collection<int, CommissionRule> $activeRules */
        $activeRules = CommissionRule::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->get();

        $accruals = new Collection;

        $sale->loadMissing(['items.service:id,category_id', 'items.product:id,category_id']);

        foreach ($sale->items as $item) {
            $commissionProfessionalId = $item->item_type === 'product'
                ? ($item->seller_professional_id ?? $item->professional_id)
                : $item->professional_id;

            if ($commissionProfessionalId === null) {
                continue;
            }

            $matchedRule = $this->findBestMatchingRule($activeRules, $item, $commissionProfessionalId);

            if ($matchedRule === null) {
                continue;
            }

            $commissionAmountCents = match ($matchedRule->type) {
                'percentage' => (int) round(($item->total_cents * $matchedRule->value_rate) / 100),
                'fixed' => (int) ($matchedRule->value_rate * max(1, $item->quantity)),
                default => 0,
            };

            /** @var CommissionAccrual $accrual */
            $accrual = CommissionAccrual::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'professional_id' => $commissionProfessionalId,
                'sale_id' => $sale->getKey(),
                'sale_item_id' => $item->getKey(),
                'item_name_snapshot' => $item->name_snapshot,
                'gross_amount_cents' => $item->total_cents,
                'rate_type' => $matchedRule->type,
                'rate_value' => $matchedRule->value_rate,
                'commission_amount_cents' => $commissionAmountCents,
                'status' => 'accrued',
                'lock_version' => 1,
            ]);

            $this->events->record($actor, $context, 'commission_accrual.created', $accrual, [
                'commission_accrual_id' => $accrual->getKey(),
                'professional_id' => $accrual->professional_id,
                'sale_id' => $accrual->sale_id,
                'sale_item_id' => $accrual->sale_item_id,
                'gross_amount_cents' => $accrual->gross_amount_cents,
                'rate_type' => $accrual->rate_type,
                'rate_value' => $accrual->rate_value,
                'commission_amount_cents' => $accrual->commission_amount_cents,
                'status' => $accrual->status,
                'lock_version' => $accrual->lock_version,
            ]);

            $accruals->push($accrual);
        }

        return $accruals;
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     */
    private function findBestMatchingRule(Collection $rules, SaleItem $item, string $professionalId): ?CommissionRule
    {
        $bestRule = null;
        $highestScore = -1;

        foreach ($rules as $rule) {
            $score = $this->calculateRuleMatchScore($rule, $item, $professionalId);

            if ($score !== null && $score > $highestScore) {
                $highestScore = $score;
                $bestRule = $rule;
            }
        }

        return $bestRule;
    }

    private function calculateRuleMatchScore(CommissionRule $rule, SaleItem $item, string $professionalId): ?int
    {
        $professionalMatches = ($rule->professional_id === null || $rule->professional_id === $professionalId);

        if (! $professionalMatches) {
            return null;
        }

        $serviceMatches = $item->service_id !== null
            && $rule->scope === 'service'
            && ($rule->service_id === null || $rule->service_id === $item->service_id);
        $productMatches = $item->product_id !== null
            && $rule->scope === 'product'
            && ($rule->product_id === null || $rule->product_id === $item->product_id);
        $itemCategoryId = $item->service_id !== null
            ? $item->service->category_id
            : $item->product?->category_id;
        $categoryMatches = $itemCategoryId !== null
            && $rule->category_id === $itemCategoryId
            && (($item->service_id !== null && $rule->scope === 'service_category')
                || ($item->product_id !== null && $rule->scope === 'product_category'));
        $isItemSpecific = ($serviceMatches && $rule->service_id !== null)
            || ($productMatches && $rule->product_id !== null);
        $isCategorySpecific = $categoryMatches;
        $isRuleItemGeneric = $rule->scope === 'all'
            || ($rule->scope === 'service' && $rule->service_id === null && $item->service_id !== null)
            || ($rule->scope === 'product' && $rule->product_id === null && $item->product_id !== null);

        if (! $isItemSpecific && ! $isCategorySpecific && ! $isRuleItemGeneric) {
            return null;
        }

        // Professional + item > professional + category > professional + type.
        $score = 1;

        if ($rule->professional_id !== null) {
            $score += 3;
        }

        if ($isItemSpecific) {
            $score += 3;
        } elseif ($isCategorySpecific) {
            $score += 2;
        } elseif (($rule->scope ?? 'all') !== 'all') {
            $score += 1;
        }

        return $score;
    }
}
