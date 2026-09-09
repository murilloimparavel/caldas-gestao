<?php

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
        ->assertJsonPath('publicUrl', route('public_booking.show', [$tenant, $unit]));

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
        'lock_version' => $unit->lock_version,
    ];

    $this->actingAs($owner)->patch(route('online_booking.update'), $payload)->assertRedirect();

    $this->actingAs($owner)->getJson(route('online_booking.index'))
        ->assertJsonPath('settings.public_domain_id', $domain->getKey())
        ->assertJsonPath('publicUrl', 'https://romawear.com.br/book/'.$unit->slug);
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
