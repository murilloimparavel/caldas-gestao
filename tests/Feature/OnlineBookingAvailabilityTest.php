<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Customer;
use App\Models\OnlineBookingSetting;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\CarbonImmutable;

/** @return array{0: Tenant, 1: Unit, 2: Service, 3: Professional, 4: CarbonImmutable} */
function onlineBookingAvailabilityWorkspace(): array
{
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();

    $unit->update([
        'online_booking_enabled' => true,
        'timezone' => 'America/Sao_Paulo',
    ]);

    $service->update([
        'online_booking_enabled' => true,
        'duration_minutes' => 30,
        'price_cents' => 6000,
    ]);

    $professional->update([
        'online_booking_enabled' => true,
    ]);

    $date = CarbonImmutable::now($unit->timezone)->addDays(5)->startOfDay();

    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'weekday' => $date->dayOfWeek,
        'starts_at' => '09:00:00',
        'ends_at' => '18:00:00',
        'timezone' => $unit->timezone,
        'status' => 'active',
    ]);

    OnlineBookingSetting::create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'minimum_notice_minutes' => 0,
        'public_hours' => [
            (string) $date->dayOfWeek => [
                'enabled' => true,
                'starts_at' => '09:00',
                'ends_at' => '18:00',
            ],
        ],
    ]);

    return [$tenant, $unit, $service, $professional, $date];
}

it('returns available slots within public hours and professional schedule', function () {
    [$tenant, $unit, $service, $professional, $date] = onlineBookingAvailabilityWorkspace();

    $response = $this->getJson(route('public_booking.availability', [
        $tenant,
        $unit,
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'date' => $date->toDateString(),
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('date', $date->toDateString())
        ->assertJsonPath('timezone', 'America/Sao_Paulo');

    $slots = $response->json('slots');
    expect($slots)->toBeArray()
        ->and($slots)->not->toBeEmpty();

    $startTimes = collect($slots)->pluck('starts_at');
    expect($startTimes)->toContain($date->setTime(9, 0)->toIso8601String())
        ->and($startTimes)->toContain($date->setTime(9, 30)->toIso8601String())
        ->and($startTimes)->toContain($date->setTime(14, 0)->toIso8601String())
        ->and($startTimes)->toContain($date->setTime(17, 30)->toIso8601String());

    // Slot that would end after public hours (e.g. 18:00 start for 30min service ends at 18:30) must NOT exist
    expect($startTimes)->not->toContain($date->setTime(18, 0)->toIso8601String());
});

it('excludes slots occupied by existing appointments of the professional', function () {
    [$tenant, $unit, $service, $professional, $date] = onlineBookingAvailabilityWorkspace();

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    // Active appointment from 10:00 to 10:30
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'customer_id' => $customer->getKey(),
        'starts_at' => $date->setTime(10, 0),
        'ends_at' => $date->setTime(10, 30),
        'status' => 'scheduled',
    ]);

    // Cancelled appointment from 15:00 to 15:30 (should NOT block availability)
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'customer_id' => $customer->getKey(),
        'starts_at' => $date->setTime(15, 0),
        'ends_at' => $date->setTime(15, 30),
        'status' => 'cancelled',
        'cancelled_at' => $date->setTime(14, 0),
        'cancel_reason' => 'Cliente solicitou reagendamento',
    ]);

    $response = $this->getJson(route('public_booking.availability', [
        $tenant,
        $unit,
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'date' => $date->toDateString(),
    ]));

    $response->assertSuccessful();

    $startTimes = collect($response->json('slots'))->pluck('starts_at');

    // Conflicting slots around 10:00 appointment (30min service with 15min interval):
    // 09:45 -> 10:15 (overlaps 10:00 - 10:30)
    // 10:00 -> 10:30 (exact overlap)
    // 10:15 -> 10:45 (overlaps 10:00 - 10:30)
    expect($startTimes)->not->toContain($date->setTime(9, 45)->toIso8601String())
        ->and($startTimes)->not->toContain($date->setTime(10, 0)->toIso8601String())
        ->and($startTimes)->not->toContain($date->setTime(10, 15)->toIso8601String());

    // Non-conflicting slots must remain available
    expect($startTimes)->toContain($date->setTime(9, 30)->toIso8601String())
        ->and($startTimes)->toContain($date->setTime(10, 30)->toIso8601String())
        ->and($startTimes)->toContain($date->setTime(15, 0)->toIso8601String());
});

it('excludes slots occupied by active schedule blocks', function () {
    [$tenant, $unit, $service, $professional, $date] = onlineBookingAvailabilityWorkspace();

    // Professional block from 14:00 to 15:00
    ScheduleBlock::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => $date->setTime(14, 0),
        'ends_at' => $date->setTime(15, 0),
        'timezone' => $unit->timezone,
        'status' => 'active',
        'reason' => 'Treinamento de equipe',
    ]);

    $response = $this->getJson(route('public_booking.availability', [
        $tenant,
        $unit,
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'date' => $date->toDateString(),
    ]));

    $response->assertSuccessful();

    $startTimes = collect($response->json('slots'))->pluck('starts_at');

    // Blocked slots between 14:00 and 15:00
    expect($startTimes)->not->toContain($date->setTime(13, 45)->toIso8601String())
        ->and($startTimes)->not->toContain($date->setTime(14, 0)->toIso8601String())
        ->and($startTimes)->not->toContain($date->setTime(14, 15)->toIso8601String())
        ->and($startTimes)->not->toContain($date->setTime(14, 30)->toIso8601String())
        ->and($startTimes)->not->toContain($date->setTime(14, 45)->toIso8601String());

    // Before and after the block
    expect($startTimes)->toContain($date->setTime(13, 30)->toIso8601String())
        ->and($startTimes)->toContain($date->setTime(15, 0)->toIso8601String());
});

it('excludes slots when unit-wide schedule block is active', function () {
    [$tenant, $unit, $service, $professional, $date] = onlineBookingAvailabilityWorkspace();

    // Unit-wide block (professional_id is null)
    ScheduleBlock::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => null,
        'starts_at' => $date->setTime(11, 0),
        'ends_at' => $date->setTime(12, 0),
        'timezone' => $unit->timezone,
        'status' => 'active',
        'reason' => 'Manutenção elétrica na unidade',
    ]);

    $response = $this->getJson(route('public_booking.availability', [
        $tenant,
        $unit,
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'date' => $date->toDateString(),
    ]));

    $response->assertSuccessful();

    $startTimes = collect($response->json('slots'))->pluck('starts_at');

    expect($startTimes)->not->toContain($date->setTime(11, 0)->toIso8601String())
        ->and($startTimes)->not->toContain($date->setTime(11, 30)->toIso8601String())
        ->and($startTimes)->toContain($date->setTime(10, 30)->toIso8601String())
        ->and($startTimes)->toContain($date->setTime(12, 0)->toIso8601String());
});
