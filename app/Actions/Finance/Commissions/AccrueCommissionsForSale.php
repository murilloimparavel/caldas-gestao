<?php

namespace App\Actions\Finance\Commissions;

use App\Actions\Operational\OperationalAction;
use App\Models\CommissionAccrual;
use App\Models\CommissionRule;
use App\Models\PackageTemplate;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
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
        $sale->unsetRelation('items')->load([
            'items.service:id,category_id,name',
            'items.product:id,category_id',
            'items.packageTemplate.services:id,name,price_cents,category_id',
            'items.customerPackage',
        ]);

        /** @var Collection<int, CommissionAccrual> $existingAccruals */
        $existingAccruals = CommissionAccrual::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('sale_id', $sale->getKey())
            ->get();

        foreach ($sale->items as $item) {
            if ($item->item_type === 'package') {
                $this->accruePackageServices($actor, $context, $sale, $item, $activeRules, $existingAccruals, $accruals);

                continue;
            }

            $alreadyAccrued = $existingAccruals->contains(fn (CommissionAccrual $accrual): bool => $accrual->sale_item_id === $item->getKey());
            if ($alreadyAccrued) {
                continue;
            }

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

            $commissionableQuantity = max(0, $item->quantity - $item->covered_quantity);
            $commissionBaseAmountCents = max(0, $item->total_cents);
            $accrual = $this->createAccrual(
                $actor,
                $context,
                $sale,
                $item,
                $matchedRule,
                $commissionProfessionalId,
                $commissionBaseAmountCents,
                $item->quantity,
                $item->covered_quantity,
                $commissionableQuantity,
                [
                    'source_type' => 'sale_item',
                    'service_id' => $item->service_id,
                    'service_name_snapshot' => $item->service_id !== null ? $item->name_snapshot : null,
                    'package_template_id' => null,
                    'customer_package_id' => null,
                    'package_name_snapshot' => null,
                ],
                $this->calculateCommission($matchedRule, $commissionBaseAmountCents, $commissionableQuantity),
            );
            $existingAccruals->push($accrual);
            $accruals->push($accrual);
        }

        return $accruals;
    }

    /**
     * @param  Collection<int, CommissionRule>  $activeRules
     * @param  Collection<int, CommissionAccrual>  $existingAccruals
     * @param  Collection<int, CommissionAccrual>  $accruals
     */
    private function accruePackageServices(
        User $actor,
        TenantContext $context,
        Sale $sale,
        SaleItem $item,
        Collection $activeRules,
        Collection $existingAccruals,
        Collection $accruals,
    ): void {
        $professionalId = $item->professional_id;
        if ($professionalId === null) {
            return;
        }

        $definitions = $this->packageServiceDefinitions($item);
        if ($definitions === []) {
            return;
        }

        $hasPositivePrice = collect($definitions)->contains(
            static fn (array $definition): bool => $definition['price_cents'] > 0,
        );
        $weightForDefinition = static function (array $definition) use ($hasPositivePrice): int {
            if ($hasPositivePrice) {
                return $definition['price_cents'] > 0
                    ? $definition['price_cents'] * $definition['quantity']
                    : 0;
            }

            return $definition['quantity'];
        };
        $weights = array_map($weightForDefinition, $definitions);
        $totalWeight = array_sum($weights);
        $lastWeightedIndex = collect($weights)
            ->filter(static fn (int $weight): bool => $weight > 0)
            ->keys()
            ->last();

        $packageAmount = max(0, $item->total_cents);
        $remainingAmount = $packageAmount;

        foreach ($definitions as $index => $definition) {
            /** @var Service $service */
            $service = $definition['service'];
            $quantity = $definition['quantity'] * max(1, $item->quantity);
            $weight = $weights[$index];
            $allocatedAmount = $index === $lastWeightedIndex
                ? $remainingAmount
                : ($weight > 0 ? (int) floor(($packageAmount * $weight) / $totalWeight) : 0);
            $allocatedAmount = max(0, $allocatedAmount);
            $remainingAmount = max(0, $remainingAmount - $allocatedAmount);

            $alreadyAccrued = $existingAccruals->contains(fn (CommissionAccrual $accrual): bool => $accrual->source_type === 'package_service'
                && $accrual->sale_item_id === $item->getKey()
                && $accrual->service_id === $service->getKey());
            if ($alreadyAccrued) {
                continue;
            }

            $matchedRule = $this->findBestMatchingServiceRule($activeRules, $service, $professionalId);
            if ($matchedRule === null) {
                continue;
            }

            $accrual = $this->createAccrual(
                $actor,
                $context,
                $sale,
                $item,
                $matchedRule,
                $professionalId,
                $allocatedAmount,
                $quantity,
                0,
                $quantity,
                [
                    'source_type' => 'package_service',
                    'service_id' => $service->getKey(),
                    'service_name_snapshot' => $definition['name_snapshot'],
                    'package_template_id' => $item->package_template_id,
                    'customer_package_id' => $item->customer_package_id,
                    'package_name_snapshot' => $item->name_snapshot,
                ],
                $this->calculateCommission($matchedRule, $allocatedAmount, $quantity),
                $definition['name_snapshot'],
            );
            $existingAccruals->push($accrual);
            $accruals->push($accrual);
        }
    }

    /**
     * @return list<array{service: Service, quantity: int, price_cents: int, name_snapshot: string}>
     */
    private function packageServiceDefinitions(SaleItem $item): array
    {
        $packageTemplate = $item->packageTemplate;
        if ($packageTemplate === null && $item->package_template_id !== null) {
            $packageTemplate = PackageTemplate::withTrashed()
                ->whereKey($item->package_template_id)
                ->where('tenant_id', $item->tenant_id)
                ->where('unit_id', $item->unit_id)
                ->with('services:id,name,price_cents,category_id')
                ->first();
        }

        $snapshot = $item->customerPackage?->eligible_services_snapshot ?? [];
        if ($snapshot !== []) {
            $serviceIds = collect($snapshot)->pluck('id')->filter()->values();
            $services = Service::query()
                ->where('tenant_id', $item->tenant_id)
                ->where('unit_id', $item->unit_id)
                ->whereIn('id', $serviceIds)
                ->get()
                ->keyBy('id');

            return collect($snapshot)
                ->map(function (array $definition) use ($services): ?array {
                    $service = $services->get((string) ($definition['id'] ?? ''));
                    if (! $service instanceof Service) {
                        return null;
                    }

                    return [
                        'service' => $service,
                        'quantity' => max(1, (int) ($definition['quantity'] ?? 1)),
                        'price_cents' => max(0, (int) ($definition['price_cents'] ?? $service->price_cents)),
                        'name_snapshot' => trim((string) ($definition['name'] ?? $service->name)) ?: $service->name,
                    ];
                })
                ->filter()
                ->groupBy(static fn (array $definition): string => $definition['service']->getKey())
                ->map(static function (SupportCollection $definitions): array {
                    $first = $definitions->first();

                    return [
                        'service' => $first['service'],
                        'quantity' => (int) $definitions->sum('quantity'),
                        'price_cents' => $first['price_cents'],
                        'name_snapshot' => $first['name_snapshot'],
                    ];
                })
                ->values()
                ->all();
        }

        if ($packageTemplate === null || ! $packageTemplate->relationLoaded('services') || $packageTemplate->services->isEmpty()) {
            return [];
        }

        return $packageTemplate->services->map(static fn (Service $service): array => [
            'service' => $service,
            'quantity' => max(1, (int) ($service->pivot->included_quantity ?? 1)),
            'price_cents' => max(0, (int) $service->price_cents),
            'name_snapshot' => $service->name,
        ])->values()->all();
    }

    private function calculateCommission(CommissionRule $rule, int $baseAmountCents, int $quantity): int
    {
        return match ($rule->type) {
            'percentage' => (int) round(($baseAmountCents * $rule->value_rate) / 100),
            'fixed' => (int) ($rule->value_rate * $quantity),
            default => 0,
        };
    }

    /** @param  array<string, mixed>  $source */
    private function createAccrual(
        User $actor,
        TenantContext $context,
        Sale $sale,
        SaleItem $item,
        CommissionRule $matchedRule,
        string $professionalId,
        int $baseAmountCents,
        int $quantity,
        int $coveredQuantity,
        int $commissionableQuantity,
        array $source,
        int $commissionAmountCents,
        ?string $itemName = null,
    ): CommissionAccrual {
        $accrual = CommissionAccrual::query()->create([
            'id' => (string) Str::uuid7(),
            'tenant_id' => $context->tenant->getKey(),
            'unit_id' => $sale->unit_id,
            'professional_id' => $professionalId,
            'sale_id' => $sale->getKey(),
            'sale_item_id' => $item->getKey(),
            'source_type' => $source['source_type'],
            'service_id' => $source['service_id'],
            'package_template_id' => $source['package_template_id'],
            'customer_package_id' => $source['customer_package_id'],
            'item_name_snapshot' => $itemName ?? $item->name_snapshot,
            'service_name_snapshot' => $source['service_name_snapshot'],
            'package_name_snapshot' => $source['package_name_snapshot'],
            'gross_amount_cents' => $baseAmountCents,
            'quantity' => $quantity,
            'covered_quantity' => $coveredQuantity,
            'commissionable_quantity' => $commissionableQuantity,
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
            'source_type' => $accrual->source_type,
            'service_id' => $accrual->service_id,
            'package_template_id' => $accrual->package_template_id,
            'customer_package_id' => $accrual->customer_package_id,
            'gross_amount_cents' => $accrual->gross_amount_cents,
            'quantity' => $accrual->quantity,
            'covered_quantity' => $accrual->covered_quantity,
            'commissionable_quantity' => $accrual->commissionable_quantity,
            'rate_type' => $accrual->rate_type,
            'rate_value' => $accrual->rate_value,
            'commission_amount_cents' => $accrual->commission_amount_cents,
            'status' => $accrual->status,
            'lock_version' => $accrual->lock_version,
        ]);

        return $accrual;
    }

    /** @param  Collection<int, CommissionRule>  $rules */
    private function findBestMatchingRule(Collection $rules, SaleItem $item, string $professionalId): ?CommissionRule
    {
        return $this->findBestMatchingRuleForFields(
            $rules,
            $item->service_id,
            $item->product_id,
            $item->service_id !== null ? $item->service?->category_id : $item->product?->category_id,
            $professionalId,
        );
    }

    /** @param  Collection<int, CommissionRule>  $rules */
    private function findBestMatchingServiceRule(Collection $rules, Service $service, string $professionalId): ?CommissionRule
    {
        return $this->findBestMatchingRuleForFields($rules, $service->getKey(), null, $service->category_id, $professionalId);
    }

    /** @param  Collection<int, CommissionRule>  $rules */
    private function findBestMatchingRuleForFields(
        Collection $rules,
        ?string $serviceId,
        ?string $productId,
        ?string $categoryId,
        string $professionalId,
    ): ?CommissionRule {
        $bestRule = null;
        $highestScore = -1;
        foreach ($rules as $rule) {
            $score = $this->calculateRuleMatchScore($rule, $serviceId, $productId, $categoryId, $professionalId);
            if ($score !== null && $score > $highestScore) {
                $highestScore = $score;
                $bestRule = $rule;
            }
        }

        return $bestRule;
    }

    private function calculateRuleMatchScore(
        CommissionRule $rule,
        ?string $serviceId,
        ?string $productId,
        ?string $categoryId,
        string $professionalId,
    ): ?int {
        if ($rule->professional_id !== null && $rule->professional_id !== $professionalId) {
            return null;
        }

        $serviceMatches = $serviceId !== null
            && $rule->scope === 'service'
            && ($rule->service_id === null || $rule->service_id === $serviceId);
        $productMatches = $productId !== null
            && $rule->scope === 'product'
            && ($rule->product_id === null || $rule->product_id === $productId);
        $categoryMatches = $categoryId !== null
            && $rule->category_id === $categoryId
            && (($serviceId !== null && $rule->scope === 'service_category')
                || ($productId !== null && $rule->scope === 'product_category'));
        $isItemSpecific = ($serviceMatches && $rule->service_id !== null)
            || ($productMatches && $rule->product_id !== null);
        $isCategorySpecific = $categoryMatches;
        $isRuleItemGeneric = $rule->scope === 'all'
            || ($rule->scope === 'service' && $rule->service_id === null && $serviceId !== null)
            || ($rule->scope === 'product' && $rule->product_id === null && $productId !== null);

        if (! $isItemSpecific && ! $isCategorySpecific && ! $isRuleItemGeneric) {
            return null;
        }

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
