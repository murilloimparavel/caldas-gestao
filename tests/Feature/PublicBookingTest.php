<?php

use App\Actions\OnlineBooking\PublishOnlineBookingSite;
use App\Actions\OnlineBooking\SaveOnlineBookingDraft;
use App\Jobs\SyncGoogleCalendarAppointment;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AvailabilityRule;
use App\Models\Category;
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
use App\Support\TenantContext;
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
    $timezone = $unit->timezone ?? $tenant->timezone ?? config('app.timezone');
    $date = CarbonImmutable::now($timezone)->addDays(7)->startOfDay();
    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(),
        'weekday' => $date->dayOfWeek, 'starts_at' => '09:00:00', 'ends_at' => '18:00:00', 'timezone' => $timezone,
    ]);

    return [$tenant, $unit, $service, $professional, $date];
}

function configurePublicBookingHours(Tenant $tenant, Unit $unit, CarbonImmutable $date): void
{
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'minimum_notice_minutes' => 0,
        'public_hours' => [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '18:00']],
    ]);
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

it('exposes service categories and professional presentation metadata', function () {
    [$tenant, $unit, $service, $professional] = publicBookingWorkspace();
    $category = Category::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Cortes']);
    $service->update(['category_id' => $category->getKey()]);

    $response = $this->getJson(route('public_booking.show', [$tenant, $unit]))->assertSuccessful();

    expect($response->json('services.0.category_id'))->toBe($category->getKey())
        ->and($response->json('services.0.category_name'))->toBe('Cortes')
        ->and($response->json('services.0.professionals.0.title'))->toBe($professional->name)
        ->and($response->json('services.0.professionals.0'))->toHaveKeys(['badge', 'description', 'next_available_at']);
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

it('uses legacy hours and notice when a publication omits those snapshot fields', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $legacyHours = [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '11:00', 'ends_at' => '12:00']];
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'minimum_notice_minutes' => 8 * 24 * 60,
        'public_hours' => $legacyHours,
    ]);
    $site = OnlineBookingSite::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'public_slug' => $unit->slug, 'status' => 'published',
    ]);
    $publication = OnlineBookingPublication::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'site_id' => $site->getKey(),
        'version' => 1, 'source_revision' => 1, 'content_hash' => hash('sha256', 'legacy-fields-fallback'),
        'template_key' => 'essential', 'public_slug' => $unit->slug, 'published_at' => now(), 'published_by' => User::factory()->create()->getKey(),
        'content' => ['service_ids' => [$service->getKey()], 'professional_ids' => [$professional->getKey()]],
    ]);
    $site->update(['active_publication_id' => $publication->getKey()]);

    $this->getJson(route('public_booking.show', [$tenant, $unit]))
        ->assertSuccessful()
        ->assertJsonPath('unit.public_hours.'.$date->dayOfWeek.'.enabled', true)
        ->assertJsonPath('unit.public_hours.'.$date->dayOfWeek.'.starts_at', '11:00')
        ->assertJsonPath('unit.public_hours.'.$date->dayOfWeek.'.ends_at', '12:00')
        ->assertJsonPath('unit.minimum_notice_minutes', 8 * 24 * 60);
    $blockedByFallbackNotice = $this->getJson(route('public_booking.availability', [
        $tenant, $unit, 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'date' => $date->toDateString(),
    ]))->assertSuccessful();
    $nextWeek = $date->addDays(7);
    $fallbackSlots = $this->getJson(route('public_booking.availability', [
        $tenant, $unit, 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'date' => $nextWeek->toDateString(),
    ]))->assertSuccessful();
    expect($blockedByFallbackNotice->json('slots'))->toBeEmpty()
        ->and(Str::contains($fallbackSlots->json('slots.0.starts_at'), 'T11:'))->toBeTrue();
    $this->withHeader('X-Idempotency-Key', 'snapshot-legacy-hours-fallback')
        ->postJson(route('public_booking.appointments.store', [$tenant, $unit]), [
            'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(),
            'starts_at' => $fallbackSlots->json('slots.0.starts_at'), 'name' => 'Legacy Fallback', 'phone' => '+55 11 95555-0000',
        ])
        ->assertCreated();

    $publication->update(['content' => [...$publication->content, 'public_hours' => []]]);
    $this->getJson(route('public_booking.availability', [
        $tenant, $unit, 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'date' => $nextWeek->toDateString(),
    ]))->assertSuccessful()->assertJsonPath('slots', []);
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

it('keeps catalog and availability on the active snapshot until the next publication', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $service->update(['online_booking_enabled' => true]);
    $professional->update(['online_booking_enabled' => true]);
    $timezone = $unit->timezone ?? $tenant->timezone ?? config('app.timezone');
    $date = CarbonImmutable::now($timezone)->addDays(7)->startOfDay();
    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(),
        'weekday' => $date->dayOfWeek, 'starts_at' => '09:00:00', 'ends_at' => '18:00:00', 'timezone' => $timezone,
    ]);
    $setting = OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'minimum_notice_minutes' => 0,
        'public_hours' => [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '12:00']],
    ]);
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_hours' => [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '12:00']],
        'booking_policy' => ['minimum_notice_minutes' => 0],
    ], 0);
    app(PublishOnlineBookingSite::class)->handle($owner, $context, $draft->revision);

    $newService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'online_booking_enabled' => false,
    ]);
    $professional->services()->attach($newService, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service->update(['online_booking_enabled' => false]);
    $professional->update(['online_booking_enabled' => false]);
    $setting->update([
        'minimum_notice_minutes' => 8 * 24 * 60,
        'public_hours' => [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '11:00', 'ends_at' => '12:00']],
    ]);
    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'service_ids' => [$newService->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_hours' => [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '11:00', 'ends_at' => '12:00']],
        'booking_policy' => ['minimum_notice_minutes' => 8 * 24 * 60],
    ], $draft->revision);

    $this->getJson(route('public_booking.show', [$tenant, $unit]))
        ->assertSuccessful()
        ->assertJsonPath('services.0.id', $service->getKey());
    $beforeRepublish = $this->getJson(route('public_booking.availability', [
        $tenant, $unit, 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'date' => $date->toDateString(),
    ]))->assertSuccessful();
    expect($beforeRepublish->json('slots.0.starts_at'))->toContain('T09:');
    $this->getJson(route('public_booking.availability', [
        $tenant, $unit, 'service_id' => $newService->getKey(), 'professional_id' => $professional->getKey(), 'date' => $date->toDateString(),
    ]))->assertNotFound();

    app(PublishOnlineBookingSite::class)->handle($owner, $context, $draft->revision);

    $this->getJson(route('public_booking.show', [$tenant, $unit]))
        ->assertSuccessful()
        ->assertJsonPath('services.0.id', $newService->getKey());
    $blockedByNotice = $this->getJson(route('public_booking.availability', [
        $tenant, $unit, 'service_id' => $newService->getKey(), 'professional_id' => $professional->getKey(), 'date' => $date->toDateString(),
    ]))->assertSuccessful();
    $nextWeek = $date->addDays(7);
    $updatedWindow = $this->getJson(route('public_booking.availability', [
        $tenant, $unit, 'service_id' => $newService->getKey(), 'professional_id' => $professional->getKey(), 'date' => $nextWeek->toDateString(),
    ]))->assertSuccessful();

    expect($blockedByNotice->json('slots'))->toBeEmpty()
        ->and($updatedWindow->json('slots.0.starts_at'))->toContain('T11:');
});

it('persists optional customer and appointment data and records operational side effects', function () {
    Queue::fake();
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    configurePublicBookingHours($tenant, $unit, $date);

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

it('returns availability using the combined duration for multiple services', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $secondService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => true,
        'duration_minutes' => 30,
    ]);
    $professional->services()->attach($secondService, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'public_hours' => [$date->dayOfWeek => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '18:00']],
    ]);

    $response = $this->getJson(route('public_booking.availability', [$tenant, $unit, 'service_ids' => [$service->getKey(), $secondService->getKey()], 'professional_id' => $professional->getKey(), 'date' => $date->toDateString(), 'from' => '09:00', 'to' => '10:30']));

    $response->assertSuccessful();
    expect($response->json('slots.0.ends_at'))->toBe($date->setTime(9, 0)->addMinutes($service->duration_minutes + $secondService->duration_minutes)->toIso8601String());
});

it('keeps availability request filters on the public 15-minute grid and rejects off-grid posts', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'public_slug' => $unit->slug,
        'minimum_notice_minutes' => 0,
        'public_hours' => [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '12:00']],
    ]);

    $slots = $this->getJson(route('public_booking.availability', [
        $tenant, $unit, 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(),
        'date' => $date->toDateString(), 'from' => '09:07', 'to' => '12:00',
    ]))->assertSuccessful();
    expect(Str::contains($slots->json('slots.0.starts_at'), 'T09:15:'))->toBeTrue();

    $this->withHeader('X-Idempotency-Key', 'off-grid-public-time')
        ->postJson(route('public_booking.appointments.store', [$tenant, $unit]), [
            'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(),
            'starts_at' => $date->setTime(9, 7)->toIso8601String(), 'name' => 'Off Grid', 'phone' => '+55 11 98888-0000',
        ])
        ->assertUnprocessable();
    $this->withHeader('X-Idempotency-Key', 'off-grid-offered-time')
        ->postJson(route('public_booking.appointments.store', [$tenant, $unit]), [
            'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(),
            'starts_at' => $slots->json('slots.0.starts_at'), 'name' => 'On Grid', 'phone' => '+55 11 97777-0000',
        ])
        ->assertCreated();
});

it('fails closed when no legacy or published public hours are configured', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    $this->getJson(route('public_booking.availability', [
        $tenant, $unit, 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'date' => $date->toDateString(),
    ]))->assertSuccessful()->assertJsonPath('slots', []);

    $this->withHeader('X-Idempotency-Key', 'missing-public-hours')
        ->postJson(route('public_booking.appointments.store', [$tenant, $unit]), [
            'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(),
            'starts_at' => $date->setTime(9, 0)->toIso8601String(), 'name' => 'No Hours', 'phone' => '+55 11 98888-1111',
        ])
        ->assertUnprocessable();
    expect(Appointment::query()->count())->toBe(0);
});

it('creates and replays a public appointment idempotently with a phone-scoped customer', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    configurePublicBookingHours($tenant, $unit, $date);
    $payload = ['service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => $date->setTime(9, 0)->toIso8601String(), 'name' => 'Public Customer', 'phone' => '+55 (11) 99999-1234'];

    $first = $this->withHeader('X-Idempotency-Key', 'public-booking-1')->postJson(route('public_booking.appointments.store', [$tenant, $unit]), $payload);
    $second = $this->withHeader('X-Idempotency-Key', 'public-booking-1')->postJson(route('public_booking.appointments.store', [$tenant, $unit]), $payload);

    $first->assertCreated()->assertJsonPath('appointment.status', 'scheduled');
    $second->assertSuccessful()->assertJsonPath('replayed', true);
    expect(Appointment::query()->count())->toBe(1)
        ->and(Appointment::query()->firstOrFail()->source)->toBe('online')
        ->and(Appointment::query()->firstOrFail()->customer->phone)->toBe('5511999991234');
});

it('creates one appointment with multiple compatible services', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    configurePublicBookingHours($tenant, $unit, $date);
    $secondService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => true,
        'duration_minutes' => 30,
        'price_cents' => 4500,
    ]);
    $professional->services()->attach($secondService, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $response = $this->withHeader('X-Idempotency-Key', 'multi-service')
        ->postJson(route('public_booking.appointments.store', [$tenant, $unit]), [
            'service_ids' => [$service->getKey(), $secondService->getKey()],
            'professional_id' => $professional->getKey(),
            'starts_at' => $date->setTime(9, 0)->toIso8601String(),
            'name' => 'Multi Service',
            'phone' => '+55 11 96666-0000',
        ])
        ->assertCreated();

    $appointment = Appointment::query()->sole();
    expect($response->json('appointment.status'))->toBe('scheduled')
        ->and($appointment->items()->count())->toBe(2)
        ->and((int) abs($appointment->ends_at->diffInMinutes($appointment->starts_at)))->toBe($service->duration_minutes + $secondService->duration_minutes);
});

it('attributes a public appointment to the matching campaign link', function () {
    [$tenant, $unit, $service, $professional, $date] = publicBookingWorkspace();
    configurePublicBookingHours($tenant, $unit, $date);
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
    configurePublicBookingHours($tenant, $unit, $date);
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

it('uses Carbon weekday keys for public hours with Monday enabled and Sunday disabled', function () {
    [$tenant, $unit, $service, $professional] = publicBookingWorkspace();
    $timezone = $unit->timezone ?? $tenant->timezone ?? config('app.timezone');
    $monday = CarbonImmutable::now($timezone)->addWeek()->startOfWeek()->startOfDay();
    $sunday = $monday->addDays(6);

    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'weekday' => 1,
        'starts_at' => '09:00:00',
        'ends_at' => '12:00:00',
        'timezone' => $timezone,
    ]);
    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'weekday' => 0,
        'starts_at' => '09:00:00',
        'ends_at' => '12:00:00',
        'timezone' => $timezone,
    ]);
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'minimum_notice_minutes' => 0,
        'public_hours' => [
            '0' => ['enabled' => false, 'starts_at' => '09:00', 'ends_at' => '12:00'],
            '1' => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '12:00'],
        ],
    ]);

    $mondayAvailability = $this->getJson(route('public_booking.availability', [
        $tenant,
        $unit,
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'date' => $monday->toDateString(),
    ]))->assertSuccessful();
    $sundayAvailability = $this->getJson(route('public_booking.availability', [
        $tenant,
        $unit,
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'date' => $sunday->toDateString(),
    ]))->assertSuccessful();

    expect($mondayAvailability->json('slots'))->not->toBeEmpty()
        ->and($sundayAvailability->json('slots'))->toBeEmpty();
});
