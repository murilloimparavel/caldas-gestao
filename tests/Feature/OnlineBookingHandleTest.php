<?php

use App\Actions\OnlineBooking\ManageOnlineBookingHandle;
use App\Actions\OnlineBooking\PublishOnlineBookingSite;
use App\Enums\OnlineBookingHandleStatus;
use App\Models\OnlineBookingHandle;
use App\Models\OnlineBookingPublication;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;

it('reserves a draft handle and rejects a handle reserved by another unit', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $payload = [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => 'shared-handle',
        'lock_version' => $unit->lock_version,
    ];

    $this->actingAs($owner)->patch(route('online_booking.update'), $payload)->assertRedirect();

    expect(OnlineBookingHandle::query()->where('handle', 'shared-handle')->value('status'))
        ->toBe(OnlineBookingHandleStatus::Reserved);

    [$otherOwner, , $otherUnit, $otherService, $otherProfessional] = onlineBookingWorkspace();
    $this->actingAs($otherOwner)->patch(route('online_booking.update'), [
        ...$payload,
        'service_ids' => [$otherService->getKey()],
        'professional_ids' => [$otherProfessional->getKey()],
        'lock_version' => $otherUnit->lock_version,
    ])->assertRedirect()->assertSessionHasErrors('public_slug');
});

it('turns the published handle into a three-day redirect when a new handle is published', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $service->update(['online_booking_enabled' => true]);
    $professional->update(['online_booking_enabled' => true]);

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => 'old-handle',
        'lock_version' => $unit->lock_version,
    ])->assertRedirect();

    $site = OnlineBookingSite::query()->where('unit_id', $unit->getKey())->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    app(PublishOnlineBookingSite::class)->handle($owner, $context, $site->draft_revision);

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => 'new-handle',
        'lock_version' => $unit->fresh()->lock_version,
    ])->assertRedirect();

    $site->refresh();
    app(PublishOnlineBookingSite::class)->handle($owner, $context, $site->draft_revision);

    $redirect = OnlineBookingHandle::query()->where('handle', 'old-handle')->firstOrFail();
    expect($redirect->status)->toBe(OnlineBookingHandleStatus::Redirect)
        ->and($redirect->redirect_until->between(Carbon::now()->addDays(3)->subSecond(), Carbon::now()->addDays(3)->addSecond()))->toBeTrue();
});

it('releases an expired legacy handle for a new tenant', function () {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $handles = app(ManageOnlineBookingHandle::class);
    $handles->activateForPublication($tenant->getKey(), $unit->getKey(), null, 'expired-handle');
    OnlineBookingHandle::query()->where('handle', 'expired-handle')->update([
        'status' => OnlineBookingHandleStatus::Redirect->value,
        'redirect_until' => Carbon::now()->subSecond(),
    ]);

    [, $otherTenant, $otherUnit] = onlineBookingWorkspace();
    $newHandle = $handles->reserveForDraft($otherTenant->getKey(), $otherUnit->getKey(), 'expired-handle');

    expect($newHandle->tenant_id)->toBe($otherTenant->getKey())
        ->and($newHandle->status)->toBe(OnlineBookingHandleStatus::Reserved);
});

it('allows an expired foreign handle through settings validation and atomically reclaims it', function () {
    [, $formerTenant, $formerUnit] = onlineBookingWorkspace();
    $handles = app(ManageOnlineBookingHandle::class);
    $handles->activateForPublication($formerTenant->getKey(), $formerUnit->getKey(), null, 'reclaimable-handle');
    OnlineBookingHandle::query()->where('handle', 'reclaimable-handle')->update([
        'status' => OnlineBookingHandleStatus::Redirect->value,
        'redirect_until' => Carbon::now()->subSecond(),
    ]);

    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $this->actingAs($owner)->patch(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => 'reclaimable-handle',
        'lock_version' => $unit->lock_version,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $reclaimed = OnlineBookingHandle::query()->where('handle', 'reclaimable-handle')->firstOrFail();

    expect($reclaimed->tenant_id)->toBe($tenant->getKey())
        ->and($reclaimed->status)->toBe(OnlineBookingHandleStatus::Reserved);
});

it('backfills the active publication handle instead of an unpublished draft handle', function () {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $site = OnlineBookingSite::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => 'draft-booking-handle',
        'template_key' => 'essential',
        'status' => 'published',
        'draft_revision' => 2,
        'published_at' => now(),
    ]);
    OnlineBookingSetting::query()->updateOrCreate([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ], [
        'public_slug' => 'legacy-booking-handle',
    ]);
    $publication = OnlineBookingPublication::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'site_id' => $site->getKey(),
        'version' => 1,
        'source_revision' => 1,
        'content' => [],
        'content_hash' => str_repeat('a', 64),
        'template_key' => 'essential',
        'public_slug' => 'published-booking-handle',
        'published_by' => $owner->getKey(),
        'published_at' => now(),
    ]);
    $site->forceFill(['active_publication_id' => $publication->getKey()])->save();

    $legacyUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    OnlineBookingSite::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $legacyUnit->getKey(),
        'public_slug' => 'draft-only-handle',
        'template_key' => 'essential',
    ]);
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $legacyUnit->getKey(),
        'public_slug' => 'legacy-live-handle',
    ]);

    $migration = require base_path('database/migrations/2026_09_28_120001_backfill_online_booking_handles.php');
    $migration->up();

    $backfilledHandles = OnlineBookingHandle::query()
        ->where('tenant_id', $tenant->getKey())
        ->pluck('handle')
        ->sort()
        ->values()
        ->all();

    expect($backfilledHandles)->toBe([
        'legacy-live-handle',
        'published-booking-handle',
    ]);
});
