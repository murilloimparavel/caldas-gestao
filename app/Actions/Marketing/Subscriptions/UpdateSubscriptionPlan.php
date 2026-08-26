<?php

namespace App\Actions\Marketing\Subscriptions;

use App\Actions\Operational\OperationalAction;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateSubscriptionPlan extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, SubscriptionPlan $plan, array $data, ?int $expectedVersion = null): SubscriptionPlan
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);

        $unit = $this->unit($actor, $context, 'subscription.manage');

        if ($plan->tenant_id !== $context->tenant->getKey() || $plan->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The subscription plan belongs to another workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('The subscription plan lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $plan, $data, $expectedVersion): SubscriptionPlan {
            $locked = SubscriptionPlan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The subscription plan was modified concurrently.');
            }

            $serviceIds = null;
            if (array_key_exists('service_ids', $data)) {
                $serviceIds = $data['service_ids'];
                unset($data['service_ids']);
            }

            $locked->forceFill([
                ...$data,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            if (is_array($serviceIds)) {
                $this->syncServices($locked, $context, $serviceIds);
            }

            $this->events->record($actor, $context, 'subscription_plan.updated', $locked, [
                'is_active' => $locked->is_active,
                'price_cents' => $locked->price_cents,
                'billing_cycle' => $locked->billing_cycle,
                'lock_version' => $locked->lock_version,
                'service_ids' => $serviceIds ?? $locked->services()->pluck('services.id')->all(),
            ]);

            return $locked->fresh()->load('services');
        }, 5);
    }

    /** @param list<string> $serviceIds */
    private function syncServices(SubscriptionPlan $plan, TenantContext $context, array $serviceIds): void
    {
        $serviceIds = array_values(array_unique($serviceIds));

        if (! empty($serviceIds)) {
            $matchingCount = Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->whereIn('id', $serviceIds)
                ->count();

            if (count($serviceIds) !== $matchingCount) {
                throw new InvalidArgumentException('Each service must belong to the active unit.');
            }
        }

        $plan->services()->syncWithPivotValues(
            $serviceIds,
            [
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $context->unit?->getKey(),
            ]
        );
    }
}
