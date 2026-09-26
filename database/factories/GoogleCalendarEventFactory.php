<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoogleCalendarEvent>
 */
class GoogleCalendarEventFactory extends Factory
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
            'appointment_id' => Appointment::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Appointment::query()->whereKey($attributes['appointment_id'])->value('tenant_id'),
            'unit_id' => fn (array $attributes): string => (string) Appointment::query()->whereKey($attributes['appointment_id'])->value('unit_id'),
            'connection_id' => fn (array $attributes): string => (string) GoogleCalendarConnection::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'google_event_id' => 'google-event-'.fake()->unique()->numerify('#####'),
            'sync_status' => 'pending',
            'operation' => 'upsert',
            'appointment_lock_version' => 0,
            'payload_hash' => null,
            'last_error' => null,
            'synced_at' => null,
        ];
    }
}
