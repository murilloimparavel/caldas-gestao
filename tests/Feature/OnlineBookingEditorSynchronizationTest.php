<?php

use App\Actions\OnlineBooking\PublishOnlineBookingSite;
use App\Actions\OnlineBooking\SaveOnlineBookingDraft;
use App\Models\OnlineBookingGalleryImage;
use App\Models\OnlineBookingSetting;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('loads editor fields and catalog from the draft instead of stale legacy settings', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'description' => 'Descrição antiga',
        'phone' => '6434530000',
        'facebook_url' => 'https://facebook.com/old-profile',
    ]);
    $hours = ['1' => [
        'enabled' => true,
        'starts_at' => '09:00',
        'ends_at' => '19:00',
        'display_periods' => [
            ['starts_at' => '09:00', 'ends_at' => '12:00'],
            ['starts_at' => '14:00', 'ends_at' => '19:00'],
        ],
    ]];
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => [
            'description' => 'Descrição atual do RomaWear',
            'phone' => '6434531234',
            'whatsapp_phone' => '64999991234',
            'instagram_url' => 'https://instagram.com/current-profile',
            'facebook_url' => null,
            'website_url' => 'https://romawear.example.com',
        ],
        'public_hours' => $hours,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
    ], 0);

    $this->actingAs($owner)->getJson(route('online_booking.index'))
        ->assertSuccessful()
        ->assertJsonPath('settings.description', 'Descrição atual do RomaWear')
        ->assertJsonPath('settings.phone', '6434531234')
        ->assertJsonPath('settings.whatsapp', '64999991234')
        ->assertJsonPath('settings.instagram', 'https://instagram.com/current-profile')
        ->assertJsonPath('settings.facebook', null)
        ->assertJsonPath('settings.website', 'https://romawear.example.com')
        ->assertJsonPath('settings.public_hours', $hours)
        ->assertJsonPath('services.0.online_booking_enabled', true)
        ->assertJsonPath('professionals.0.online_booking_enabled', true);

    expect($service->fresh()->online_booking_enabled)->toBeFalse()
        ->and($professional->fresh()->online_booking_enabled)->toBeFalse();
});

it('recovers published editor content when its draft is missing', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $service->update(['online_booking_enabled' => true]);
    $professional->update(['online_booking_enabled' => true]);
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $hours = ['1' => ['enabled' => true, 'starts_at' => '10:00', 'ends_at' => '18:00', 'display_periods' => [['starts_at' => '10:00', 'ends_at' => '18:00']]]];
    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => ['description' => 'Identidade publicada', 'phone' => '6434531234'],
        'public_hours' => $hours,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
    ], 0);
    $publication = app(PublishOnlineBookingSite::class)->handle($owner, $context, $draft->revision);
    $draft->delete();

    $this->actingAs($owner)->getJson(route('online_booking.index'))
        ->assertSuccessful()
        ->assertJsonPath('settings.description', 'Identidade publicada')
        ->assertJsonPath('settings.phone', '6434531234')
        ->assertJsonPath('settings.public_hours', $hours)
        ->assertJsonPath('draft.content.identity.description', 'Identidade publicada')
        ->assertJsonPath('draft.content.service_ids', [$service->getKey()]);

    expect($publication->fresh()->content['identity']['description'])->toBe('Identidade publicada');
});

it('saves editor fields without erasing publication metadata assets hours or catalog', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $coverPath = 'online-booking/'.$unit->getKey().'/cover/current.webp';
    $gallery = [['path' => 'online-booking/'.$unit->getKey().'/gallery/current.webp', 'alt_text' => 'Espaço RomaWear']];
    $hours = ['1' => [
        'enabled' => true, 'starts_at' => '09:00', 'ends_at' => '19:00',
        'display_periods' => [['starts_at' => '09:00', 'ends_at' => '12:00'], ['starts_at' => '14:00', 'ends_at' => '19:00']],
    ]];
    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => ['description' => 'Antes', 'cover_image_path' => $coverPath, 'phone' => '6434531234'],
        'theme' => ['brand_color' => '#112233', 'surface_color' => '#0e0e0e'],
        'seo' => ['title' => 'RomaWear em Caldas Novas', 'description' => 'SEO preservado'],
        'gallery' => $gallery,
        'sections' => [['key' => 'contact', 'enabled' => true]],
        'public_hours' => $hours,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'appearance' => ['headline' => 'Reserve no RomaWear'],
    ], 0);

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => $unit->slug,
        'template_key' => 'atelier-barber',
        'description' => 'Depois',
        'phone' => '6434534321',
        'brand_color' => '#665544',
        'public_hours' => $hours,
        'lock_version' => $unit->lock_version,
        'draft_revision' => $draft->revision,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $content = $draft->fresh()->content;
    expect($content['identity']['description'])->toBe('Depois')
        ->and($content['identity']['phone'])->toBe('6434534321')
        ->and($content['identity']['cover_image_path'])->toBe($coverPath)
        ->and($content['seo'])->toBe(['title' => 'RomaWear em Caldas Novas', 'description' => 'SEO preservado'])
        ->and($content['theme']['surface_color'])->toBe('#0e0e0e')
        ->and($content['theme']['brand_color'])->toBe('#665544')
        ->and($content['gallery'])->toBe($gallery)
        ->and($content['sections'])->toBe([['key' => 'contact', 'enabled' => true]])
        ->and($content['appearance']['headline'])->toBe('Reserve no RomaWear')
        ->and($content['public_hours'])->toBe($hours)
        ->and($content['service_ids'])->toBe([$service->getKey()])
        ->and($content['professional_ids'])->toBe([$professional->getKey()]);
});

it('rejects a settings save from a stale draft before changing unit or catalog', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $first = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => ['description' => 'Primeira revisão'],
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
    ], 0);
    $current = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => ['description' => 'Outra sessão salvou esta revisão'],
    ], $first->revision);

    $this->actingAs($owner)->patchJson(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'service_ids' => [],
        'professional_ids' => [],
        'public_slug' => $unit->slug,
        'lock_version' => $unit->lock_version,
        'draft_revision' => $first->revision,
        'description' => 'Revisão antiga tentaria sobrescrever',
    ])->assertStatus(409);

    expect($current->fresh()->content['identity']['description'])->toBe('Outra sessão salvou esta revisão')
        ->and($current->fresh()->content['service_ids'])->toBe([$service->getKey()])
        ->and($unit->fresh()->lock_version)->toBe($unit->lock_version)
        ->and($unit->fresh()->online_booking_enabled)->toBeFalse()
        ->and($service->fresh()->online_booking_enabled)->toBeFalse();
});

it('synchronizes uploaded and removed identity assets to the draft without changing the live publication', function (string $asset): void {
    config(['filesystems.media_disk' => 'public']);
    Storage::fake('public');
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $service->update(['online_booking_enabled' => true]);
    $professional->update(['online_booking_enabled' => true]);
    $assetKey = $asset.'_image_path';
    $oldPath = 'online-booking/'.$unit->getKey().'/'.$asset.'/published.webp';
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        $assetKey => $oldPath,
    ]);
    Storage::disk('public')->put($oldPath, 'published-asset');
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => [$assetKey => $oldPath, 'description' => 'Identidade preservada'],
        'seo' => ['title' => 'Título preservado'],
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
    ], 0);
    $publication = app(PublishOnlineBookingSite::class)->handle($owner, $context, $draft->revision);

    $this->actingAs($owner)->post(route('online_booking.'.$asset.'.store'), [
        'image' => UploadedFile::fake()->image('new-'.$asset.'.png'),
    ])->assertSuccessful();

    $newPath = $unit->fresh()->onlineBookingSetting->{$assetKey};
    expect($newPath)->not->toBe($oldPath)
        ->and($draft->fresh()->content['identity'][$assetKey])->toBe($newPath)
        ->and($draft->fresh()->content['identity']['description'])->toBe('Identidade preservada')
        ->and($draft->fresh()->content['seo']['title'])->toBe('Título preservado')
        ->and($draft->fresh()->content['service_ids'])->toBe([$service->getKey()])
        ->and($publication->fresh()->content['identity'][$assetKey])->toBe($oldPath);
    Storage::disk('public')->assertExists($newPath);

    $this->actingAs($owner)->delete(route('online_booking.'.$asset.'.destroy'))
        ->assertSuccessful()->assertJsonPath($asset, null);

    expect($draft->fresh()->content['identity'][$assetKey])->toBeNull()
        ->and($draft->fresh()->content['identity']['description'])->toBe('Identidade preservada')
        ->and($draft->fresh()->content['professional_ids'])->toBe([$professional->getKey()])
        ->and($publication->fresh()->content['identity'][$assetKey])->toBe($oldPath);
    Storage::disk('public')->assertExists($oldPath);
})->with(['cover', 'logo']);

it('rejects invalid displayed opening periods without overwriting the draft', function (array $periods): void {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => ['description' => 'Identidade preservada'],
        'public_hours' => ['1' => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '18:00']],
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
    ], 0);
    $originalContent = $draft->content;

    $this->actingAs($owner)->patchJson(route('online_booking.update'), [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => $unit->slug,
        'lock_version' => $unit->lock_version,
        'draft_revision' => $draft->revision,
        'public_hours' => ['1' => [
            'enabled' => true,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'display_periods' => $periods,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('public_hours.1.display_periods');

    expect($draft->fresh()->content)->toBe($originalContent)
        ->and($unit->fresh()->lock_version)->toBe($unit->lock_version);
})->with([
    'closing before opening' => [[['starts_at' => '18:00', 'ends_at' => '09:00']]],
    'overlapping periods' => [[['starts_at' => '09:00', 'ends_at' => '13:00'], ['starts_at' => '12:00', 'ends_at' => '18:00']]],
    'enabled day without periods' => [[]],
]);

it('removes identity assets held only in the draft', function (string $asset): void {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $assetKey = $asset.'_image_path';
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'public_slug' => $unit->slug,
        $assetKey => null,
    ]);
    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => [$assetKey => 'online-booking/'.$unit->getKey().'/'.$asset.'/draft.webp', 'description' => 'Preservada'],
    ], 0);

    $this->actingAs($owner)->delete(route('online_booking.'.$asset.'.destroy'))
        ->assertSuccessful()->assertJsonPath($asset, null);

    expect($draft->fresh()->content['identity'][$assetKey])->toBeNull()
        ->and($draft->fresh()->content['identity']['description'])->toBe('Preservada');
})->with(['cover', 'logo']);

it('synchronizes gallery mutations to the draft while preserving metadata and the live publication', function (): void {
    config(['filesystems.media_disk' => 'public']);
    Storage::fake('public');
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    $service->update(['online_booking_enabled' => true]);
    $professional->update(['online_booking_enabled' => true]);
    $original = OnlineBookingGalleryImage::query()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(),
        'path' => 'online-booking/'.$unit->getKey().'/gallery/published.webp',
        'alt_text' => 'Foto publicada', 'position' => 0,
    ]);
    $publishedGallery = [['path' => $original->path, 'thumbnail_path' => null, 'alt_text' => 'Foto publicada']];
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'identity' => ['description' => 'Identidade preservada'],
        'seo' => ['title' => 'SEO preservado'],
        'gallery' => $publishedGallery,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
    ], 0);
    $publication = app(PublishOnlineBookingSite::class)->handle($owner, $context, $draft->revision);

    $response = $this->actingAs($owner)->post(route('online_booking.gallery.store'), [
        'image' => UploadedFile::fake()->image('new-gallery.jpg'), 'alt_text' => 'Nova foto',
    ])->assertCreated();
    $imageId = $response->json('gallery.id');
    $imagePath = $response->json('gallery.path');
    expect(array_column($draft->fresh()->content['gallery'], 'path'))->toBe([$original->path, $imagePath]);

    $this->actingAs($owner)->patch(route('online_booking.gallery.update', ['image' => $imageId]), ['alt_text' => 'Entrada atualizada'])
        ->assertSuccessful();
    expect($draft->fresh()->content['gallery'][1]['alt_text'])->toBe('Entrada atualizada');

    $this->actingAs($owner)->post(route('online_booking.gallery.reorder'), ['image_ids' => [$imageId, $original->getKey()]])
        ->assertSuccessful();
    expect(array_column($draft->fresh()->content['gallery'], 'path'))->toBe([$imagePath, $original->path]);

    $this->actingAs($owner)->delete(route('online_booking.gallery.destroy', ['image' => $original->getKey()]))
        ->assertSuccessful();
    expect(array_column($draft->fresh()->content['gallery'], 'path'))->toBe([$imagePath])
        ->and($draft->fresh()->content['identity']['description'])->toBe('Identidade preservada')
        ->and($draft->fresh()->content['seo']['title'])->toBe('SEO preservado')
        ->and($draft->fresh()->content['service_ids'])->toBe([$service->getKey()])
        ->and($publication->fresh()->content['gallery'])->toBe($publishedGallery);
});

it('accepts string lock versions and booleans submitted by the browser settings form', function (): void {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $draft = app(SaveOnlineBookingDraft::class)->handle($owner, $context, [
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
    ], 0);

    $this->actingAs($owner)->patch(route('online_booking.update'), [
        'online_booking_enabled' => '1',
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => $unit->slug,
        'lock_version' => (string) $unit->lock_version,
        'draft_revision' => (string) $draft->revision,
        'public_hours' => ['1' => [
            'enabled' => '1',
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'display_periods' => [['starts_at' => '09:00', 'ends_at' => '12:00'], ['starts_at' => '14:00', 'ends_at' => '18:00']],
        ]],
    ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');

    expect($unit->fresh()->online_booking_enabled)->toBeTrue()
        ->and($unit->fresh()->lock_version)->toBe($unit->lock_version + 1)
        ->and($draft->fresh()->content['public_hours'][1]['display_periods'])->toHaveCount(2);
});
