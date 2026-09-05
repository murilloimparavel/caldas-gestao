<?php

namespace Database\Factories;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionRenewalAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionRenewalAttempt>
 */
class SubscriptionRenewalAttemptFactory extends Factory
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
            'customer_subscription_id' => CustomerSubscription::factory(),
            'tenant_id' => fn (array $attributes): string => (string) CustomerSubscription::query()->whereKey($attributes['customer_subscription_id'])->value('tenant_id'),
            'unit_id' => fn (array $attributes): string => (string) CustomerSubscription::query()->whereKey($attributes['customer_subscription_id'])->value('unit_id'),
            'source_cycle_id' => SubscriptionCycle::factory(),
            'target_cycle_id' => null,
            'idempotency_key' => fn (array $attributes): string => $attributes['customer_subscription_id'].':source',
            'status' => 'pending',
            'failure_code' => null,
            'failure_message' => null,
            'attempted_at' => now(),
            'completed_at' => null,
            'metadata' => null,
        ];
    }
}
