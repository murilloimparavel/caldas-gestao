<?php

namespace Database\Factories;

use App\Models\ClosingSession;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClosingSession>
 */
class ClosingSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Unit::query()->whereKey($attributes['unit_id'])->value('tenant_id'),
            'closing_subject' => 'customer:'.(string) Str::uuid7(),
            'currency' => 'BRL',
            'payment_method' => null,
            'expected_total_cents' => 10000,
            'final_total_cents' => 10000,
            'status' => 'draft',
            'receipt_number' => null,
            'receipt_payload' => null,
            'idempotency_key' => null,
            'closed_by_user_id' => User::factory(),
            'lock_version' => 1,
        ];
    }
}
