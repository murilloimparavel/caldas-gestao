<?php

namespace Database\Factories;

use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PackageUsage>
 */
class PackageUsageFactory extends Factory
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
            'customer_package_id' => fn (array $attributes): string => (string) CustomerPackage::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'sale_id' => null,
            'sale_item_id' => null,
            'sessions_consumed' => 1,
            'user_id' => User::factory(),
        ];
    }
}
