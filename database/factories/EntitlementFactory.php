<?php

namespace Database\Factories;

use App\Enums\EntitlementSource;
use App\Enums\EntitlementStatus;
use App\Models\Entitlement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Entitlement> */
class EntitlementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => fake()->unique()->slug(2),
            'status' => EntitlementStatus::Active,
            'quantity' => null,
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
            'source' => EntitlementSource::Plan,
            'config' => [],
        ];
    }
}
