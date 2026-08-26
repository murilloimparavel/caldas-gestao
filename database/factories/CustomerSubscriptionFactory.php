<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerSubscription>
 */
class CustomerSubscriptionFactory extends Factory
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
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Unit::query()->whereKey($attributes['unit_id'])->value('tenant_id'),
            'customer_id' => fn (array $attributes): string => (string) Customer::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'subscription_plan_id' => fn (array $attributes): string => (string) SubscriptionPlan::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'price_cents' => fn (array $attributes): int => (int) SubscriptionPlan::query()->whereKey($attributes['subscription_plan_id'])->value('price_cents'),
            'billing_cycle' => fn (array $attributes): string => (string) SubscriptionPlan::query()->whereKey($attributes['subscription_plan_id'])->value('billing_cycle'),
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'next_billing_date' => now()->addMonth()->toDateString(),
            'cancelled_at' => null,
            'notes' => null,
            'lock_version' => 0,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'paused']);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'expired']);
    }
}
