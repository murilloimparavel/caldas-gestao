<?php

namespace Database\Factories;

use App\Models\OnlineBookingVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineBookingVisit>
 */
class OnlineBookingVisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['visitor_hash' => hash('sha256', fake()->uuid()), 'landing_path' => '/book/example', 'occurred_at' => now(), 'consent' => false];
    }
}
