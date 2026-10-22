<?php

namespace App\Actions\Marketing\Packages;

use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
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
