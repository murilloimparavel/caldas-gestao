<?php

namespace Database\Factories;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionCycle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionCycle>
 */
class SubscriptionCycleFactory extends Factory
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
            'cycle_number' => 1,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonth()->subDay()->toDateString(),
            'status' => 'open',
            'renewal_key' => fn (array $attributes): string => $attributes['customer_subscription_id'].':1:'.$attributes['starts_on'],
            'previous_cycle_id' => null,
            'next_cycle_id' => null,
            'closed_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }
}
