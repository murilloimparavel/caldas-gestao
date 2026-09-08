<?php

namespace Database\Factories;

use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantDomain>
 */
class TenantDomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'hostname' => fake()->unique()->domainWord().'.example.com',
            'kind' => TenantDomainKind::Management,
            'status' => TenantDomainStatus::Pending,
            'verification_token' => fake()->unique()->sha256(),
            'expected_cname' => 'vps.caldasindica.com',
            'ssl_status' => 'pending',
            'metadata' => [],
        ];
    }
}
