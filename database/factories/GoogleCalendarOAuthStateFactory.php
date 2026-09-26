<?php

namespace Database\Factories;

use App\Models\GoogleCalendarOAuthState;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoogleCalendarOAuthState>
 */
class GoogleCalendarOAuthStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $state = Str::random(64);

        return [
            'id' => (string) Str::uuid7(),
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Unit::query()->whereKey($attributes['unit_id'])->value('tenant_id'),
            'user_id' => User::factory(),
            'state_hash' => hash('sha256', $state),
            'code_verifier' => Str::random(96),
            'redirect_uri' => 'https://example.test/google-calendar/callback',
            'return_host' => 'example.test',
            'return_path' => '/calendar',
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ];
    }
}
