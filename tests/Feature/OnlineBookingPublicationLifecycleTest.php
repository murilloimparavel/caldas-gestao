<?php

use App\Actions\OnlineBooking\PublishOnlineBookingSite;
use App\Actions\OnlineBooking\RestoreOnlineBookingPublication;
use App\Actions\OnlineBooking\SaveOnlineBookingDraft;
use App\Actions\OnlineBooking\UnpublishOnlineBookingSite;
use App\Enums\OnlineBookingPublicationStatus;
use App\Models\OnlineBookingPublication;
use App\Support\TenantContext;

it('saves, publishes, unpublishes, and restores an online booking site', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $service->update(['online_booking_enabled' => true]);
    $professional->update(['online_booking_enabled' => true]);
    $professional->services()->syncWithoutDetaching([$service->getKey()]);
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $content = [
        'schema_version' => 1,
        'theme' => ['brand_color' => '#2563eb'],
        'sections' => [['key' => 'hero', 'enabled' => true]],
        'service_ids' => [],
        'professional_ids' => [],
    ];

    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, $content, 0);
    $publication = app(PublishOnlineBookingSite::class)->handle($owner, $context, $draft->revision);
    $replayedPublication = app(PublishOnlineBookingSite::class)->handle($owner, $context, $draft->revision);

    expect($publication->version)->toBe(1)
        ->and($replayedPublication->getKey())->toBe($publication->getKey())
        ->and(OnlineBookingPublication::query()->count())->toBe(1)
        ->and($unit->refresh()->onlineBookingSetting)->toBeNull();

    $site = $publication->site()->firstOrFail();
    expect($site->status)->toBe(OnlineBookingPublicationStatus::Published)
        ->and($site->active_publication_id)->toBe($publication->getKey());

    app(UnpublishOnlineBookingSite::class)->handle($owner, $context);
    expect($site->refresh()->status)->toBe(OnlineBookingPublicationStatus::Unpublished)
        ->and($site->active_publication_id)->toBeNull();

    $restored = app(RestoreOnlineBookingPublication::class)->handle($owner, $context, $publication);
    expect($restored->revision)->toBe(2)
        ->and($restored->content)->toMatchArray($content);
});

it('exposes the draft and publication lifecycle through the authorized routes', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $service->update(['online_booking_enabled' => true]);
    $professional->update(['online_booking_enabled' => true]);
    $professional->services()->syncWithoutDetaching([$service->getKey()]);
    $content = [
        'schema_version' => 1,
        'theme' => [],
        'sections' => [],
        'service_ids' => [],
        'professional_ids' => [],
    ];

    $draft = $this->actingAs($owner)->patchJson(route('online_booking.draft.update'), [
        'revision' => 0,
        'content' => $content,
    ])->assertOk()->json('draft');

    $this->actingAs($owner)->postJson(route('online_booking.publish'), ['revision' => $draft['revision']])
        ->assertOk()
        ->assertJsonPath('status', 'published');

    $this->actingAs($owner)->getJson(route('online_booking.index'))
        ->assertOk()
        ->assertJsonPath('publication.status', 'published')
        ->assertJsonPath('activePublication.version', 1);

    $this->actingAs($owner)->postJson(route('online_booking.unpublish'))
        ->assertOk()
        ->assertJsonPath('status', 'unpublished');

    expect($unit->refresh()->tenant_id)->toBe($tenant->getKey());
});

it('preserves draft sections when saving a partial editor update', function () {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());

    $first = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => ['description' => 'Página da unidade'],
        'sections' => [['key' => 'hero', 'enabled' => true]],
    ], 0);
    $second = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'theme' => ['brand_color' => '#0f766e'],
    ], $first->revision);

    expect($second->content)->toMatchArray([
        'identity' => ['description' => 'Página da unidade'],
        'sections' => [['key' => 'hero', 'enabled' => true]],
        'theme' => ['brand_color' => '#0f766e'],
    ]);
});
