<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\PackageTemplate;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerPackage>
 */
class CustomerPackageFactory extends Factory
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
            'customer_id' => fn (array $attributes): string => (string) Customer::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'package_template_id' => fn (array $attributes): string => (string) PackageTemplate::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'name_snapshot' => 'Pacote de sessões',
            'price_cents_snapshot' => 30000,
            'total_sessions_snapshot' => 5,
            'validity_days_snapshot' => 90,
            'eligible_services_snapshot' => [],
            'sale_id' => null,
            'total_sessions' => 5,
            'remaining_sessions' => 5,
            'expires_at' => now()->addDays(90)->toDateString(),
            'status' => 'active',
            'lock_version' => 0,
        ];
    }
}
