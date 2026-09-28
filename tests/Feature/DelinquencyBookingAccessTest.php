<?php

use App\Actions\Identity\OnboardTenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\SyncGoogleCalendarAppointment;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use App\Models\OnlineBookingSetting;
use App\Models\PlatformPlan;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit} */
function delinquencyBookingWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Delinquency '.Str::random(8),
        'slug' => 'delinquency-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $unit->update(['online_booking_enabled' => true]);

    return [$owner, $tenant, $unit];
}

function delinquencySubscription(Tenant $tenant, string $status = 'expired'): TenantSubscription
{
    $plan = PlatformPlan::factory()->create();

    return TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'platform_plan_id' => $plan->getKey(),
        'status' => $status,
        'ends_at' => $status === 'active' ? now()->addDay() : now()->subMinute(),
        'grace_ends_at' => null,
    ]);
}

it('keeps public booking available while the tenant subscription is delinquent', function (): void {
    [, $tenant, $unit] = delinquencyBookingWorkspace();
    delinquencySubscription($tenant);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => true,
    ]);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => true,
    ]);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $response = $this->getJson(route('public_booking.show', [$tenant, $unit]));

    $response->assertSuccessful()->assertJsonPath('unit.slug', $unit->slug);
    expect($response->json('services.0.id'))->toBe($service->getKey());
});

it('allows a delinquent tenant to create a public booking', function (): void {
    [, $tenant, $unit] = delinquencyBookingWorkspace();
    delinquencySubscription($tenant);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => true,
    ]);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => true,
    ]);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $timezone = $unit->timezone ?? $tenant->timezone ?? config('app.timezone');
    $date = CarbonImmutable::now($timezone)->addDays(7)->startOfDay();
    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'weekday' => $date->dayOfWeek,
        'starts_at' => '09:00:00',
        'ends_at' => '18:00:00',
        'timezone' => $timezone,
    ]);
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'minimum_notice_minutes' => 0,
        'public_hours' => [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '18:00']],
    ]);
    Queue::fake();

    $response = $this->withHeader('X-Idempotency-Key', 'delinquent-public-booking')
        ->postJson(route('public_booking.appointments.store', [$tenant, $unit]), [
            'service_id' => $service->getKey(),
            'professional_id' => $professional->getKey(),
            'starts_at' => $date->setTime(9, 0)->toIso8601String(),
            'name' => 'Public Customer',
            'phone' => '+55 11 98888-0000',
        ]);

    $response->assertCreated()->assertJsonPath('appointment.status', 'scheduled');
    expect(Appointment::query()->sole()->source)->toBe('online');
    Queue::assertPushed(SyncGoogleCalendarAppointment::class);
});

it('returns only an aggregate online booking count in the billing payload', function (): void {
    [$owner, $tenant, $unit] = delinquencyBookingWorkspace();
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $onlineAppointment = Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'source' => 'online',
        'status' => 'scheduled',
    ]);
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'source' => 'online',
        'status' => 'confirmed',
    ]);
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'source' => 'online',
        'status' => 'scheduled',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addMinutes(30),
    ]);
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'source' => 'online',
        'status' => 'completed',
    ]);
    $otherTenant = Tenant::factory()->create();
    $otherTenantUnit = Unit::factory()->create(['tenant_id' => $otherTenant->getKey()]);
    Appointment::factory()->create([
        'tenant_id' => $otherTenant->getKey(),
        'unit_id' => $otherTenantUnit->getKey(),
        'source' => 'online',
        'status' => 'confirmed',
    ]);
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'source' => 'online',
        'status' => 'cancelled',
    ]);
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'source' => 'internal',
        'status' => 'confirmed',
    ]);

    $response = $this->withoutMiddleware(HandleInertiaRequests::class)
        ->actingAs($owner)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('billing.index'));

    $response->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('props.onlineBookingCount', 2)
        ->assertJsonMissingPath('props.appointments')
        ->assertJsonMissingPath('props.customers')
        ->assertJsonMissingPath('props.online_booking_details');
    expect($response->getContent())
        ->not->toContain($onlineAppointment->getKey())
        ->not->toContain($onlineAppointment->customer->name);
});

it('does not call Google Calendar for a delinquent tenant', function (): void {
    [, $tenant, $unit] = delinquencyBookingWorkspace();
    delinquencySubscription($tenant);
    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'source' => 'online',
    ]);
    GoogleCalendarConnection::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    Http::fake();

    (new SyncGoogleCalendarAppointment((string) $appointment->getKey()))->handle();

    Http::assertNothingSent();
    expect(GoogleCalendarEvent::query()->count())->toBe(0);
});

it('redirects owners away from appointment details while delinquent', function (): void {
    [$owner, $tenant, $unit] = delinquencyBookingWorkspace();
    delinquencySubscription($tenant);

    $this->actingAs($owner)
        ->get(route('calendar.index'))
        ->assertRedirect(route('billing.index'));
});
