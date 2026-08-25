<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScheduleBlock>
 */
class ScheduleBlockFactory extends Factory
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
            'starts_at' => now()->setTime(12, 0),
            'ends_at' => now()->setTime(13, 0),
            'timezone' => 'America/Sao_Paulo',
            'reason' => 'Indisponível',
            'status' => 'active',
            'lock_version' => 0,
        ];
    }
}
