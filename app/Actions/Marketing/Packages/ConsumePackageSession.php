<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageUsage;
use App\Models\Sale;
use App\Models\SaleItem;
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

        $saleId = isset($data['sale_id']) && $data['sale_id'] !== '' ? (string) $data['sale_id'] : null;
        $saleItemId = isset($data['sale_item_id']) && $data['sale_item_id'] !== '' ? (string) $data['sale_item_id'] : null;
        $serviceId = isset($data['service_id']) && $data['service_id'] !== '' ? (string) $data['service_id'] : null;

        if ($saleItemId !== null && $saleId === null) {
            throw new \InvalidArgumentException('A sale item requires an associated sale.');
        }

        $package = DB::transaction(function () use ($actor, $context, $unit, $customerPackage, $sessionsToConsume, $saleId, $saleItemId, $serviceId): ?CustomerPackage {
            /** @var CustomerPackage $locked */
            $locked = CustomerPackage::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($customerPackage->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->expires_at !== null && $locked->expires_at->endOfDay()->isPast()) {
                if ($locked->status !== 'expired') {
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
                }

                return null;
            }

            if ($locked->status !== 'active') {
                throw new ConflictHttpException("Package is not active (current status: {$locked->status}).");
            }

            if ($saleId !== null) {
                $saleExists = Sale::query()
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $unit->getKey())
                    ->where('customer_id', $locked->customer_id)
                    ->whereKey($saleId)
                    ->exists();

                if (! $saleExists) {
                    throw new \InvalidArgumentException('The associated sale was not found for this customer.');
                }
            }

            if ($saleItemId !== null) {
                $saleItem = SaleItem::query()
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $unit->getKey())
                    ->where('sale_id', $saleId)
                    ->whereKey($saleItemId)
                    ->first();

                if ($saleItem === null) {
                    throw new \InvalidArgumentException('The associated sale item does not belong to the associated sale.');
                }

                if ($saleItem->service_id === null) {
                    throw new \InvalidArgumentException('Package consumption requires a service sale item.');
                }

                $eligibleServiceIds = collect($locked->eligible_services_snapshot ?? [])
                    ->pluck('id')
                    ->map(static fn (mixed $serviceId): string => (string) $serviceId)
                    ->all();

                if (! in_array((string) $saleItem->service_id, $eligibleServiceIds, true)) {
                    throw new \InvalidArgumentException('The sale item service is not eligible for this package.');
                }

                $serviceId ??= (string) $saleItem->service_id;
            }

            if ($serviceId !== null) {
                $balance = CustomerPackageService::query()
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $unit->getKey())
                    ->where('customer_package_id', $locked->getKey())
                    ->where('service_id', $serviceId)
                    ->lockForUpdate()
                    ->first();

                if ($balance !== null && $balance->remaining_quantity < $sessionsToConsume) {
                    throw new ConflictHttpException('Not enough sessions remaining for this service.');
                }

                if ($balance !== null) {
                    CustomerPackageService::query()
                        ->where('tenant_id', $context->tenant->getKey())
                        ->where('unit_id', $unit->getKey())
                        ->where('customer_package_id', $locked->getKey())
                        ->where('service_id', $serviceId)
                        ->update([
                            'remaining_quantity' => $balance->remaining_quantity - $sessionsToConsume,
                        ]);
                }
            }

            if ($locked->remaining_sessions < $sessionsToConsume) {
                throw new ConflictHttpException("Not enough sessions remaining. Available: {$locked->remaining_sessions}, requested: {$sessionsToConsume}.");
            }

            $newRemaining = $locked->remaining_sessions - $sessionsToConsume;
            $newStatus = $newRemaining === 0 ? 'exhausted' : 'active';

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
                'sale_id' => $saleId,
                'sale_item_id' => $saleItemId,
                'sessions_consumed' => $sessionsToConsume,
                'user_id' => $actor->getKey(),
            ]);

            $this->events->record($actor, $context, 'customer_package.consumed', $locked, [
                'customer_package_id' => $locked->getKey(),
                'package_usage_id' => $usage->getKey(),
                'sessions_consumed' => $sessionsToConsume,
                'remaining_sessions' => $newRemaining,
                'status' => $newStatus,
                'sale_id' => $saleId,
                'sale_item_id' => $saleItemId,
            ]);

            return $locked->fresh()->load(['packageTemplate.services', 'customer', 'usages.user']);
        }, 5);

        if ($package === null) {
            throw new ConflictHttpException('Package has expired.');
        }

        return $package;
    }
}
