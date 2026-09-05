<?php

namespace Database\Factories;

use App\Models\DataRetentionPolicy;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DataRetentionPolicy>
 */
class DataRetentionPolicyFactory extends Factory
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
            'data_class' => 'customer',
            'retention_days' => 1825,
            'anchor' => 'last_activity_at',
            'enabled' => true,
        ];
    }
}
