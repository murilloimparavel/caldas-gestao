<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('allows an authorized owner to manage the public gallery', function () {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    Storage::fake('public');

    $response = $this->actingAs($owner)->post(route('online_booking.gallery.store'), [
        'image' => UploadedFile::fake()->image('front.jpg'), 'alt_text' => 'Fachada',
    ]);
    $response->assertCreated()->assertJsonPath('gallery.alt_text', 'Fachada');
    $imageId = $response->json('gallery.id');

    $this->actingAs($owner)->patch(route('online_booking.gallery.update', ['image' => $imageId]), ['alt_text' => 'Entrada'])
        ->assertSuccessful()->assertJsonPath('gallery.alt_text', 'Entrada');
    $this->actingAs($owner)->post(route('online_booking.gallery.reorder'), ['image_ids' => [$imageId]])->assertSuccessful();
    $this->actingAs($owner)->delete(route('online_booking.gallery.destroy', ['image' => $imageId]))->assertSuccessful();
});
