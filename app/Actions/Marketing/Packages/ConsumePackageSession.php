<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ConsumePackageSession extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, CustomerPackage $customerPackage, array $data = []): CustomerPackage
    {
        $unit = $this->unit($actor, $context, 'package.consume');

        if ($customerPackage->tenant_id !== $context->tenant->getKey() || $customerPackage->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The customer package belongs to another workspace.');
        }

        $sessionsToConsume = isset($data['sessions_consumed']) ? (int) $data['sessions_consumed'] : 1;
        if ($sessionsToConsume <= 0) {
            throw new \InvalidArgumentException('Sessions to consume must be greater than zero.');
        }

        $serviceId = (string) ($data['service_id'] ?? '');

        if (! empty($data['sale_id']) || ! empty($data['sale_item_id'])) {
            throw new \InvalidArgumentException('Comanda vinculada deve consumir o pacote apenas pelo fechamento da comanda.');
        }

        if ($serviceId === '') {
            throw new \InvalidArgumentException('A service must be selected when consuming package sessions.');
        }

        $package = DB::transaction(function () use ($actor, $context, $unit, $customerPackage, $sessionsToConsume, $serviceId): ?CustomerPackage {
            /** @var CustomerPackage $locked */
            $locked = CustomerPackage::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($customerPackage->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'active') {
                throw new ConflictHttpException("Package is not active (current status: {$locked->status}).");
            }

            if ($locked->expires_at !== null && $locked->expires_at->endOfDay()->isPast()) {
                $locked->forceFill([
                    'status' => 'expired',
                    'lock_version' => $locked->lock_version + 1,
                ])->save();

                $this->events->record($actor, $context, 'customer_package.expired', $locked, [
                    'customer_package_id' => $locked->getKey(),
                    'remaining_sessions' => $locked->remaining_sessions,
                    'expires_at' => $locked->expires_at?->toDateString(),
                    'status' => 'expired',
                ]);

                return null;
            }

            $eligibleServiceIds = collect($locked->eligible_services_snapshot ?? [])
                ->pluck('id')
                ->map(static fn (mixed $eligibleServiceId): string => (string) $eligibleServiceId)
                ->all();

            if (! in_array($serviceId, $eligibleServiceIds, true)) {
                throw new \InvalidArgumentException('The selected service is not eligible for this package.');
            }

            $balance = CustomerPackageService::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('customer_package_id', $locked->getKey())
                ->where('service_id', $serviceId)
                ->lockForUpdate()
                ->first();

            $reservedServiceQuantity = (int) PackageUsageReservation::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('customer_package_id', $locked->getKey())
                ->where('service_id', $serviceId)
                ->where('status', 'reserved')
                ->sum('sessions_reserved');

            if ($balance === null || $balance->remaining_quantity - $reservedServiceQuantity < $sessionsToConsume) {
                throw new ConflictHttpException('Not enough sessions remaining for this service.');
            }

            CustomerPackageService::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('customer_package_id', $locked->getKey())
                ->where('service_id', $serviceId)
                ->update([
                    'remaining_quantity' => $balance->remaining_quantity - $sessionsToConsume,
                ]);

            $reservedPackageQuantity = (int) PackageUsageReservation::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('customer_package_id', $locked->getKey())
                ->where('status', 'reserved')
                ->sum('sessions_reserved');
            $availableSessions = $locked->remaining_sessions - $reservedPackageQuantity;

            if ($availableSessions < $sessionsToConsume) {
                throw new ConflictHttpException("Not enough sessions remaining. Available: {$availableSessions}, requested: {$sessionsToConsume}.");
            }

            $newRemaining = $locked->remaining_sessions - $sessionsToConsume;
            $newStatus = $newRemaining === 0 ? 'completed' : 'active';

            $locked->forceFill([
                'remaining_sessions' => $newRemaining,
                'status' => $newStatus,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $usage = PackageUsage::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'customer_package_id' => $locked->getKey(),
                'service_id' => $serviceId,
                'sale_id' => null,
                'sale_item_id' => null,
                'sessions_consumed' => $sessionsToConsume,
                'user_id' => $actor->getKey(),
            ]);

            $this->events->record($actor, $context, 'customer_package.consumed', $locked, [
                'customer_package_id' => $locked->getKey(),
                'package_usage_id' => $usage->getKey(),
                'sessions_consumed' => $sessionsToConsume,
                'remaining_sessions' => $newRemaining,
                'status' => $newStatus,
                'sale_id' => null,
                'sale_item_id' => null,
            ]);

            return $locked->fresh()->load(['packageTemplate.services', 'customer', 'usages.user']);
        }, 5);

        if ($package === null) {
            throw new ConflictHttpException('Package has expired.');
        }

        return $package;
    }
}
