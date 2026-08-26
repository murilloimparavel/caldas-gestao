<?php

namespace Database\Factories;

use App\Models\CommissionSettlement;
use App\Models\Professional;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommissionSettlement>
 */
class CommissionSettlementFactory extends Factory
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
            'total_amount_cents' => 5000,
            'period_start' => now()->subDays(30),
            'period_end' => now(),
            'paid_at' => now(),
            'user_id' => User::factory(),
            'notes' => null,
            'lock_version' => 0,
        ];
    }
}
