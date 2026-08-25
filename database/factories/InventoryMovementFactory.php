<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'product_id' => Product::factory(),
            'unit_id' => fn (array $attributes): string => (string) Product::query()->whereKey($attributes['product_id'])->value('unit_id'),
            'tenant_id' => fn (array $attributes): string => (string) Product::query()->whereKey($attributes['product_id'])->value('tenant_id'),
            'type' => 'purchase_inflow',
            'quantity' => 10,
            'unit_cost_cents' => 1500,
            'previous_stock' => 0,
            'resulting_stock' => 10,
            'reason' => 'Entrada por nota fiscal de compra',
            'reference_type' => null,
            'reference_id' => null,
            'user_id' => User::factory(),
        ];
    }
}
