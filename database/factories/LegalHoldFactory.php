<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LegalHold;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LegalHold>
 */
class LegalHoldFactory extends Factory
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
            'customer_id' => Customer::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Customer::query()->whereKey($attributes['customer_id'])->value('tenant_id'),
            'unit_id' => fn (array $attributes): string => (string) Customer::query()->whereKey($attributes['customer_id'])->value('unit_id'),
            'reason' => fake()->sentence(),
            'placed_by_user_id' => null,
            'placed_at' => now(),
        ];
    }
}
