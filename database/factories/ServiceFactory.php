<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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
            'name' => fake()->words(3, true),
            'duration_minutes' => 60,
            'price_cents' => 10000,
            'status' => 'active',
            'lock_version' => 0,
        ];
    }
}
