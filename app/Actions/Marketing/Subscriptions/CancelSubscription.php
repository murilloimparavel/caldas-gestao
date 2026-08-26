<?php

namespace App\Actions\Marketing\Subscriptions;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerSubscription;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CancelSubscription extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, CustomerSubscription $subscription, array $data = [], ?int $expectedVersion = null): CustomerSubscription
    {
        $unit = $this->unit($actor, $context, 'subscription.cancel');

        if ($subscription->tenant_id !== $context->tenant->getKey() || $subscription->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The subscription belongs to another workspace.');
        }

        $expectedVersion ??= $subscription->lock_version;

        return DB::transaction(function () use ($actor, $context, $subscription, $data, $expectedVersion): CustomerSubscription {
            $locked = CustomerSubscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The subscription was modified concurrently.');
            }

            if ($locked->status === 'cancelled') {
                return $locked->load(['plan', 'customer']);
            }

            if (! in_array($locked->status, ['active', 'paused'], true)) {
                throw ValidationException::withMessages(['subscription' => 'Somente assinaturas ativas ou pausadas podem ser canceladas.']);
            }

            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'notes' => $data['notes'] ?? $locked->notes,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'customer_subscription.cancelled', $locked, [
                'customer_id' => $locked->customer_id,
                'subscription_plan_id' => $locked->subscription_plan_id,
                'cancelled_at' => $locked->cancelled_at?->toISOString(),
                'notes' => $locked->notes,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh()->load(['plan', 'customer']);
        }, 5);
    }
}
