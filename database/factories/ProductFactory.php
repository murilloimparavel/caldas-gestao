<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
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
            'category_id' => null,
            'name' => fake()->words(2, true),
            'sku' => fake()->optional()->bothify('SKU-####-????'),
            'barcode' => fake()->optional()->ean13(),
            'cost_price_cents' => 3000,
            'sale_price_cents' => 6000,
            'unit_of_measure' => 'un',
            'min_stock' => 5,
            'current_stock' => 20,
            'is_active' => true,
            'lock_version' => 1,
        ];
    }
}
