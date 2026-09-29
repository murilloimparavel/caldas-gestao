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

it('allows an authorized owner to upload, replace, expose, and remove the booking logo', function (): void {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true]);
    Service::query()->where('unit_id', $unit->getKey())->update(['online_booking_enabled' => true]);
    Professional::query()->where('unit_id', $unit->getKey())->update(['online_booking_enabled' => true]);
    $setting = OnlineBookingSetting::create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => 'logo-'.fake()->unique()->slug(2),
    ]);

    $firstResponse = $this->actingAs($owner)->post(route('online_booking.logo.store'), [
        'image' => UploadedFile::fake()->image('first-logo.png'),
    ]);
    $firstResponse->assertSuccessful()->assertJsonStructure(['logo']);

    $oldPath = $setting->refresh()->logo_image_path;
    expect($oldPath)->not->toBeNull();
    Storage::disk('public')->assertExists($oldPath);

    $secondResponse = $this->actingAs($owner)->post(route('online_booking.logo.store'), [
        'image' => UploadedFile::fake()->image('second-logo.webp'),
    ]);
    $secondResponse->assertSuccessful();

    $newPath = $setting->refresh()->logo_image_path;
    expect($newPath)->not->toBe($oldPath)
        ->and($secondResponse->json('logo'))->toBe(Storage::disk('public')->url($newPath));
    Storage::disk('public')->assertExists($oldPath);
    Storage::disk('public')->assertExists($newPath);

    $this->getJson(route('public_booking.show', [$tenant, $unit]))
        ->assertSuccessful()
        ->assertJsonPath('unit.logo_image_url', Storage::disk('public')->url($newPath));

    $this->actingAs($owner)->delete(route('online_booking.logo.destroy'))
        ->assertSuccessful()
        ->assertJsonPath('logo', null);

    expect($setting->fresh()->logo_image_path)->toBeNull();
    Storage::disk('public')->assertExists($newPath);
});

it('validates logo uploads and isolates them to the active unit', function (): void {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    OnlineBookingSetting::create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => 'logo-'.fake()->unique()->slug(2),
    ]);

    $this->actingAs($owner)->post(route('online_booking.logo.store'), [
        'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors(['image']);

    $this->actingAs($owner)->post(route('online_booking.logo.store'), [
        'image' => UploadedFile::fake()->image('large.png')->size(5121),
    ])->assertSessionHasErrors(['image']);

    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)
        ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
        ->post(route('online_booking.logo.store'), [
            'image' => UploadedFile::fake()->image('unauthorized.jpg'),
        ])->assertForbidden();

    $this->actingAs($unauthorized)
        ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
        ->delete(route('online_booking.logo.destroy'))
        ->assertForbidden();
});
