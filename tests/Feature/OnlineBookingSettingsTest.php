<?php

use App\Actions\OnlineBooking\EnsureOnlineBookingSite;
use App\Actions\OnlineBooking\PublishOnlineBookingSite;
use App\Actions\OnlineBooking\SaveOnlineBookingDraft;
use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Models\OnlineBookingSite;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

it('requires authentication and exposes scoped readiness to the owner', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();

    $this->get(route('online_booking.index'))->assertRedirect(route('login'));

    $this->actingAs($owner)->getJson(route('online_booking.index'))->assertSuccessful()
        ->assertJsonPath('unit.id', $unit->getKey())
        ->assertJsonPath('tenant.slug', $tenant->slug)
        ->assertJsonPath('readiness.publishable', false)
        ->assertJsonPath('publicUrl', null)
        ->assertJsonCount(1, 'services')
        ->assertJsonCount(1, 'professionals');
});

it('updates publish flags, publishes the public link only for a valid pair, and preserves opt-ins while disabling the unit', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $payload = [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'lock_version' => $unit->lock_version,
    ];

    $this->actingAs($owner)->withHeader('X-Idempotency-Key', 'online-booking-enable')
        ->patch(route('online_booking.update'), $payload)->assertRedirect(route('online_booking.index'));

    expect($unit->fresh()->online_booking_enabled)->toBeTrue()
        ->and($unit->fresh()->lock_version)->toBe(1)
        ->and($service->fresh()->online_booking_enabled)->toBeTrue()
        ->and($professional->fresh()->online_booking_enabled)->toBeTrue();

    $this->actingAs($owner)->getJson(route('online_booking.index'))
        ->assertJsonPath('readiness.publishable', true)
        ->assertJsonPath('publicUrl', null)
        ->assertJsonPath('canonicalUrl', null);

    $this->flushHeaders()->actingAs($owner)->patch(route('online_booking.update'), [
        ...$payload,
        'online_booking_enabled' => false,
        'lock_version' => 1,
    ])->assertRedirect();

    expect($unit->fresh()->online_booking_enabled)->toBeFalse()
        ->and($service->fresh()->online_booking_enabled)->toBeTrue()
        ->and($professional->fresh()->online_booking_enabled)->toBeTrue();

    $this->flushHeaders()->actingAs($owner)->patch(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'lock_version' => $unit->fresh()->lock_version,
    ])->assertRedirect();

    expect($service->fresh()->online_booking_enabled)->toBeFalse()
        ->and($professional->fresh()->online_booking_enabled)->toBeFalse();
});

it('preserves editor section visibility when legacy settings are synchronized', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'sections' => [['key' => 'gallery', 'enabled' => false]],
    ], 0);

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => $unit->slug,
        'lock_version' => $unit->lock_version,
    ])->assertRedirect();

    $draft = OnlineBookingSite::query()->where('unit_id', $unit->getKey())->firstOrFail()->draft;
    expect($draft?->content['sections'])->toBe([
        ['key' => 'gallery', 'enabled' => false],
    ]);

    $diff = $this->actingAs($owner)->getJson(route('online_booking.index'))->json('draftDiff');
    expect($diff)->toContain('Seções visíveis');
    expect($this->actingAs($owner)->getJson(route('online_booking.index'))->json('previewUrl'))->toContain('expires=');
});

it('rejects stale versions and cross-scope selections', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignUnit = Unit::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $foreignService = Service::factory()->create(['tenant_id' => $foreignTenant->getKey(), 'unit_id' => $foreignUnit->getKey()]);

    $payload = [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'lock_version' => $unit->lock_version,
    ];

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        ...$payload,
        'service_ids' => [$foreignService->getKey()],
    ])->assertRedirect()->assertSessionHasErrors('service_ids.0');

    $this->actingAs($owner)->patch(route('online_booking.update'), $payload)->assertRedirect();
    $this->actingAs($owner)->patch(route('online_booking.update'), $payload)->assertStatus(409);
});

it('uses an active public tenant domain for the booking link', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $site = app(EnsureOnlineBookingSite::class)->handle($context);
    $site->draft->forceFill(['revision' => 4])->save();
    $site->forceFill(['draft_revision' => 3])->save();

    $domain = TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'hostname' => 'romawear.com.br',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    $payload = [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_domain_id' => $domain->getKey(),
        'lock_version' => (string) $unit->lock_version,
    ];

    $this->actingAs($owner)->patch(route('online_booking.update'), $payload)->assertRedirect();

    $site->refresh();

    expect($site->public_domain_id)->toBe($domain->getKey())
        ->and($site->draft_revision)->toBe(5)
        ->and($site->draft->revision)->toBe(5);

    $this->actingAs($owner)->getJson(route('online_booking.index'))
        ->assertJsonPath('settings.public_domain_id', $domain->getKey())
        ->assertJsonPath('publicUrl', null)
        ->assertJsonPath('canonicalUrl', null);
});

it('keeps the published slug and domain live until the edited link is republished', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $firstDomain = TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'hostname' => 'published-a.example.com',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    $secondDomain = TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'hostname' => 'published-b.example.com',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    $settingsPayload = [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => 'published-old-link',
        'public_domain_id' => $firstDomain->getKey(),
        'lock_version' => $unit->lock_version,
    ];

    $this->actingAs($owner)->patch(route('online_booking.update'), $settingsPayload)->assertRedirect();
    $site = OnlineBookingSite::query()->where('tenant_id', $tenant->getKey())->where('unit_id', $unit->getKey())->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    app(PublishOnlineBookingSite::class)->handle($owner, $context, $site->draft_revision);

    $firstLiveUrl = 'https://'.$firstDomain->hostname.'/';
    $this->actingAs($owner)->getJson(route('online_booking.index'))
        ->assertSuccessful()
        ->assertJsonPath('publicUrl', $firstLiveUrl)
        ->assertJsonPath('canonicalUrl', $firstLiveUrl);
    $this->getJson($firstLiveUrl)->assertSuccessful();

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        ...$settingsPayload,
        'public_slug' => 'draft-new-link',
        'public_domain_id' => $secondDomain->getKey(),
        'lock_version' => $unit->fresh()->lock_version,
    ])->assertRedirect();

    $this->actingAs($owner)->getJson(route('online_booking.index'))
        ->assertSuccessful()
        ->assertJsonPath('settings.public_slug', 'draft-new-link')
        ->assertJsonPath('publicUrl', $firstLiveUrl)
        ->assertJsonPath('canonicalUrl', $firstLiveUrl);
    $secondLiveUrl = 'https://'.$secondDomain->hostname.'/';
    $this->getJson($firstLiveUrl)->assertSuccessful();
    $this->getJson('https://'.$secondDomain->hostname.'/book/published-old-link')->assertNotFound();
    $this->getJson('https://'.$secondDomain->hostname.'/book/draft-new-link')->assertNotFound();
    $this->get('https://'.$firstDomain->hostname.'/book/published-old-link')
        ->assertStatus(302)
        ->assertHeader('Location', $firstLiveUrl);
    $this->get('https://'.$secondDomain->hostname.'/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('public/coming-soon'));
    $this->get('https://'.$firstDomain->hostname.'/')->assertSuccessful();
    $previewUrl = $this->getJson(route('online_booking.index'))->json('previewUrl');
    $this->get($previewUrl)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public-booking/show')
            ->where('unit.canonical_url', 'https://barber.caldasindica.com/published-old-link'));

    $site->refresh();
    app(PublishOnlineBookingSite::class)->handle($owner, $context, $site->draft_revision);
    $this->actingAs($owner)->getJson(route('online_booking.index'))
        ->assertSuccessful()
        ->assertJsonPath('publicUrl', $secondLiveUrl)
        ->assertJsonPath('canonicalUrl', $secondLiveUrl);
    $this->getJson($secondLiveUrl)->assertSuccessful();
    $this->get('https://'.$firstDomain->hostname.'/book/published-old-link')
        ->assertStatus(302)
        ->assertHeader('Location', $secondLiveUrl);
    $this->get($firstLiveUrl)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('public/coming-soon'));
});

it('serves default publications on the management host but scopes custom domains to their public host', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $managementDomain = TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'hostname' => 'gestao.example.test',
        'kind' => TenantDomainKind::Management,
        'status' => TenantDomainStatus::Active,
    ]);
    $publicDomain = TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'hostname' => 'agenda.example.test',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    $payload = [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => 'default-booking-link',
        'lock_version' => $unit->lock_version,
    ];

    $this->actingAs($owner)->patch(route('online_booking.update'), $payload)->assertRedirect();
    $site = OnlineBookingSite::query()->where('tenant_id', $tenant->getKey())->where('unit_id', $unit->getKey())->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    app(PublishOnlineBookingSite::class)->handle($owner, $context, $site->draft_revision);

    $this->getJson('https://'.$managementDomain->hostname.'/book/default-booking-link')
        ->assertStatus(302)
        ->assertHeader('Location', 'https://barber.caldasindica.com/default-booking-link');

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        ...$payload,
        'public_slug' => 'custom-booking-link',
        'public_domain_id' => $publicDomain->getKey(),
        'lock_version' => $unit->fresh()->lock_version,
    ])->assertRedirect();
    $site->refresh();
    app(PublishOnlineBookingSite::class)->handle($owner, $context, $site->draft_revision);

    $this->getJson('https://'.$managementDomain->hostname.'/book/custom-booking-link')->assertNotFound();
    $this->getJson('https://'.$publicDomain->hostname.'/book/custom-booking-link')
        ->assertStatus(302)
        ->assertHeader('Location', 'https://'.$publicDomain->hostname.'/');
});

it('redirects stale Inertia saves with a recoverable message', function () {
    [$owner, , $unit, $service, $professional] = onlineBookingWorkspace();
    $payload = [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'lock_version' => $unit->lock_version,
    ];

    $this->actingAs($owner)->patch(route('online_booking.update'), $payload)->assertRedirect();

    $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->patch(route('online_booking.update'), $payload)
        ->assertRedirect(route('online_booking.index'))
        ->assertSessionHas('error', 'Esta tela estava desatualizada. Recarregamos as configurações atuais; revise e salve novamente.');
});

it('rejects a user without unit update permission', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('X-Tenant-Id', $tenant->getKey())
        ->withHeader('X-Unit-Id', $unit->getKey())
        ->get(route('online_booking.index'))
        ->assertForbidden();
});

it('keeps public booking unavailable without a published pair', function () {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);

    $this->getJson(route('public_booking.show', [$tenant, $unit]))->assertJsonMissingPath('services.0');
});

it('persists the selected visual template and exposes it to the editor', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'template_key' => 'atelier-barber',
        'lock_version' => $unit->lock_version,
    ])->assertRedirect();

    $site = OnlineBookingSite::query()->where('unit_id', $unit->getKey())->firstOrFail();

    expect($site->template_key)->toBe('atelier-barber')
        ->and($this->actingAs($owner)->getJson(route('online_booking.index'))->json('template_key'))->toBe('atelier-barber');
});

it('rejects unsupported visual templates', function () {
    [$owner, , $unit, $service, $professional] = onlineBookingWorkspace();

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'template_key' => 'unknown-template',
        'lock_version' => $unit->lock_version,
    ])->assertRedirect()->assertSessionHasErrors('template_key');
});
