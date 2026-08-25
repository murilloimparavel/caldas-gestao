<?php

namespace Database\Factories;

use App\Models\SaleCategory;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SaleCategory>
 */
class SaleCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'id' => (string) Str::uuid7(),
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Unit::query()->whereKey($attributes['unit_id'])->value('tenant_id'),
            'name' => $name,
            'key' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'type' => fake()->randomElement(['service', 'product', 'mixed']),
            'uniqueness_scope' => fake()->randomElement(['customer', 'appointment', 'reference', 'none']),
            'is_active' => true,
            'lock_version' => 1,
        ];
    }
}
