<?php

namespace Database\Factories;

use App\Models\CommissionAccrual;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommissionAccrual>
 */
class CommissionAccrualFactory extends Factory
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
            'professional_id' => fn (array $attributes): string => (string) Professional::factory()->create([
                'unit_id' => $attributes['unit_id'],
                'tenant_id' => $attributes['tenant_id'],
            ])->getKey(),
            'sale_id' => fn (array $attributes): string => (string) Sale::factory()->create([
                'unit_id' => $attributes['unit_id'],
                'tenant_id' => $attributes['tenant_id'],
            ])->getKey(),
            'sale_item_id' => fn (array $attributes): string => (string) SaleItem::factory()->create([
                'unit_id' => $attributes['unit_id'],
                'tenant_id' => $attributes['tenant_id'],
                'sale_id' => $attributes['sale_id'],
            ])->getKey(),
            'item_name_snapshot' => 'Serviço Exemplo',
            'gross_amount_cents' => 5000,
            'rate_type' => 'percentage',
            'rate_value' => 10,
            'commission_amount_cents' => 500,
            'status' => 'accrued',
            'settled_at' => null,
            'settlement_id' => null,
            'lock_version' => 0,
        ];
    }
}
