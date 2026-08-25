<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Professional>
 */
class ProfessionalFactory extends Factory
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
            'name' => fake()->name(),
            'email' => null,
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'lock_version' => 0,
        ];
    }
}
