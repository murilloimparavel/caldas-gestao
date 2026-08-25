<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Unit::query()->whereKey($attributes['unit_id'])->value('tenant_id'),
            'customer_id' => fn (array $attributes): string => (string) Customer::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'sale_category_id' => fn (array $attributes): string => (string) SaleCategory::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'category_key_snapshot' => 'barbearia',
            'category_name_snapshot' => 'Barbearia',
            'reference_label' => null,
            'open_context_key' => null,
            'status' => 'draft',
            'currency' => 'BRL',
            'total_amount_cents' => 10000,
            'discount_amount_cents' => 0,
            'final_amount_cents' => 10000,
            'notes' => null,
            'lock_version' => 1,
        ];
    }
}
