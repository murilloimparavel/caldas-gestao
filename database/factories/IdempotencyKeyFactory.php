<?php

namespace Database\Factories;

use App\Enums\IdempotencyStatus;
use App\Models\IdempotencyKey;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IdempotencyKey> */
class IdempotencyKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'actor_user_id' => null,
            'key' => fake()->unique()->uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'status' => IdempotencyStatus::Started,
            'response_code' => null,
            'resource_type' => null,
            'resource_id' => null,
            'response_ref' => null,
            'expires_at' => now()->addDay(),
            'completed_at' => null,
        ];
    }
}
