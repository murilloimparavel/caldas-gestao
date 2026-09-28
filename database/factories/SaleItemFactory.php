<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Unit::query()->whereKey($attributes['unit_id'])->value('tenant_id'),
            'sale_id' => fn (array $attributes): string => (string) Sale::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'item_type' => 'service',
            'service_id' => fn (array $attributes): string => (string) Service::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'product_id' => null,
            'professional_id' => null,
            'seller_professional_id' => null,
            'name_snapshot' => 'Corte de Cabelo',
            'unit_price_cents' => 5000,
            'quantity' => 1,
            'discount_cents' => 0,
            'total_cents' => 5000,
        ];
    }
}
