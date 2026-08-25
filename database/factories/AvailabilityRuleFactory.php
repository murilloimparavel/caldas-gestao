<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\Professional;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
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
            'professional_id' => fn (array $attributes): string => (string) Professional::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'weekday' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '18:00:00',
            'timezone' => 'America/Sao_Paulo',
            'status' => 'active',
            'lock_version' => 0,
        ];
    }
}
