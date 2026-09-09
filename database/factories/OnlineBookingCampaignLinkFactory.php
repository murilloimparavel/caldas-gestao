<?php

namespace Database\Factories;

use App\Models\OnlineBookingCampaignLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineBookingCampaignLink>
 */
class OnlineBookingCampaignLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => fake()->words(2, true), 'utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => fake()->slug(2), 'is_active' => true];
    }
}
