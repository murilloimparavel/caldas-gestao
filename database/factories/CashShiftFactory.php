<?php

namespace Database\Factories;

use App\Models\CashShift;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CashShift>
 */
class CashShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Unit::query()->whereKey($attributes['unit_id'])->value('tenant_id'),
            'opened_by_user_id' => User::factory(),
            'closed_by_user_id' => null,
            'initial_amount_cents' => 10000,
            'expected_amount_cents' => 10000,
            'final_amount_cents' => null,
            'difference_cents' => null,
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => null,
            'lock_version' => 1,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'closed_by_user_id' => $attributes['opened_by_user_id'] ?? User::factory(),
            'final_amount_cents' => $attributes['expected_amount_cents'] ?? 10000,
            'difference_cents' => 0,
            'closed_at' => now(),
        ]);
    }
}
