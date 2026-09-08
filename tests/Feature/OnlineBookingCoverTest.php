<?php

use App\Models\OnlineBookingSetting;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['filesystems.media_disk' => 'public']);
    Storage::fake('public');
});

it('allows an authorized owner to upload, replace, publish, and remove the booking cover', function (): void {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    Service::query()->where('unit_id', $unit->getKey())->update(['online_booking_enabled' => true]);
    Professional::query()->where('unit_id', $unit->getKey())->update(['online_booking_enabled' => true]);
    $setting = OnlineBookingSetting::create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => 'cover-'.fake()->unique()->slug(2),
    ]);

    $firstResponse = $this->actingAs($owner)->post(route('online_booking.cover.store'), [
        'image' => UploadedFile::fake()->image('first-cover.jpg'),
    ]);
    $firstResponse->assertSuccessful()->assertJsonStructure(['cover']);

    $setting->refresh();
    $oldPath = $setting->cover_image_path;
    expect($oldPath)->not->toBeNull();
    Storage::disk('public')->assertExists($oldPath);

    $secondResponse = $this->actingAs($owner)->post(route('online_booking.cover.store'), [
        'image' => UploadedFile::fake()->image('second-cover.webp'),
    ]);
    $secondResponse->assertSuccessful();

    $setting->refresh();
    $newPath = $setting->cover_image_path;
    expect($newPath)->not->toBe($oldPath)
        ->and($secondResponse->json('cover'))->toBe(Storage::disk('public')->url($newPath));
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);

    $this->getJson(route('public_booking.show', [$tenant, $unit]))
        ->assertSuccessful()
        ->assertJsonPath('unit.cover_image_url', Storage::disk('public')->url($newPath));

    $this->actingAs($owner)->delete(route('online_booking.cover.destroy'))
        ->assertSuccessful()
        ->assertJsonPath('cover', null);

    expect($setting->fresh()->cover_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($newPath);
});

it('validates cover uploads and authorizes them against the active unit', function (): void {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    OnlineBookingSetting::create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => 'cover-'.fake()->unique()->slug(2),
    ]);

    $this->actingAs($owner)->post(route('online_booking.cover.store'), [
        'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors(['image']);

    $this->actingAs($owner)->post(route('online_booking.cover.store'), [
        'image' => UploadedFile::fake()->image('large.png')->size(5121),
    ])->assertSessionHasErrors(['image']);

    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)
        ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
        ->post(route('online_booking.cover.store'), [
            'image' => UploadedFile::fake()->image('unauthorized.jpg'),
        ])->assertForbidden();

    $this->actingAs($unauthorized)
        ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
        ->delete(route('online_booking.cover.destroy'))
        ->assertForbidden();
});
