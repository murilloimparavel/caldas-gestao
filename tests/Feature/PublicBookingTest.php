<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\OnlineBookingPublication;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** @return array{0: Tenant, 1: Unit, 2: Service, 3: Professional, 4: CarbonImmutable} */
function publicBookingWorkspace(): array
{
    $tenant = Tenant::factory()->create(['slug' => 'public-'.Str::lower(Str::random(8))]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->getKey(), 'slug' => 'unit-'.Str::lower(Str::random(8)), 'online_booking_enabled' => true]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'online_booking_enabled' => true]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'online_booking_enabled' => true]);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $date = CarbonImmutable::now($unit->timezone)->addDays(7)->startOfDay();
    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(),
        'weekday' => $date->dayOfWeek, 'starts_at' => '09:00:00', 'ends_at' => '18:00:00', 'timezone' => $unit->timezone,
    ]);

    return [$tenant, $unit, $service, $professional, $date];
}

it('publishes only opted-in catalog data and isolates tenant units', function () {
    [$tenant, $unit, $service, $professional] = publicBookingWorkspace();
    $hiddenService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'online_booking_enabled' => false]);
    Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'online_booking_enabled' => false]);
    $otherTenant = Tenant::factory()->create(['slug' => 'other-'.Str::lower(Str::random(8))]);
    Unit::factory()->create(['tenant_id' => $otherTenant->getKey(), 'slug' => $unit->slug, 'online_booking_enabled' => true]);

    $response = $this->getJson(route('public_booking.show', [$tenant, $unit]));

    $response->assertSuccessful()->assertJsonPath('unit.slug', $unit->slug)->assertJsonMissing(['id' => $hiddenService->getKey()]);
    expect($response->json('services.0.professionals.0.id'))->toBe($professional->getKey())
        ->and($response->json('unit'))->not->toHaveKey('email');
});

it('serves the selected catalog from the active publication snapshot', function () {
    [$tenant, $unit, $service, $professional] = publicBookingWorkspace();
    $newService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => true,
    ]);
    $newService->professionals()->attach($professional, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $site = OnlineBookingSite::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'status' => 'published',
    ]);
    $publication = OnlineBookingPublication::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'site_id' => $site->getKey(),
        'version' => 1,
        'source_revision' => 1,
        'content_hash' => hash('sha256', 'snapshot'),
        'template_key' => 'essential',
        'public_slug' => $unit->slug,
        'published_at' => now(),
        'published_by' => User::factory()->create()->getKey(),
        'content' => [
            'schema_version' => 1,
            'identity' => ['cover_image_path' => null],
            'service_ids' => [$service->getKey()],
            'professional_ids' => [$professional->getKey()],
            'gallery' => [],
        ],
    ]);
    $site->update(['active_publication_id' => $publication->getKey()]);

    $response = $this->getJson(route('public_booking.show', [$tenant, $unit]));

    $response->assertSuccessful();
    expect(collect($response->json('services'))->pluck('id')->all())->toBe([$service->getKey()]);
});

it('rejects disabled public booking and invalid relationship without enumeration', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $unit->update(['online_booking_enabled' => false]);
    $this->getJson(route('public_booking.show', [$tenant, $unit]))->assertNotFound();

    $unit->update(['online_booking_enabled' => true]);
    $otherService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'online_booking_enabled' => true]);
    $this->getJson(route('public_booking.availability', [$tenant, $unit, 'service_id' => $otherService->getKey(), 'professional_id' => $professional->getKey(), 'date' => $date->toDateString()]))->assertNotFound();
});

it('returns availability without blocked or conflicting slots', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    ScheduleBlock::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(),
        'starts_at' => $date->setTime(10, 0), 'ends_at' => $date->setTime(12, 0), 'timezone' => $unit->timezone,
    ]);

    $response = $this->getJson(route('public_booking.availability', [$tenant, $unit, 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'date' => $date->toDateString()]));
    $starts = collect($response->json('slots'))->pluck('starts_at');

    $response->assertSuccessful();
    expect($starts->filter(fn (string $start): bool => Str::contains($start, '10:'))->all())->toBeEmpty();
});

it('creates and replays a public appointment idempotently with a phone-scoped customer', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $payload = ['service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => $date->setTime(9, 0)->toIso8601String(), 'name' => 'Public Customer', 'phone' => '+55 (11) 99999-1234'];

    $first = $this->withHeader('X-Idempotency-Key', 'public-booking-1')->postJson(route('public_booking.appointments.store', [$tenant, $unit]), $payload);
    $second = $this->withHeader('X-Idempotency-Key', 'public-booking-1')->postJson(route('public_booking.appointments.store', [$tenant, $unit]), $payload);

    $first->assertCreated()->assertJsonPath('appointment.status', 'scheduled');
    $second->assertSuccessful()->assertJsonPath('replayed', true);
    expect(Appointment::query()->count())->toBe(1)
        ->and(Appointment::query()->firstOrFail()->source)->toBe('online')
        ->and(Appointment::query()->firstOrFail()->customer->phone)->toBe('5511999991234');
});

it('rejects public appointments outside the unit timezone booking window', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $past = CarbonImmutable::now($unit->timezone)->subMinutes(5)->toIso8601String();
    $future = CarbonImmutable::now($unit->timezone)->addDays(32)->toIso8601String();
    $payload = fn (string $startsAt): array => [
        'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(),
        'starts_at' => $startsAt, 'name' => 'Window Customer', 'phone' => '+55 11 98888-0000',
    ];

    $this->withHeader('X-Idempotency-Key', 'public-past')->postJson(route('public_booking.appointments.store', [$tenant, $unit]), $payload($past))->assertUnprocessable();
    $this->withHeader('X-Idempotency-Key', 'public-future')->postJson(route('public_booking.appointments.store', [$tenant, $unit]), $payload($future))->assertUnprocessable();
    expect(Appointment::query()->count())->toBe(0);
});

it('reuses a customer by normalized phone across separate public bookings', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $payload = fn (string $startsAt, string $phone): array => [
        'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(),
        'starts_at' => $startsAt, 'name' => 'Returning Customer', 'phone' => $phone,
    ];

    $this->withHeader('X-Idempotency-Key', 'public-customer-1')->postJson(route('public_booking.appointments.store', [$tenant, $unit]), $payload($date->setTime(9, 0)->toIso8601String(), '+55 (11) 97777-1234'))->assertCreated();
    $this->withHeader('X-Idempotency-Key', 'public-customer-2')->postJson(route('public_booking.appointments.store', [$tenant, $unit]), $payload($date->setTime(11, 0)->toIso8601String(), '5511977771234'))->assertCreated();

    expect(Appointment::query()->count())->toBe(2)
        ->and(Appointment::query()->pluck('customer_id')->unique())->toHaveCount(1);
});

it('applies public hours and minimum notice to availability', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    OnlineBookingSetting::create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'public_slug' => 'hours-'.Str::random(8),
        'minimum_notice_minutes' => 120,
        'public_hours' => [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '11:00', 'ends_at' => '12:00']],
    ]);

    $response = $this->getJson(route('public_booking.availability', [$tenant, $unit, 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'date' => $date->toDateString()]));
    $response->assertSuccessful();
    expect($response->json('slots'))->each->toHaveKey('starts_at');
    expect(collect($response->json('slots'))->every(fn (array $slot): bool => str_contains($slot['starts_at'], '11:')))->toBeTrue();
});
