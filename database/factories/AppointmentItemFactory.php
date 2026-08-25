<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppointmentItem>
 */
class AppointmentItemFactory extends Factory
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
            'service_id' => Service::factory(),
            'professional_id' => fn (array $attributes): string => (string) Appointment::query()->whereKey($attributes['appointment_id'])->value('professional_id'),
            'service_name_snapshot' => 'Serviço',
            'duration_minutes' => 60,
            'price_cents' => 10000,
            'currency' => 'BRL',
            'position' => 1,
        ];
    }
}
