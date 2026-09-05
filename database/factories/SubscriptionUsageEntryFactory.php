<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionCycleUsage;
use App\Models\SubscriptionUsageEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionUsageEntry>
 */
class SubscriptionUsageEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'subscription_cycle_id' => SubscriptionCycle::factory(),
            'subscription_cycle_usage_id' => SubscriptionCycleUsage::factory(),
            'tenant_id' => fn (array $attributes): string => (string) SubscriptionCycle::query()->whereKey($attributes['subscription_cycle_id'])->value('tenant_id'),
            'unit_id' => fn (array $attributes): string => (string) SubscriptionCycle::query()->whereKey($attributes['subscription_cycle_id'])->value('unit_id'),
            'customer_subscription_id' => fn (array $attributes): string => (string) SubscriptionCycle::query()->whereKey($attributes['subscription_cycle_id'])->value('customer_subscription_id'),
            'service_id' => Service::factory(),
            'quantity' => 1,
            'idempotency_key' => null,
            'payload_hash' => null,
            'payload_metadata' => null,
            'actor_user_id' => null,
            'consumed_at' => now(),
            'reversed_at' => null,
            'reversal_reason' => null,
        ];
    }
}
