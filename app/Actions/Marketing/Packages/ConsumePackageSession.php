<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
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
            throw new InvalidArgumentException('Sessions to consume must be greater than zero.');
        }

        $saleId = isset($data['sale_id']) && $data['sale_id'] !== '' ? (string) $data['sale_id'] : null;
        $saleItemId = isset($data['sale_item_id']) && $data['sale_item_id'] !== '' ? (string) $data['sale_item_id'] : null;

        if ($saleId !== null) {
            $saleExists = Sale::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($saleId)
                ->exists();

            if (! $saleExists) {
                throw new InvalidArgumentException('The associated sale was not found.');
            }
        }

        if ($saleItemId !== null) {
            $saleItemExists = SaleItem::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($saleItemId)
                ->exists();

            if (! $saleItemExists) {
                throw new InvalidArgumentException('The associated sale item was not found.');
            }
        }

        $current = CustomerPackage::query()->whereKey($customerPackage->getKey())->firstOrFail();

        if ($current->expires_at !== null && $current->expires_at->endOfDay()->isPast()) {
            if ($current->status !== 'expired') {
                $current->forceFill([
                    'status' => 'expired',
                    'lock_version' => $current->lock_version + 1,
                ])->save();
            }

            throw new ConflictHttpException('Package has expired.');
        }

        return DB::transaction(function () use ($actor, $context, $unit, $customerPackage, $sessionsToConsume, $saleId, $saleItemId): CustomerPackage {
            /** @var CustomerPackage $locked */
            $locked = CustomerPackage::query()
                ->whereKey($customerPackage->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'active') {
                throw new ConflictHttpException("Package is not active (current status: {$locked->status}).");
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
    }
}
