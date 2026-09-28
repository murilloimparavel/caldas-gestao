<?php

use App\Enums\OnlineBookingHandleStatus;
use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Models\OnlineBookingHandle;
use App\Models\OnlineBookingPublication;
use App\Models\OnlineBookingSite;
use App\Models\TenantDomain;
use App\Models\Unit;
use Carbon\CarbonInterface;

it('does not expose application routes on the shared booking host', function (): void {
    $this->get('http://barber.caldasindica.com/dashboard')->assertNotFound();
    $this->get('http://barber.caldasindica.com/')->assertNotFound();
});

it('does not choose an arbitrary unit when a custom booking domain has multiple units', function (): void {
    $domain = TenantDomain::factory()->create([
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    Unit::factory()->create([
        'tenant_id' => $domain->tenant_id,
        'online_booking_enabled' => true,
        'status' => 'active',
    ]);
    Unit::factory()->create([
        'tenant_id' => $domain->tenant_id,
        'online_booking_enabled' => true,
        'status' => 'active',
    ]);

    $this->get('http://'.$domain->hostname.'/')->assertNotFound();
});

it('serves a current handle on the shared booking host', function (): void {
    [, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $service->update(['online_booking_enabled' => true]);
    $professional->update(['online_booking_enabled' => true]);
    $handle = OnlineBookingHandle::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'handle' => 'current-booking',
        'status' => OnlineBookingHandleStatus::Current,
    ]);

    $this->getJson('http://barber.caldasindica.com/'.$handle->handle)
        ->assertSuccessful()
        ->assertJsonPath('unit.slug', $unit->slug);
});

it('never serves a reserved or expired handle', function (): void {
    [, $tenant, $unit] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    OnlineBookingHandle::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'handle' => 'reserved-booking',
        'status' => OnlineBookingHandleStatus::Reserved,
    ]);
    OnlineBookingHandle::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'handle' => 'expired-booking',
        'status' => OnlineBookingHandleStatus::Redirect,
        'redirect_until' => now()->subMinute(),
    ]);

    $this->getJson('http://barber.caldasindica.com/reserved-booking')->assertNotFound();
    $this->getJson('http://barber.caldasindica.com/expired-booking')->assertNotFound();
});

it('redirects an active renamed handle to the current shared destination', function (): void {
    [, $tenant, $unit] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    OnlineBookingHandle::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'handle' => 'old-booking',
        'status' => OnlineBookingHandleStatus::Redirect,
        'redirect_until' => now()->addDays(2),
    ]);
    OnlineBookingHandle::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'handle' => 'new-booking',
        'status' => OnlineBookingHandleStatus::Current,
    ]);

    $oldHandle = OnlineBookingHandle::query()->where('handle', 'old-booking')->firstOrFail();
    expect($oldHandle->status)->toBe(OnlineBookingHandleStatus::Redirect)
        ->and($oldHandle->redirect_until)->toBeInstanceOf(CarbonInterface::class)
        ->and($oldHandle->isRedirectActive())->toBeTrue();
    $redirect = $this->get('http://barber.caldasindica.com/old-booking?utm_source=instagram&utm_medium=profile&utm_campaign=launch&utm_term=barber&utm_content=bio&utm_id=discard')
        ->assertStatus(302)
        ->assertHeader('Location', 'http://barber.caldasindica.com/new-booking?utm_source=instagram&utm_medium=profile&utm_campaign=launch&utm_term=barber&utm_content=bio');
    expect((string) $redirect->headers->get('Cache-Control'))->toContain('no-store');

    $this->get('http://barber.caldasindica.com/book/old-booking')
        ->assertStatus(302)
        ->assertHeader('Location', 'http://barber.caldasindica.com/new-booking');
});

it('serves a reclaimed handle for its new owner instead of its former redirect', function (): void {
    [, $oldTenant, $oldUnit] = onlineBookingWorkspace();
    [, $newTenant, $newUnit] = onlineBookingWorkspace();
    $oldUnit->update(['online_booking_enabled' => true]);
    $newUnit->update(['online_booking_enabled' => true]);
    $handle = OnlineBookingHandle::query()->create([
        'tenant_id' => $oldTenant->getKey(),
        'unit_id' => $oldUnit->getKey(),
        'handle' => 'reclaimed-booking',
        'status' => OnlineBookingHandleStatus::Current,
    ]);
    $handle->forceFill([
        'tenant_id' => $newTenant->getKey(),
        'unit_id' => $newUnit->getKey(),
        'status' => OnlineBookingHandleStatus::Current,
    ])->save();

    $this->getJson('http://barber.caldasindica.com/reclaimed-booking')
        ->assertSuccessful()
        ->assertJsonPath('unit.slug', $newUnit->slug);
});

it('redirects legacy handles to the root of their active custom domain', function (): void {
    [, $tenant, $unit] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $domain = TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'hostname' => 'agenda.example.test',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    OnlineBookingSite::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_domain_id' => $domain->getKey(),
        'public_slug' => 'new-custom-booking',
    ]);
    OnlineBookingHandle::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'handle' => 'old-custom-booking',
        'status' => OnlineBookingHandleStatus::Redirect,
        'redirect_until' => now()->addDay(),
    ]);
    OnlineBookingHandle::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'handle' => 'new-custom-booking',
        'status' => OnlineBookingHandleStatus::Current,
    ]);

    $this->get('http://agenda.example.test/book/old-custom-booking?utm_source=google&utm_medium=organic&utm_campaign=brand')
        ->assertStatus(302)
        ->assertHeader('Location', 'http://agenda.example.test/?utm_source=google&utm_medium=organic&utm_campaign=brand');
});

it('canonicalizes shared and legacy paths to the configured custom-domain root', function (): void {
    [, $tenant, $unit] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $domain = TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'hostname' => 'canonical.example.test',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    OnlineBookingSite::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_domain_id' => $domain->getKey(),
        'public_slug' => 'canonical-booking',
    ]);
    OnlineBookingHandle::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'handle' => 'canonical-booking',
        'status' => OnlineBookingHandleStatus::Current,
    ]);

    $this->get('http://barber.caldasindica.com/canonical-booking?utm_source=instagram')
        ->assertStatus(302)
        ->assertHeader('Location', 'http://canonical.example.test/?utm_source=instagram');

    $this->get('http://canonical.example.test/book/canonical-booking')
        ->assertStatus(302)
        ->assertHeader('Location', 'http://canonical.example.test/');
});

it('serves a published custom domain at its root and rejects another public domain', function (): void {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $customDomain = TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'hostname' => 'agenda-root.example.test',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    $wrongDomain = TenantDomain::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'hostname' => 'wrong-root.example.test',
        'kind' => TenantDomainKind::Public,
        'status' => TenantDomainStatus::Active,
    ]);
    $site = OnlineBookingSite::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_domain_id' => $customDomain->getKey(),
        'public_slug' => 'root-booking',
        'status' => 'published',
    ]);
    $publication = OnlineBookingPublication::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'site_id' => $site->getKey(),
        'public_domain_id' => $customDomain->getKey(),
        'version' => 1,
        'source_revision' => 1,
        'content_hash' => hash('sha256', 'custom-root'),
        'template_key' => 'essential',
        'public_slug' => 'root-booking',
        'published_at' => now(),
        'published_by' => $owner->getKey(),
        'content' => [
            'service_ids' => [$service->getKey()],
            'professional_ids' => [$professional->getKey()],
        ],
    ]);
    $site->update(['active_publication_id' => $publication->getKey()]);

    $this->getJson('http://'.$customDomain->hostname.'/')
        ->assertSuccessful()
        ->assertJsonPath('unit.slug', $unit->slug);

    $this->getJson('http://'.$wrongDomain->hostname.'/book/root-booking')->assertNotFound();
});
