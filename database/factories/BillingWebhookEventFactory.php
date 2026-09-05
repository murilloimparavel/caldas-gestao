<?php

namespace Database\Factories;

use App\Models\BillingWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingWebhookEvent>
 */
class BillingWebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'lastlink', 'provider_event_id' => fake()->uuid(), 'event' => 'Purchase_Order_Confirmed', 'payload' => [], 'status' => 'pending', 'attempts' => 0, 'received_at' => now(),
        ];
    }
}
