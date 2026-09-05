<?php

namespace Database\Factories;

use App\Models\PlatformPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformPlan>
 */
class PlatformPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2), 'name' => fake()->words(2, true), 'price_cents' => 9900, 'billing_cycle' => 'monthly', 'trial_days' => 14, 'features' => [], 'limits' => [], 'is_active' => true,
        ];
    }
}
