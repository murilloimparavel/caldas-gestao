<?php

namespace App\Actions\Marketing\Subscriptions;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionUsageEntry;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ConsumeSubscriptionUsage extends OperationalAction
{
    /** @param array{service_id:string, quantity?:int, idempotency_key?:string|null} $data */
    public function handle(User $actor, TenantContext $context, CustomerSubscription $subscription, array $data): SubscriptionUsageEntry
    {
        $unit = $this->unit($actor, $context, 'subscription.usage');
        if ($subscription->tenant_id !== $context->tenant->getKey() || $subscription->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The subscription belongs to another workspace.');
        }
        $quantity = (int) ($data['quantity'] ?? 1);
        if ($quantity < 1) {
            throw new ConflictHttpException('Usage quantity must be greater than zero.');
        }
        $serviceId = (string) ($data['service_id'] ?? '');
        $idempotencyKey = isset($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $payload = ['service_id' => $serviceId, 'quantity' => $quantity, 'metadata' => $data['metadata'] ?? null];
        ksort($payload);
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $context, $subscription, $quantity, $serviceId, $idempotencyKey, $payload, $payloadHash): SubscriptionUsageEntry {
            $lockedSubscription = CustomerSubscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedSubscription->status !== 'active') {
                throw new ConflictHttpException('Only active subscriptions can be consumed.');
            }
            $cycle = $lockedSubscription->cycles()->where('status', 'open')->latest('cycle_number')->lockForUpdate()->first();
            if ($cycle === null || $cycle->starts_on->isAfter(today()) || $cycle->ends_on->isBefore(today())) {
                throw new ConflictHttpException('The subscription has no open cycle.');
            }

            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = $cycle->usageEntries()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ($existing->payload_hash !== $payloadHash) {
                        throw new ConflictHttpException('The idempotency key was already used with a different payload.');
                    }

                    return $existing;
                }
            }

            $usage = $cycle->usages()->where('service_id', $serviceId)->lockForUpdate()->first();
            if ($usage === null) {
                throw new ConflictHttpException('The service is not included in this subscription cycle.');
            }
            if ($usage->tenant_id !== $lockedSubscription->tenant_id || $usage->unit_id !== $lockedSubscription->unit_id || $usage->customer_subscription_id !== $lockedSubscription->getKey()) {
                throw new AuthorizationException('The subscription usage belongs to another workspace.');
            }
            if ($usage->max_uses_snapshot !== null && $usage->used_count + $quantity > $usage->max_uses_snapshot) {
                throw new ConflictHttpException('The subscription service limit has been reached for this cycle.');
            }
            $usage->increment('used_count', $quantity);
            $entry = SubscriptionUsageEntry::query()->create([
                'id' => (string) Str::uuid7(), 'tenant_id' => $lockedSubscription->tenant_id, 'unit_id' => $lockedSubscription->unit_id,
                'customer_subscription_id' => $lockedSubscription->getKey(), 'subscription_cycle_id' => $cycle->getKey(),
                'subscription_cycle_usage_id' => $usage->getKey(), 'service_id' => $serviceId, 'quantity' => $quantity,
                'idempotency_key' => $idempotencyKey ?: null, 'payload_hash' => $payloadHash, 'payload_metadata' => $payload,
                'actor_user_id' => $actor->getKey(), 'consumed_at' => now(),
            ]);
            $this->events->record($actor, $context, 'customer_subscription.usage_consumed', $lockedSubscription, ['customer_subscription_id' => $lockedSubscription->getKey(), 'service_id' => $entry->service_id, 'quantity' => $quantity]);

            return $entry;
        }, 5);
    }
}
