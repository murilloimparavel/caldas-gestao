<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: string, 2: string, 3: Professional, 4: Customer, 5: Service} */
function calendarOperationsWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Calendar operations '.Str::random(8)]);
    $unit = $tenant->units()->firstOrFail();
    $unit->update(['timezone' => 'America/Sao_Paulo']);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'duration_minutes' => 60]);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    return [$owner, (string) $tenant->getKey(), (string) $unit->getKey(), $professional, $customer, $service];
}

it('creates, updates, and deactivates availability rules idempotently', function () {
    [$owner, $tenantId, $unitId, $professional] = calendarOperationsWorkspace();
    $payload = ['professional_id' => $professional->getKey(), 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00', 'timezone' => 'America/Sao_Paulo'];

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'availability-rule-create-1'])
        ->actingAs($owner)->post(route('availability_rules.store'), $payload)->assertRedirect(route('professionals.show', $professional));
    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'availability-rule-create-1'])
        ->actingAs($owner)->post(route('availability_rules.store'), $payload)->assertRedirect(route('professionals.show', $professional));

    $rule = AvailabilityRule::query()->sole();
    expect(AvailabilityRule::query()->count())->toBe(1);

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'availability-rule-update-1'])
        ->actingAs($owner)->put(route('availability_rules.update', $rule), [...$payload, 'starts_at' => '10:00', 'lock_version' => 0])->assertRedirect(route('professionals.show', $professional));
    expect($rule->fresh()->starts_at)->toStartWith('10:00')
        ->and($rule->fresh()->lock_version)->toBe(1);

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'availability-rule-delete-1'])
        ->actingAs($owner)->delete(route('availability_rules.destroy', $rule), ['lock_version' => 1])->assertRedirect(route('professionals.show', $professional));
    expect($rule->fresh()->status)->toBe('inactive');
});

it('validates availability intervals and protects calendar resources from another tenant', function () {
    [$owner, $tenantId, $unitId, $professional] = calendarOperationsWorkspace();
    $payload = ['professional_id' => $professional->getKey(), 'weekday' => 1, 'starts_at' => '18:00', 'ends_at' => '09:00', 'timezone' => 'America/Sao_Paulo'];
    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'availability-rule-invalid-1'])
        ->actingAs($owner)->post(route('availability_rules.store'), $payload)->assertSessionHasErrors('ends_at');

    $foreignOwner = User::factory()->create();
    $foreignTenant = (new OnboardTenant)->handle($foreignOwner, ['name' => 'Foreign calendar '.Str::random(8)]);
    $foreignUnit = $foreignTenant->units()->firstOrFail();
    $foreignRule = AvailabilityRule::factory()->create(['tenant_id' => $foreignTenant->getKey(), 'unit_id' => $foreignUnit->getKey()]);
    $validPayload = [...$payload, 'starts_at' => '09:00', 'ends_at' => '18:00'];

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'availability-rule-foreign-1'])
        ->actingAs($owner)->put(route('availability_rules.update', $foreignRule), [...$validPayload, 'lock_version' => 0])->assertForbidden();
});

it('serializes active-unit calendar settings for the calendar page', function () {
    [$owner, $tenantId, $unitId, $professional] = calendarOperationsWorkspace();
    $rule = AvailabilityRule::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'professional_id' => $professional->getKey()]);
    $block = ScheduleBlock::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 09:00', 'ends_at' => '2030-02-10 10:00']);

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId])
        ->actingAs($owner)->get(route('calendar.index', ['date' => '2030-02-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/index')
            ->where('options.professionals.0.id', $professional->getKey())
            ->where('options.professionals.0.avatar_url', $professional->avatar_url)
            ->where('calendarSettings.availability_rules.0.id', $rule->getKey())
            ->where('calendarSettings.schedule_blocks.0.id', $block->getKey()));
});

it('creates, updates, and cancels schedule blocks idempotently', function () {
    [$owner, $tenantId, $unitId, $professional] = calendarOperationsWorkspace();
    $payload = ['professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 09:00', 'ends_at' => '2030-02-10 10:00', 'timezone' => 'America/Sao_Paulo', 'reason' => 'Treinamento'];

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'schedule-block-create-1'])
        ->actingAs($owner)->post(route('schedule_blocks.store'), $payload)->assertRedirect();
    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'schedule-block-create-1'])
        ->actingAs($owner)->post(route('schedule_blocks.store'), $payload)->assertRedirect();

    $block = ScheduleBlock::query()->sole();
    expect(ScheduleBlock::query()->count())->toBe(1);

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'schedule-block-update-1'])
        ->actingAs($owner)->put(route('schedule_blocks.update', $block), [...$payload, 'reason' => 'Reunião', 'lock_version' => 0])->assertRedirect();
    expect($block->fresh()->reason)->toBe('Reunião')
        ->and($block->fresh()->lock_version)->toBe(1);

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'schedule-block-delete-1'])
        ->actingAs($owner)->delete(route('schedule_blocks.destroy', $block), ['lock_version' => 1])->assertRedirect();
    expect($block->fresh()->status)->toBe('cancelled');
});

it('checks in a scoped appointment and rejects an invalid check-in transition', function () {
    [$owner, $tenantId, $unitId, $professional, $customer] = calendarOperationsWorkspace();
    $appointment = Appointment::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'professional_id' => $professional->getKey(), 'customer_id' => $customer->getKey(), 'status' => 'confirmed']);

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'appointment-check-in-1'])
        ->actingAs($owner)->post(route('appointments.check_in', $appointment), ['lock_version' => 0])->assertRedirect();
    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'appointment-check-in-1'])
        ->actingAs($owner)->post(route('appointments.check_in', $appointment), ['lock_version' => 0])->assertRedirect();
    expect($appointment->fresh()->status)->toBe('checked_in')
        ->and($appointment->fresh()->lock_version)->toBe(1);

    $draft = Appointment::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'professional_id' => $professional->getKey(), 'customer_id' => $customer->getKey(), 'status' => 'draft', 'starts_at' => '2030-02-10 12:00:00', 'ends_at' => '2030-02-10 13:00:00']);
    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'appointment-check-in-invalid-1'])
        ->actingAs($owner)->post(route('appointments.check_in', $draft), ['lock_version' => 0])->assertSessionHasErrors('status');

    $foreignOwner = User::factory()->create();
    $foreignTenant = (new OnboardTenant)->handle($foreignOwner, ['name' => 'Foreign check in '.Str::random(8)]);
    $foreignUnit = $foreignTenant->units()->firstOrFail();
    $foreignAppointment = Appointment::factory()->create(['tenant_id' => $foreignTenant->getKey(), 'unit_id' => $foreignUnit->getKey()]);
    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'X-Idempotency-Key' => 'appointment-check-in-foreign-1'])
        ->actingAs($owner)->post(route('appointments.check_in', $foreignAppointment), ['lock_version' => 0])->assertForbidden();
});
