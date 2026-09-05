<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;

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
