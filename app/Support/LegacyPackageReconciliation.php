<?php

namespace App\Support;

use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;

final class LegacyPackageReconciliation
{
    /**
     * @param  Collection<int, SaleItem>  $linkedItems
     * @return array{target_status: 'active'|'pending'|'review_required', reasons: list<string>}
     */
    public function assess(CustomerPackage $package, Collection $linkedItems, bool $financialLinkAvailable): array
    {
        $reasons = [];
        $financialObligation = $financialLinkAvailable ? $package->financialObligation : null;
        $hasFinancialObligation = $financialObligation !== null;
        $usageIsConsistent = $this->usageBalancesAreConsistent($package);
        $reservationsAreConsistent = $this->reservationsAreConsistent($package, $linkedItems);
        $hasLegacyNullPackageLinks = $this->hasLegacyNullPackageLinks($package, $linkedItems);
        $sale = $package->sale;
        $packageItems = $linkedItems->where('item_type', 'package')->values();
        $saleLineIsCongruent = $this->hasOneCongruentPackageSaleLine($package, $sale, $packageItems);
        $hasPaidClosing = $this->hasStrictPaidSaleEvidence($package, $linkedItems, $financialLinkAvailable);

        if (! $financialLinkAvailable) {
            $reasons[] = 'financial_obligation_link_unavailable';
        }

        if ($hasFinancialObligation) {
            $reasons[] = 'linked_financial_obligation_exists';
        }

        if (! $usageIsConsistent) {
            $reasons[] = 'usage_or_service_balance_mismatch';
        }

        if (! $reservationsAreConsistent) {
            $reasons[] = 'reservation_mismatch';
        }

        $hasPendingReservations = $package->reservations->contains(
            static fn (PackageUsageReservation $reservation): bool => $reservation->status === 'reserved',
        );
        if ($hasPendingReservations) {
            $reasons[] = 'pending_package_reservation';
        }

        if ($package->sale_id !== null && ! $saleLineIsCongruent) {
            $reasons[] = 'sale_or_package_item_mismatch';
        }

        if ($hasLegacyNullPackageLinks) {
            $reasons[] = 'legacy_sale_item_package_link_missing';
        }

        if ($hasPaidClosing && $financialLinkAvailable && ! $hasFinancialObligation && $usageIsConsistent
            && $reservationsAreConsistent && ! $hasPendingReservations) {
            return ['target_status' => 'active', 'reasons' => $hasLegacyNullPackageLinks ? ['legacy_sale_item_package_link_missing'] : []];
        }

        $hasUsage = $package->usages->isNotEmpty();
        $hasReservations = $package->reservations->isNotEmpty();

        if ($financialLinkAvailable && ! $hasFinancialObligation && ! $hasUsage && ! $hasReservations
            && $usageIsConsistent && $package->sale_id === null && $linkedItems->isEmpty()) {
            return ['target_status' => 'pending', 'reasons' => array_values(array_unique([...$reasons, 'no_sale_or_usage_evidence']))];
        }

        if ($financialLinkAvailable && ! $hasFinancialObligation && ! $hasUsage && ! $hasReservations
            && $usageIsConsistent && $reservationsAreConsistent && $saleLineIsCongruent
            && $linkedItems->count() === 1 && in_array($sale?->status, ['open', 'ready_to_bill'], true)
            && $sale->closingSessions->isEmpty()) {
            return ['target_status' => 'pending', 'reasons' => array_values(array_unique([...$reasons, 'sale_open_without_payment_evidence']))];
        }

        if ($package->sale_id === null && ! $hasUsage && ! $hasReservations && $linkedItems->isNotEmpty()) {
            $reasons[] = 'unlinked_sale_items_exist';
        }

        if ($sale !== null && ! $this->saleScopeMatchesPackage($package, $sale)) {
            $reasons[] = 'sale_customer_or_unit_mismatch';
        }

        if ($sale !== null && ! in_array($sale->status, ['open', 'ready_to_bill', 'finalized'], true)) {
            $reasons[] = 'sale_not_recoverable';
        }

        if (! $hasPaidClosing) {
            $reasons[] = 'completed_paid_closing_not_proven';
        }

        return [
            'target_status' => 'review_required',
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /** @param Collection<int, SaleItem> $linkedItems */
    public function hasStrictPaidSaleEvidence(CustomerPackage $package, Collection $linkedItems, bool $financialLinkAvailable): bool
    {
        if (! $financialLinkAvailable) {
            return false;
        }

        $package->loadMissing(['sale.closingSessions.payments', 'financialObligation']);

        return $package->financialObligation === null
            && $this->hasOneCongruentPackageSaleLine(
                $package,
                $package->sale,
                $linkedItems->where('item_type', 'package')->values(),
            )
            && $this->hasCompletedPaidClosing($package->sale);
    }

    private function usageBalancesAreConsistent(CustomerPackage $package): bool
    {
        if ($package->total_sessions <= 0 || $package->remaining_sessions < 0 || $package->remaining_sessions > $package->total_sessions) {
            return false;
        }

        $activeUsages = $package->usages->filter(static fn (PackageUsage $usage): bool => $usage->reversed_at === null);
        $consumedSessions = (int) $activeUsages->sum('sessions_consumed');

        if ($consumedSessions !== $package->total_sessions - $package->remaining_sessions) {
            return false;
        }

        $snapshot = collect($this->snapshotEntries($package));
        if ($snapshot->isEmpty()) {
            return false;
        }

        /** @var array<string, int> $allocations */
        $allocations = [];
        foreach ($snapshot as $service) {
            if (! is_array($service)) {
                return false;
            }

            $serviceId = (string) ($service['id'] ?? '');
            $quantity = (int) ($service['quantity'] ?? 0);

            if ($serviceId === '' || $quantity <= 0 || isset($allocations[$serviceId])) {
                return false;
            }

            $allocations[$serviceId] = $quantity;
        }

        if (array_sum($allocations) !== (int) $package->total_sessions) {
            return false;
        }

        $balances = $package->serviceBalances->keyBy('service_id');
        if ($balances->count() !== count($allocations)) {
            return false;
        }

        foreach ($allocations as $serviceId => $allocatedQuantity) {
            $balance = $balances->get($serviceId);
            $usedQuantity = (int) $activeUsages->where('service_id', $serviceId)->sum('sessions_consumed');

            if ($balance === null || $balance->allocated_quantity !== $allocatedQuantity
                || $balance->remaining_quantity !== $allocatedQuantity - $usedQuantity || $usedQuantity > $allocatedQuantity) {
                return false;
            }
        }

        foreach ($activeUsages as $usage) {
            if ($usage->tenant_id !== $package->tenant_id || $usage->unit_id !== $package->unit_id
                || $usage->customer_package_id !== $package->getKey() || $usage->service_id === null
                || ! isset($allocations[$usage->service_id])) {
                return false;
            }
        }

        return true;
    }

    /** @param Collection<int, SaleItem> $linkedItems */
    private function reservationsAreConsistent(CustomerPackage $package, Collection $linkedItems): bool
    {
        $usages = $package->usages->keyBy('id');
        $eligibleServiceIds = collect($this->snapshotEntries($package))
            ->map(static fn (mixed $service): string => is_array($service) ? (string) ($service['id'] ?? '') : '')
            ->filter()
            ->all();
        $reservedSessions = 0;
        $reservedByService = [];

        foreach ($package->reservations as $reservation) {
            if ($reservation->tenant_id !== $package->tenant_id || $reservation->unit_id !== $package->unit_id
                || $reservation->customer_package_id !== $package->getKey() || $reservation->sessions_reserved <= 0
                || ! in_array($reservation->service_id, $eligibleServiceIds, true)) {
                return false;
            }

            if ($reservation->status === 'reserved') {
                $reservedSessions += $reservation->sessions_reserved;
                $reservedByService[$reservation->service_id] = ($reservedByService[$reservation->service_id] ?? 0) + $reservation->sessions_reserved;
                $item = $linkedItems->firstWhere('id', $reservation->sale_item_id);
                $sale = $item?->getRelation('sale');

                if ($item === null || $item->item_type !== 'service' || $item->sale_id !== $reservation->sale_id
                    || ! $this->saleItemPackageLinkMatches($package, $item) || $item->service_id !== $reservation->service_id
                    || $item->tenant_id !== $package->tenant_id || $item->unit_id !== $package->unit_id
                    || $item->covered_quantity !== $reservation->sessions_reserved
                    || ! ($sale instanceof Sale) || ! $this->saleScopeMatchesPackage($package, $sale)
                    || ! in_array($sale->status, ['open', 'ready_to_bill'], true)) {
                    return false;
                }
            } elseif ($reservation->status === 'consumed') {
                $usage = $usages->get($reservation->package_usage_id);
                $item = $linkedItems->firstWhere('id', $reservation->sale_item_id);
                $sale = $item?->getRelation('sale');

                if ($usage === null || $usage->reversed_at !== null || $usage->sale_id !== $reservation->sale_id
                    || $usage->sale_item_id !== $reservation->sale_item_id || $usage->service_id !== $reservation->service_id
                    || $usage->sessions_consumed !== $reservation->sessions_reserved
                    || $usage->tenant_id !== $package->tenant_id || $usage->unit_id !== $package->unit_id
                    || $item === null || $item->item_type !== 'service' || ! $this->saleItemPackageLinkMatches($package, $item)
                    || $item->tenant_id !== $package->tenant_id || $item->unit_id !== $package->unit_id
                    || $item->sale_id !== $reservation->sale_id || $item->service_id !== $reservation->service_id
                    || $item->covered_quantity !== $reservation->sessions_reserved
                    || ! ($sale instanceof Sale) || ! $this->saleScopeMatchesPackage($package, $sale) || $sale->status !== 'finalized') {
                    return false;
                }
            } elseif ($reservation->status === 'released') {
                $usage = $reservation->package_usage_id !== null ? $usages->get($reservation->package_usage_id) : null;
                $item = $linkedItems->firstWhere('id', $reservation->sale_item_id);
                $sale = $item?->getRelation('sale');
                $releasedUsageMatches = $reservation->released_at !== null
                    && $usage !== null
                    && $usage->reversed_at !== null
                    && $usage->tenant_id === $package->tenant_id
                    && $usage->unit_id === $package->unit_id
                    && $usage->customer_package_id === $package->getKey()
                    && $usage->sale_id === $reservation->sale_id
                    && $usage->sale_item_id === $reservation->sale_item_id
                    && $usage->service_id === $reservation->service_id
                    && $usage->sessions_consumed === $reservation->sessions_reserved
                    && $item !== null
                    && $item->item_type === 'service'
                    && $item->tenant_id === $package->tenant_id
                    && $item->unit_id === $package->unit_id
                    && $item->sale_id === $reservation->sale_id
                    && $item->service_id === $reservation->service_id
                    && $item->covered_quantity === $reservation->sessions_reserved
                    && $this->saleItemPackageLinkMatches($package, $item)
                    && $sale !== null
                    && $this->saleScopeMatchesPackage($package, $sale);

                if (($reservation->package_usage_id !== null && ! $releasedUsageMatches)
                    || ($reservation->package_usage_id === null && ! $this->releasedReservationEvidenceMatches($package, $reservation, $item))) {
                    return false;
                }
            } else {
                return false;
            }
        }

        foreach ($package->usages as $usage) {
            if ($usage->sale_id !== null || $usage->sale_item_id !== null) {
                $reservation = $package->reservations->first(static fn (PackageUsageReservation $item): bool => $item->package_usage_id === $usage->getKey() && $item->status === 'consumed');

                if ($usage->reversed_at === null && $reservation === null) {
                    return false;
                }
            }
        }

        if ($reservedSessions > $package->remaining_sessions) {
            return false;
        }

        foreach ($reservedByService as $serviceId => $quantity) {
            $balance = $package->serviceBalances->firstWhere('service_id', $serviceId);

            if ($balance === null || $quantity > $balance->remaining_quantity) {
                return false;
            }
        }

        return true;
    }

    /** @param Collection<int, SaleItem> $linkedItems */
    private function hasLegacyNullPackageLinks(CustomerPackage $package, Collection $linkedItems): bool
    {
        return $package->reservations->contains(static function (PackageUsageReservation $reservation) use ($linkedItems): bool {
            $item = $linkedItems->firstWhere('id', $reservation->sale_item_id);

            return $item !== null && $item->customer_package_id === null;
        });
    }

    /** @return list<mixed> */
    private function snapshotEntries(CustomerPackage $package): array
    {
        $rawSnapshot = $package->getRawOriginal('eligible_services_snapshot');

        if (is_string($rawSnapshot)) {
            $decodedSnapshot = json_decode($rawSnapshot, true);

            return is_array($decodedSnapshot) ? array_values($decodedSnapshot) : [];
        }

        if (is_array($rawSnapshot)) {
            return array_values($rawSnapshot);
        }

        $snapshot = $package->eligible_services_snapshot;

        return is_array($snapshot) ? array_values($snapshot) : [];
    }

    private function saleItemPackageLinkMatches(CustomerPackage $package, SaleItem $item): bool
    {
        return $item->customer_package_id === null || $item->customer_package_id === $package->getKey();
    }

    private function releasedReservationEvidenceMatches(CustomerPackage $package, PackageUsageReservation $reservation, ?SaleItem $item): bool
    {
        $sale = $item?->sale;

        return $reservation->released_at !== null
            && $item !== null
            && $item->item_type === 'service'
            && $item->tenant_id === $package->tenant_id
            && $item->unit_id === $package->unit_id
            && $item->sale_id === $reservation->sale_id
            && $item->service_id === $reservation->service_id
            && $item->covered_quantity === $reservation->sessions_reserved
            && $this->saleItemPackageLinkMatches($package, $item)
            && $sale !== null && $this->saleScopeMatchesPackage($package, $sale)
            && ($item->trashed() || $sale->status === 'cancelled');
    }

    /** @param Collection<int, SaleItem> $packageItems */
    private function hasOneCongruentPackageSaleLine(CustomerPackage $package, ?Sale $sale, Collection $packageItems): bool
    {
        if ($sale === null || $packageItems->count() !== 1 || ! $this->saleScopeMatchesPackage($package, $sale)) {
            return false;
        }

        $item = $packageItems->first();

        return $item !== null
            && $item->tenant_id === $package->tenant_id
            && $item->unit_id === $package->unit_id
            && $item->sale_id === $package->sale_id
            && $item->customer_package_id === $package->getKey()
            && $item->package_template_id === $package->package_template_id
            && $item->item_type === 'package'
            && $item->quantity === 1
            && $item->covered_quantity === 0
            && $item->total_cents > 0;
    }

    private function saleScopeMatchesPackage(CustomerPackage $package, Sale $sale): bool
    {
        return $sale->tenant_id === $package->tenant_id
            && $sale->unit_id === $package->unit_id
            && $sale->customer_id === $package->customer_id;
    }

    private function hasCompletedPaidClosing(?Sale $sale): bool
    {
        if ($sale === null || $sale->status !== 'finalized' || $sale->final_amount_cents <= 0) {
            return false;
        }

        $closingSessions = $sale->closingSessions;
        if ($closingSessions->count() !== 1) {
            return false;
        }

        $closingSession = $closingSessions->first();
        if ($closingSession === null || $closingSession->status !== 'completed' || $closingSession->final_total_cents < $sale->final_amount_cents) {
            return false;
        }

        $netReceived = (int) $closingSession->payments->sum(static fn ($payment): int => $payment->is_reversal
            ? -$payment->amount_cents
            : $payment->amount_cents);

        return $closingSession->payments->isNotEmpty() && $netReceived >= $closingSession->final_total_cents;
    }
}
