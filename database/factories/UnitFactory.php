<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'status' => 'active',
            'timezone' => 'America/Sao_Paulo',
            'address' => null,
        ];
    }
}
