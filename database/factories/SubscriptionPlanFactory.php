<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
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
            'name' => fake()->words(3, true).' Plan',
            'description' => fake()->sentence(),
            'price_cents' => fake()->randomElement([4990, 9990, 19990, 29990]),
            'billing_cycle' => fake()->randomElement(['monthly', 'quarterly', 'yearly']),
            'is_active' => true,
            'lock_version' => 0,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => ['billing_cycle' => 'monthly']);
    }

    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => ['billing_cycle' => 'quarterly']);
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => ['billing_cycle' => 'yearly']);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
