<?php

namespace Database\Factories;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantSubscription>
 */
class TenantSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(), 'platform_plan_id' => PlatformPlan::factory(), 'provider' => 'lastlink', 'status' => 'trial', 'starts_at' => now(), 'ends_at' => now()->addDays(14), 'metadata' => [],
        ];
    }
}
