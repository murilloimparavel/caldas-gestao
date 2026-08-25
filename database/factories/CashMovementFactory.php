<?php

namespace Database\Factories;

use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'cash_shift_id' => CashShift::factory(),
            'unit_id' => fn (array $attributes): string => (string) CashShift::query()->whereKey($attributes['cash_shift_id'])->value('unit_id'),
            'tenant_id' => fn (array $attributes): string => (string) CashShift::query()->whereKey($attributes['cash_shift_id'])->value('tenant_id'),
            'type' => 'supply',
            'amount_cents' => 5000,
            'reason' => 'Suprimento inicial de troco',
            'reference_type' => null,
            'reference_id' => null,
            'user_id' => User::factory(),
        ];
    }
}
