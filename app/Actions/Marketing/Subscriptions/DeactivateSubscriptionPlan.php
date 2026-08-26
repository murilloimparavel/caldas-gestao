<?php

namespace App\Actions\Marketing\Subscriptions;

use App\Actions\Operational\OperationalAction;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeactivateSubscriptionPlan extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, SubscriptionPlan $plan, ?int $expectedVersion = null): SubscriptionPlan
    {
        $unit = $this->unit($actor, $context, 'subscription.manage');

        if ($plan->tenant_id !== $context->tenant->getKey() || $plan->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The subscription plan belongs to another workspace.');
        }

        $expectedVersion ??= $plan->lock_version;

        return DB::transaction(function () use ($actor, $context, $plan, $expectedVersion): SubscriptionPlan {
            $locked = SubscriptionPlan::query()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();

            if ($expectedVersion !== null && $locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The subscription plan was modified concurrently.');
            }

            if (! $locked->is_active) {
                return $locked->load('services');
            }

            $locked->forceFill([
                'is_active' => false,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'subscription_plan.deactivated', $locked, [
                'is_active' => $locked->is_active,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh()->load('services');
        }, 5);
    }
}
