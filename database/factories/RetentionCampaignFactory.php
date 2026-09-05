<?php

namespace Database\Factories;

use App\Models\RetentionCampaign;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RetentionCampaign>
 */
class RetentionCampaignFactory extends Factory
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
            'created_by_user_id' => null,
            'name' => fake()->sentence(3),
            'status' => 'draft',
            'channel' => 'email',
            'purpose' => 'marketing',
            'segment_definition' => ['inactive_days' => 90],
            'subject' => fake()->sentence(),
            'message' => fake()->paragraph(),
            'lock_version' => 0,
        ];
    }
}
