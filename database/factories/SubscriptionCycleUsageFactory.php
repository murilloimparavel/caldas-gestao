<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionCycleUsage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionCycleUsage>
 */
class SubscriptionCycleUsageFactory extends Factory
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
            'tenant_id' => fn (array $attributes): string => (string) SubscriptionCycle::query()->whereKey($attributes['subscription_cycle_id'])->value('tenant_id'),
            'unit_id' => fn (array $attributes): string => (string) SubscriptionCycle::query()->whereKey($attributes['subscription_cycle_id'])->value('unit_id'),
            'customer_subscription_id' => fn (array $attributes): string => (string) SubscriptionCycle::query()->whereKey($attributes['subscription_cycle_id'])->value('customer_subscription_id'),
            'service_id' => Service::factory(),
            'max_uses_snapshot' => 10,
            'used_count' => 0,
        ];
    }
}
