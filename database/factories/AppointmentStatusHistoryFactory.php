<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppointmentStatusHistory>
 */
class AppointmentStatusHistoryFactory extends Factory
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
            'actor_user_id' => null,
            'action' => 'created',
            'from_status' => null,
            'to_status' => 'confirmed',
            'reason' => null,
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
