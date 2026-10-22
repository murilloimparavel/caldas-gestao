<?php

namespace App\Actions\Marketing\Packages;

use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageUsage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MutateCustomerPackageBalance
{
    /**
     * @param  array<string, int>  $serviceQuantities
     * @param  array<string, int>  $reservedServiceQuantities
     */
    public function consume(
        CustomerPackage $package,
        string $tenantId,
        string $unitId,
        array $serviceQuantities,
        int $reservedPackageQuantity = 0,
        array $reservedServiceQuantities = [],
    ): CustomerPackage {
        if ($serviceQuantities === [] || $reservedPackageQuantity < 0) {
            throw new ConflictHttpException('Package balance mutation requires valid quantities.');
        }

        /** @var CustomerPackage $lockedPackage */
        $lockedPackage = CustomerPackage::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->whereKey($package->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $sessionsToConsume = array_sum($serviceQuantities);

        if (
            $sessionsToConsume < 1
            || $lockedPackage->total_sessions < 0
            || $lockedPackage->remaining_sessions < 0
            || $lockedPackage->remaining_sessions > $lockedPackage->total_sessions
            || $lockedPackage->remaining_sessions - $reservedPackageQuantity < $sessionsToConsume
        ) {
            throw new ConflictHttpException('Package session balance is inconsistent or insufficient.');
        }

        /** @var array<string, CustomerPackageService> $serviceBalances */
        $serviceBalances = $this->lockServiceBalances($tenantId, $unitId, $lockedPackage->getKey());
        $this->assertAggregateMatchesServiceBalances($lockedPackage, $serviceBalances);
        $this->assertServiceLedgerMatchesBalances(
            $lockedPackage,
            $serviceBalances,
            $this->loadPackageUsages($tenantId, $unitId, $lockedPackage->getKey()),
        );

        foreach ($serviceQuantities as $serviceId => $serviceQuantity) {
            if ($serviceId === '' || $serviceQuantity < 1) {
                throw new ConflictHttpException('Package service balance mutation requires valid quantities.');
            }

            $serviceBalance = $serviceBalances[$serviceId] ?? null;

            if ($serviceBalance === null) {
                throw new ConflictHttpException('Package service balance is missing.');
            }

            $reservedServiceQuantity = (int) ($reservedServiceQuantities[$serviceId] ?? 0);
            $allocatedQuantity = filter_var($serviceBalance->getRawOriginal('allocated_quantity'), FILTER_VALIDATE_INT);
            $remainingQuantity = filter_var($serviceBalance->getRawOriginal('remaining_quantity'), FILTER_VALIDATE_INT);
            $availableServiceQuantity = is_int($remainingQuantity)
                ? $remainingQuantity - $reservedServiceQuantity
                : 0;

            if (
                $reservedServiceQuantity < 0
                || ! is_int($allocatedQuantity)
                || ! is_int($remainingQuantity)
                || $allocatedQuantity < 0
                || $remainingQuantity < 0
                || $remainingQuantity > $allocatedQuantity
                || $availableServiceQuantity < $serviceQuantity
            ) {
                throw new ConflictHttpException('Package service balance is inconsistent or insufficient.');
            }

            $serviceBalances[$serviceId] = $serviceBalance;
        }

        foreach ($serviceQuantities as $serviceId => $serviceQuantity) {
            CustomerPackageService::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('customer_package_id', $lockedPackage->getKey())
                ->where('service_id', $serviceId)
                ->update([
                    'remaining_quantity' => (int) $serviceBalances[$serviceId]->remaining_quantity - $serviceQuantity,
                ]);
        }

        $newRemaining = $lockedPackage->remaining_sessions - $sessionsToConsume;
        $lockedPackage->forceFill([
            'remaining_sessions' => $newRemaining,
            'status' => $newRemaining === 0 ? 'completed' : $lockedPackage->status,
            'lock_version' => $lockedPackage->lock_version + 1,
        ])->save();

        return $lockedPackage;
    }

    public function restore(
        CustomerPackage $package,
        string $tenantId,
        string $unitId,
        ?string $serviceId,
        int $sessionsToRestore,
        ?string $status = null,
        ?string $usageIdBeingReversed = null,
    ): CustomerPackage {
        if ($sessionsToRestore < 1) {
            throw new ConflictHttpException('Package usage must contain at least one session.');
        }

        /** @var CustomerPackage $lockedPackage */
        $lockedPackage = CustomerPackage::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->whereKey($package->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $newRemaining = $lockedPackage->remaining_sessions + $sessionsToRestore;

        if (
            $lockedPackage->total_sessions < 0
            || $lockedPackage->remaining_sessions < 0
            || $lockedPackage->remaining_sessions > $lockedPackage->total_sessions
            || $newRemaining > $lockedPackage->total_sessions
        ) {
            throw new ConflictHttpException('Package session balance is inconsistent.');
        }

        $serviceBalances = $this->lockServiceBalances($tenantId, $unitId, $lockedPackage->getKey());
        if ($serviceBalances !== []) {
            $this->assertAggregateMatchesServiceBalances($lockedPackage, $serviceBalances);
        }

        $usages = $this->loadPackageUsages($tenantId, $unitId, $lockedPackage->getKey());

        $serviceBalance = null;
        $remainingQuantity = null;

        if ($serviceId !== null && $serviceId !== '') {
            $serviceBalance = $serviceBalances[$serviceId] ?? null;

            if ($serviceBalance === null) {
                throw new ConflictHttpException('Package service balance is missing.');
            }

            $allocatedQuantity = filter_var($serviceBalance->getRawOriginal('allocated_quantity'), FILTER_VALIDATE_INT);
            $remainingQuantity = filter_var($serviceBalance->getRawOriginal('remaining_quantity'), FILTER_VALIDATE_INT);
            $restoredServiceQuantity = is_int($remainingQuantity)
                ? $remainingQuantity + $sessionsToRestore
                : 0;

            if (
                ! is_int($allocatedQuantity)
                || ! is_int($remainingQuantity)
                || $allocatedQuantity < 0
                || $remainingQuantity < 0
                || $remainingQuantity > $allocatedQuantity
                || $restoredServiceQuantity > $allocatedQuantity
            ) {
                throw new ConflictHttpException('Package service balance is missing or inconsistent.');
            }
        } elseif ($serviceBalances !== []) {
            throw new ConflictHttpException('Package usage has no matching service balance.');
        }

        $this->assertServiceLedgerMatchesBalances(
            $lockedPackage,
            $serviceBalances,
            $usages,
            $usageIdBeingReversed,
            $serviceId,
            $sessionsToRestore,
        );

        if ($serviceBalance !== null) {
            CustomerPackageService::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('customer_package_id', $lockedPackage->getKey())
                ->where('service_id', $serviceId)
                ->update([
                    'remaining_quantity' => $remainingQuantity + $sessionsToRestore,
                ]);
        }

        $lockedPackage->forceFill([
            'remaining_sessions' => $newRemaining,
            'status' => $status ?? $lockedPackage->status,
            'lock_version' => $lockedPackage->lock_version + 1,
        ])->save();

        return $lockedPackage;
    }

    /**
     * The package row is locked by the caller before this read, serializing usage
     * writers that follow the same package-first locking convention.
     *
     * @return list<PackageUsage>
     */
    private function loadPackageUsages(string $tenantId, string $unitId, string $packageId): array
    {
        return PackageUsage::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('customer_package_id', $packageId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, CustomerPackageService>  $serviceBalances
     * @param  list<PackageUsage>  $usages
     */
    private function assertServiceLedgerMatchesBalances(
        CustomerPackage $package,
        array $serviceBalances,
        array $usages,
        ?string $usageIdBeingReversed = null,
        ?string $restoreServiceId = null,
        int $sessionsToRestore = 0,
    ): void {
        $allocations = $this->snapshotAllocations($package);

        $activeUsageByService = [];
        $excludedUsageFound = false;
        foreach ($usages as $usage) {
            if ($usageIdBeingReversed !== null && $usage->getKey() === $usageIdBeingReversed) {
                $excludedUsageFound = true;

                continue;
            }

            if ($usage->reversed_at !== null) {
                continue;
            }

            $serviceId = (string) ($usage->service_id ?? '');
            if ($serviceId === '' || ! array_key_exists($serviceId, $allocations)) {
                throw new ConflictHttpException('Package usage history does not match its service allocation.');
            }

            $activeUsageByService[$serviceId] = ($activeUsageByService[$serviceId] ?? 0) + (int) $usage->sessions_consumed;
        }

        if ($usageIdBeingReversed !== null && ! $excludedUsageFound) {
            throw new ConflictHttpException('Package usage to reverse was not found in its balance ledger.');
        }

        $expectedRemaining = 0;
        foreach ($allocations as $serviceId => $allocatedQuantity) {
            $expectedServiceRemaining = $allocatedQuantity - ($activeUsageByService[$serviceId] ?? 0);
            if ($expectedServiceRemaining < 0) {
                throw new ConflictHttpException('Package usage history exceeds its service allocation.');
            }

            $expectedRemaining += $expectedServiceRemaining;
            $serviceBalance = $serviceBalances[$serviceId] ?? null;
            $storedRemaining = $serviceBalance === null
                ? null
                : filter_var($serviceBalance->getRawOriginal('remaining_quantity'), FILTER_VALIDATE_INT);
            $projectedRemaining = $serviceId === $restoreServiceId && $sessionsToRestore > 0
                ? (is_int($storedRemaining) ? $storedRemaining + $sessionsToRestore : null)
                : $storedRemaining;

            if (! is_int($projectedRemaining) || $projectedRemaining !== $expectedServiceRemaining) {
                throw new ConflictHttpException('Package service balance does not match its snapshot and usage history.');
            }
        }

        if (count($serviceBalances) !== count($allocations)) {
            throw new ConflictHttpException('Package service balance does not match its service allocation.');
        }

        $projectedPackageRemaining = (int) $package->remaining_sessions + $sessionsToRestore;
        if ($projectedPackageRemaining !== $expectedRemaining) {
            throw new ConflictHttpException('Package balance does not match its usage history.');
        }
    }

    /** @return array<string, int> */
    private function snapshotAllocations(CustomerPackage $package): array
    {
        $snapshot = $package->eligible_services_snapshot;
        if (! is_array($snapshot) || $snapshot === []) {
            throw new ConflictHttpException('Package service allocation snapshot is missing.');
        }

        $allocations = [];
        foreach ($snapshot as $service) {
            if (! is_array($service) || ! array_key_exists('id', $service) || ! array_key_exists('quantity', $service)) {
                throw new ConflictHttpException('Package service allocation snapshot is invalid.');
            }

            $serviceId = (string) $service['id'];
            $quantity = filter_var($service['quantity'], FILTER_VALIDATE_INT);
            if ($serviceId === '' || ! is_int($quantity) || $quantity < 1 || array_key_exists($serviceId, $allocations)) {
                throw new ConflictHttpException('Package service allocation snapshot is invalid.');
            }

            $allocations[$serviceId] = $quantity;
        }

        if (array_sum($allocations) !== (int) $package->total_sessions) {
            throw new ConflictHttpException('Package service allocation snapshot does not match the package total.');
        }

        return $allocations;
    }

    /** @return array<string, CustomerPackageService> */
    private function lockServiceBalances(string $tenantId, string $unitId, string $packageId): array
    {
        return CustomerPackageService::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('customer_package_id', $packageId)
            ->orderBy('service_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('service_id')
            ->all();
    }

    /** @param array<string, CustomerPackageService> $serviceBalances */
    private function assertAggregateMatchesServiceBalances(CustomerPackage $package, array $serviceBalances): void
    {
        $allocatedTotal = 0;
        $remainingTotal = 0;

        foreach ($serviceBalances as $serviceBalance) {
            $allocatedQuantity = filter_var($serviceBalance->getRawOriginal('allocated_quantity'), FILTER_VALIDATE_INT);
            $remainingQuantity = filter_var($serviceBalance->getRawOriginal('remaining_quantity'), FILTER_VALIDATE_INT);

            if (! is_int($allocatedQuantity) || ! is_int($remainingQuantity) || $allocatedQuantity < 0 || $remainingQuantity < 0 || $remainingQuantity > $allocatedQuantity) {
                throw new ConflictHttpException('Package service balance is inconsistent or insufficient.');
            }

            $allocatedTotal += $allocatedQuantity;
            $remainingTotal += $remainingQuantity;
        }

        if ($allocatedTotal !== (int) $package->total_sessions || $remainingTotal !== (int) $package->remaining_sessions) {
            throw new ConflictHttpException('Package aggregate and service balances are inconsistent.');
        }
    }
}
