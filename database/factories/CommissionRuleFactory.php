<?php

namespace Database\Factories;

use App\Models\CommissionRule;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
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
            'professional_id' => null,
            'service_id' => null,
            'product_id' => null,
            'type' => 'percentage',
            'value_rate' => 10,
            'is_active' => true,
            'lock_version' => 0,
        ];
    }
}
