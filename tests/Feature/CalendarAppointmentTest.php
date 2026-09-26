<?php

use App\Actions\Appointments\CreateAppointment;
use App\Actions\Appointments\UpdateAppointment;
use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AvailabilityRule;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @return array{0: User, 1: string, 2: string, 3: Customer, 4: Professional, 5: Service} */
function calendarWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Calendar '.Str::random(8)]);
    $unit = $tenant->units()->firstOrFail();
    $unit->update(['timezone' => 'America/Sao_Paulo']);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'duration_minutes' => 60]);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    AvailabilityRule::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(), 'weekday' => 0]);

    return [$owner, $tenant->getKey(), $unit->getKey(), $customer, $professional, $service];
}

it('creates and cancels an appointment with history and idempotent replay', function () {
    [$owner, $tenantId, $unitId, $customer, $professional, $service] = calendarWorkspace();
    $payload = ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 10:00', 'duration_minutes' => 45];

    $response = $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->withHeader('X-Idempotency-Key', 'appointment-create-1')->actingAs($owner)->post(route('appointments.store'), $payload);
    $response->assertRedirect();
    $appointment = Appointment::query()->firstOrFail();
    expect((int) $appointment->starts_at->diffInMinutes($appointment->ends_at))->toBe(45)
        ->and(AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->count())->toBe(1);

    $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->withHeader('X-Idempotency-Key', 'appointment-create-1')->actingAs($owner)->post(route('appointments.store'), $payload)->assertRedirect();
    expect(Appointment::query()->count())->toBe(1);

    $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->withHeader('X-Idempotency-Key', 'appointment-cancel-1')->actingAs($owner)->post(route('appointments.cancel', $appointment), ['lock_version' => 0, 'cancel_reason' => 'Cliente solicitou cancelamento'])->assertRedirect();
    expect($appointment->fresh()->status)->toBe('cancelled')
        ->and($appointment->fresh()->cancelled_at)->not->toBeNull()
        ->and(AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->count())->toBe(2);
});

it('rejects recurrence and overlapping active appointments', function () {
    [$owner, $tenantId, $unitId, $customer, $professional, $service] = calendarWorkspace();
    $payload = ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 10:00', 'duration_minutes' => 60];
    $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner)->post(route('appointments.store'), [...$payload, 'recurrence' => 'weekly'])->assertSessionHasErrors('recurrence');
    app(CreateAppointment::class)->handle($owner, TenantContext::forUser($owner, $tenantId, $unitId), $payload);

    expect(fn () => app(CreateAppointment::class)->handle($owner, TenantContext::forUser($owner, $tenantId, $unitId), $payload))->toThrow(ConflictHttpException::class);
});

it('rejects unit blocks, missing availability and cross-tenant resources', function () {
    [$owner, $tenantId, $unitId, $customer, $professional, $service] = calendarWorkspace();
    $context = TenantContext::forUser($owner, $tenantId, $unitId);
    $payload = ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 10:00', 'duration_minutes' => 60];
    ScheduleBlock::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'professional_id' => null, 'starts_at' => '2030-02-10 09:30', 'ends_at' => '2030-02-10 11:00']);

    expect(fn () => app(CreateAppointment::class)->handle($owner, $context, $payload))->toThrow(ConflictHttpException::class);

    $otherOwner = User::factory()->create();
    $otherTenant = (new OnboardTenant)->handle($otherOwner, ['name' => 'Other '.Str::random(8)]);
    $otherUnit = $otherTenant->units()->firstOrFail();
    $foreignCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->getKey(), 'unit_id' => $otherUnit->getKey()]);
    $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner)->post(route('appointments.store'), [...$payload, 'customer_id' => $foreignCustomer->getKey()])->assertNotFound();
});

it('updates with optimistic locking and requires a cancellation reason', function () {
    [$owner, $tenantId, $unitId, $customer, $professional, $service] = calendarWorkspace();
    $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner)->post(route('appointments.store'), ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 10:00', 'duration_minutes' => 60])->assertRedirect();
    $appointment = Appointment::query()->firstOrFail();

    $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner)->put(route('appointments.update', $appointment), ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 11:00', 'duration_minutes' => 60, 'lock_version' => 0])->assertRedirect();
    expect($appointment->fresh()->lock_version)->toBe(1);
    $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner)->put(route('appointments.update', $appointment), ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 12:00', 'duration_minutes' => 60, 'lock_version' => 0])->assertStatus(409);
    $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner)->post(route('appointments.cancel', $appointment), ['lock_version' => 1])->assertSessionHasErrors('cancel_reason');
});

it('rejects invalid status transitions and edits to cancelled appointments', function () {
    [$owner, $tenantId, $unitId, $customer, $professional, $service] = calendarWorkspace();
    $headers = $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner);
    $headers->post(route('appointments.store'), ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 10:00', 'duration_minutes' => 60])->assertRedirect();
    $appointment = Appointment::query()->firstOrFail();

    $headers->put(route('appointments.update', $appointment), ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 10:00', 'duration_minutes' => 60, 'status' => 'completed', 'lock_version' => 0])->assertSessionHasErrors('status');
    $headers->post(route('appointments.cancel', $appointment), ['lock_version' => 0, 'cancel_reason' => 'Cliente solicitou cancelamento'])->assertRedirect();
    $headers->put(route('appointments.update', $appointment), ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 11:00', 'duration_minutes' => 60, 'lock_version' => 1])->assertSessionHasErrors('appointment');
});

it('requires an active professional-service relationship when creating and updating', function () {
    [$owner, $tenantId, $unitId, $customer, $professional, $service] = calendarWorkspace();
    $otherService = Service::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId]);
    $context = TenantContext::forUser($owner, $tenantId, $unitId);
    $payload = ['customer_id' => $customer->getKey(), 'service_id' => $otherService->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 10:00', 'duration_minutes' => 60];

    expect(fn () => app(CreateAppointment::class)->handle($owner, $context, $payload))->toThrow(ValidationException::class);

    $appointment = app(CreateAppointment::class)->handle($owner, $context, ['customer_id' => $customer->getKey(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => '2030-02-10 10:00', 'duration_minutes' => 60]);
    expect(fn () => app(UpdateAppointment::class)->handle($owner, $context, $appointment, [...$payload, 'lock_version' => 0]))->toThrow(ValidationException::class);
});

it('validates calendar query filters', function () {
    [$owner, $tenantId, $unitId] = array_slice(calendarWorkspace(), 0, 3);

    $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner)
        ->get(route('calendar.index', ['date' => 'not-a-date', 'view' => 'year', 'status' => ['unknown']]))
        ->assertSessionHasErrors(['date', 'view', 'status.0']);
});

it('uses the unit timezone for appointment timestamps and stored timezone', function () {
    [$owner, $tenantId, $unitId, $customer, $professional, $service] = calendarWorkspace();
    $context = TenantContext::forUser($owner, $tenantId, $unitId);
    $payload = [
        'customer_id' => $customer->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => '2030-02-10 10:00',
        'duration_minutes' => 60,
        'timezone' => 'UTC',
    ];

    $appointment = app(CreateAppointment::class)->handle($owner, $context, $payload);
    expect($appointment->timezone)->toBe('America/Sao_Paulo')
        ->and($appointment->starts_at->format('H:i'))->toBe('10:00');

    $updated = app(UpdateAppointment::class)->handle($owner, $context, $appointment, [...$payload, 'starts_at' => '2030-02-10 11:00', 'lock_version' => 0]);
    expect($updated->timezone)->toBe('America/Sao_Paulo')
        ->and($updated->starts_at->format('H:i'))->toBe('11:00');
});

it('normalizes reminder_enabled boolean input on create and update', function () {
    [$owner, $tenantId, $unitId, $customer, $professional, $service] = calendarWorkspace();
    $headers = $this->withHeader('X-Tenant-Id', $tenantId)->withHeader('X-Unit-Id', $unitId)->actingAs($owner);

    $payloadWithOn = [
        'customer_id' => $customer->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => '2030-02-10 10:00',
        'duration_minutes' => 60,
        'reminder_enabled' => 'on',
    ];

    $headers->post(route('appointments.store'), $payloadWithOn)->assertRedirect();
    $firstAppointment = Appointment::query()->firstOrFail();
    expect($firstAppointment->reminder_enabled)->toBeTrue();

    $payloadWithZero = [
        'customer_id' => $customer->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => '2030-02-10 12:00',
        'duration_minutes' => 60,
        'reminder_enabled' => '0',
    ];

    $headers->post(route('appointments.store'), $payloadWithZero)->assertRedirect();
    $secondAppointment = Appointment::query()->where('id', '!=', $firstAppointment->getKey())->firstOrFail();
    expect($secondAppointment->reminder_enabled)->toBeFalse();

    $headers->put(route('appointments.update', $firstAppointment), [
        'customer_id' => $customer->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => '2030-02-10 10:00',
        'duration_minutes' => 60,
        'reminder_enabled' => '0',
        'lock_version' => 0,
    ])->assertRedirect();

    expect($firstAppointment->fresh()->reminder_enabled)->toBeFalse();
});
