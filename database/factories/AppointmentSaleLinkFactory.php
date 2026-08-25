<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentSaleLink;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppointmentSaleLink>
 */
class AppointmentSaleLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Unit::query()->whereKey($attributes['unit_id'])->value('tenant_id'),
            'appointment_id' => fn (array $attributes): string => (string) Appointment::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'sale_id' => fn (array $attributes): string => (string) Sale::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'unit_id' => $attributes['unit_id'],
            ])->getKey(),
            'created_by' => User::factory(),
        ];
    }
}
