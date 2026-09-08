<?php

namespace Database\Factories;

use App\Models\GoogleCalendarConnection;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoogleCalendarConnection>
 */
class GoogleCalendarConnectionFactory extends Factory
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
            'connected_by_user_id' => User::factory(),
            'provider' => 'google',
            'status' => 'connected',
            'google_account_email' => fake()->safeEmail(),
            'calendar_id' => 'primary',
            'calendar_name' => 'Primary calendar',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'scopes' => [config('services.google_calendar.scope')],
            'token_expires_at' => now()->addHour(),
            'last_error' => null,
            'last_synced_at' => null,
            'lock_version' => 0,
        ];
    }
}
