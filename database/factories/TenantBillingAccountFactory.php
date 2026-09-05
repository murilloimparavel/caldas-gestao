<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantBillingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantBillingAccount>
 */
class TenantBillingAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(), 'provider' => 'lastlink', 'external_buyer_id' => fake()->uuid(), 'email' => fake()->safeEmail(), 'metadata' => [],
        ];
    }
}
