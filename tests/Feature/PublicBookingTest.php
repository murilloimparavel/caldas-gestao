<?php

use App\Jobs\SyncGoogleCalendarAppointment;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AvailabilityRule;
use App\Models\OnlineBookingCampaignLink;
use App\Models\OnlineBookingPublication;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\OnlineBookingVisit;
use App\Models\OutboxEvent;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
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
    expect(OnlineBookingVisit::query()->count())->toBe(1)
        ->and(OnlineBookingVisit::query()->firstOrFail()->visitor_hash)->toHaveLength(64);
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
            'appearance' => [
                'brand_name' => 'Atelier Público',
                'headline' => 'Reserve agora',
                'primary_color' => '#D4AF37',
                'background_color' => '#0D0D0C',
            ],
            'identity' => ['cover_image_path' => null],
            'service_ids' => [$service->getKey()],
            'professional_ids' => [$professional->getKey()],
            'gallery' => [],
            'sections' => [
                ['key' => 'gallery', 'enabled' => false],
                ['key' => 'hours', 'enabled' => true],
            ],
        ],
    ]);
    $site->update(['active_publication_id' => $publication->getKey()]);

    $response = $this->getJson(route('public_booking.show', [$tenant, $unit]));

    $response->assertSuccessful()->assertHeader('ETag');
    $this->withHeader('If-None-Match', $response->headers->get('ETag'))
        ->getJson(route('public_booking.show', [$tenant, $unit]))
        ->assertNotModified();
    expect(collect($response->json('services'))->pluck('id')->all())->toBe([$service->getKey()]);
    expect($response->json('unit.sections.gallery'))->toBeFalse()
        ->and($response->json('unit.sections.services'))->toBeTrue()
        ->and($response->json('unit.appearance.brand_name'))->toBe('Atelier Público')
        ->and($response->json('settings.appearance.headline'))->toBe('Reserve agora');
});

it('can roll back public reads to the legacy settings resolver', function () {
    [$tenant, $unit, $service, $professional] = publicBookingWorkspace();
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
        'content_hash' => hash('sha256', 'rollback-snapshot'),
        'template_key' => 'essential',
        'public_slug' => $unit->slug,
        'published_at' => now(),
        'published_by' => User::factory()->create()->getKey(),
        'content' => ['service_ids' => [], 'professional_ids' => []],
    ]);
    $site->update(['active_publication_id' => $publication->getKey()]);
    config(['online_booking.use_publication_resolver' => false]);

    $response = $this->getJson(route('public_booking.show', [$tenant, $unit]));

    $response->assertSuccessful();
    expect(collect($response->json('services'))->pluck('id')->all())->toContain($service->getKey())
        ->and($response->headers->get('ETag'))->toBeNull()
        ->and($professional->getKey())->not->toBeEmpty();
});

it('exposes the published visual template in the public payload', function () {
    [$tenant, $unit, $service, $professional] = publicBookingWorkspace();
    $site = OnlineBookingSite::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'status' => 'published',
        'template_key' => 'atelier-barber',
    ]);
    $publication = OnlineBookingPublication::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'site_id' => $site->getKey(),
        'version' => 1,
        'source_revision' => 1,
        'content_hash' => hash('sha256', 'atelier-barber'),
        'template_key' => 'atelier-barber',
        'public_slug' => $unit->slug,
        'published_at' => now(),
        'published_by' => User::factory()->create()->getKey(),
        'content' => ['service_ids' => [$service->getKey()], 'professional_ids' => [$professional->getKey()]],
    ]);
    $site->update(['active_publication_id' => $publication->getKey()]);

    $this->getJson(route('public_booking.show', [$tenant, $unit]))
        ->assertSuccessful()
        ->assertJsonPath('unit.template_key', 'atelier-barber');
});

it('rejects disabled public booking and invalid relationship without enumeration', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $unit->update(['online_booking_enabled' => false]);
    $this->getJson(route('public_booking.show', [$tenant, $unit]))->assertNotFound();

    $unit->update(['online_booking_enabled' => true]);
    $otherService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'online_booking_enabled' => true]);
    $this->getJson(route('public_booking.availability', [$tenant, $unit, 'service_id' => $otherService->getKey(), 'professional_id' => $professional->getKey(), 'date' => $date->toDateString()]))->assertNotFound();
});

it('enforces the active publication catalog for availability and creation', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $hiddenService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'online_booking_enabled' => true]);
    $hiddenProfessional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'online_booking_enabled' => true]);
    $hiddenProfessional->services()->attach($hiddenService, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $site = OnlineBookingSite::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'public_slug' => $unit->slug, 'status' => 'published']);
    $publication = OnlineBookingPublication::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'site_id' => $site->getKey(),
        'version' => 1, 'source_revision' => 1, 'content_hash' => hash('sha256', 'catalog-lock'),
        'template_key' => 'essential', 'public_slug' => $unit->slug, 'published_at' => now(), 'published_by' => User::factory()->create()->getKey(),
        'content' => ['service_ids' => [$service->getKey()], 'professional_ids' => [$professional->getKey()]],
    ]);
    $site->update(['active_publication_id' => $publication->getKey()]);

    $this->getJson(route('public_booking.availability', [$tenant, $unit, 'service_id' => $hiddenService->getKey(), 'professional_id' => $hiddenProfessional->getKey(), 'date' => $date->toDateString()]))->assertNotFound();
    $this->withHeader('X-Idempotency-Key', 'catalog-bypass')
        ->postJson(route('public_booking.appointments.store', [$tenant, $unit]), [
            'service_id' => $hiddenService->getKey(), 'professional_id' => $hiddenProfessional->getKey(),
            'starts_at' => $date->setTime(9, 0)->toIso8601String(), 'name' => 'Bypass', 'phone' => '+55 11 98888-0000',
        ])
        ->assertNotFound();
    expect(Appointment::query()->count())->toBe(0);
});

it('persists optional customer and appointment data and records operational side effects', function () {
    Queue::fake();
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();

    $this->withHeader('X-Idempotency-Key', 'optional-data')
        ->postJson(route('public_booking.appointments.store', [$tenant, $unit]), [
            'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(),
            'starts_at' => $date->setTime(9, 0)->toIso8601String(), 'name' => 'Optional Data', 'phone' => '+55 11 97777-0000',
            'email' => 'optional@example.com', 'notes' => 'Prefere atendimento silencioso.',
        ])
        ->assertCreated();

    $appointment = Appointment::query()->with('customer')->sole();
    expect($appointment->customer->email)->toBe('optional@example.com')
        ->and($appointment->notes)->toBe('Prefere atendimento silencioso.')
        ->and(AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->where('action', 'created')->exists())->toBeTrue()
        ->and(OutboxEvent::query()->where('event_type', 'appointment.created')->where('aggregate_id', $appointment->getKey())->exists())->toBeTrue();
    Queue::assertPushed(SyncGoogleCalendarAppointment::class, fn (SyncGoogleCalendarAppointment $job): bool => $job->appointmentId === $appointment->getKey());
});

it('resolves the canonical public slug from the publication site registry', function () {
    [$tenant, $unit] = publicBookingWorkspace();
    OnlineBookingSite::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => 'published-'.$unit->slug,
    ]);

    $this->getJson(route('public_booking.slug', ['public_slug' => 'published-'.$unit->slug]))->assertSuccessful();
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

it('attributes a public appointment to the matching campaign link', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $site = OnlineBookingSite::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'public_slug' => $unit->slug]);
    $campaign = OnlineBookingCampaignLink::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'site_id' => $site->getKey(), 'created_by' => User::factory()->create()->getKey(),
        'utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'setembro',
    ]);
    $payload = ['service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => $date->setTime(9, 0)->toIso8601String(), 'name' => 'Campaign Customer', 'phone' => '+55 (11) 96666-1234'];

    $response = $this->withHeader('X-Idempotency-Key', 'campaign-booking-1')->postJson(route('public_booking.appointments.store', [$tenant, $unit]).'?utm_source=instagram&utm_medium=social&utm_campaign=setembro', $payload);

    $response->assertCreated();
    expect(Appointment::query()->firstOrFail()->online_booking_campaign_link_id)->toBe($campaign->getKey());
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
