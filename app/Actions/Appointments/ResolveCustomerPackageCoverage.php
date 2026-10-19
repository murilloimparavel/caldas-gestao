<?php

namespace App\Actions\Appointments;

use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageUsageReservation;
use App\Support\LegacyPackageReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

final class ResolveCustomerPackageCoverage
{
    public function __construct(
        private readonly LegacyPackageReconciliation $packageEvidence = new LegacyPackageReconciliation,
    ) {}

    /**
     * @param  array<string, array{total?: int, services?: array<string, int>}>  $allocatedCounts  Contador em memória de sessões alocadas no mesmo agendamento [customer_package_id => ['total' => int, 'services' => [service_id => int]]]
     */
    public function resolve(string $tenantId, string $unitId, string $customerId, string $serviceId, array &$allocatedCounts = []): ?CustomerPackage
    {
        $today = CarbonImmutable::now()->toDateString();

        /** @var Collection<int, CustomerPackage> $packages */
        $packages = CustomerPackage::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->where(function ($query) use ($today): void {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->where('remaining_sessions', '>', 0)
            ->orderByRaw('expires_at IS NULL, expires_at ASC')
            ->with(['serviceBalances', 'sale.closingSessions.payments', 'financialObligation'])
            ->get();

        $financialLinkAvailable = Schema::hasColumn('financial_obligations', 'customer_package_id');

        foreach ($packages as $package) {
            $linkedItems = $package->sale !== null
                ? $package->sale->items()->where('customer_package_id', $package->getKey())->with('sale.closingSessions.payments')->get()
                : collect();

            if (! $this->packageEvidence->hasStrictPaidSaleEvidence($package, $linkedItems, $financialLinkAvailable)) {
                continue;
            }

            $eligibleServiceIds = collect($package->eligible_services_snapshot ?? [])
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();

            if (! in_array($serviceId, $eligibleServiceIds, true)) {
                continue;
            }

            /** @var CustomerPackageService|null $serviceBalance */
            $serviceBalance = $package->serviceBalances->firstWhere('service_id', $serviceId);
            $serviceRemaining = $serviceBalance !== null ? (int) $serviceBalance->remaining_quantity : 0;

            $reservedServiceQuantity = (int) PackageUsageReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('customer_package_id', $package->getKey())
                ->where('service_id', $serviceId)
                ->where('status', 'reserved')
                ->sum('sessions_reserved');

            $reservedPackageQuantity = (int) PackageUsageReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('customer_package_id', $package->getKey())
                ->where('status', 'reserved')
                ->sum('sessions_reserved');

            $alreadyAllocatedPackage = (int) ($allocatedCounts[$package->getKey()]['total'] ?? 0);
            $alreadyAllocatedService = (int) ($allocatedCounts[$package->getKey()]['services'][$serviceId] ?? 0);

            $availableForService = max(0, $serviceRemaining - $reservedServiceQuantity - $alreadyAllocatedService);
            $availableForPackage = max(0, $package->remaining_sessions - $reservedPackageQuantity - $alreadyAllocatedPackage);

            if ($availableForService > 0 && $availableForPackage > 0) {
                $allocatedCounts[$package->getKey()]['total'] = $alreadyAllocatedPackage + 1;
                $allocatedCounts[$package->getKey()]['services'][$serviceId] = $alreadyAllocatedService + 1;

                return $package;
            }
        }

        return null;
    }
}
