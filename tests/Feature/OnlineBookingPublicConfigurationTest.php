<?php

test('home renders the public marketing landing page', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('marketing/home'));
});

it('persists public configuration and resolves the canonical slug', function () {
    [$owner, $tenant, $unit, $service, $professional] = onlineBookingWorkspace();
    $payload = [
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'lock_version' => $unit->lock_version,
        'public_slug' => 'roma-club',
        'description' => 'Barbearia moderna',
        'whatsapp_phone' => '5511999999999',
        'facebook_url' => 'https://www.facebook.com/roma-club',
        'brand_color' => '#112233',
        'booking_flow' => 'professional_first',
        'minimum_notice_minutes' => 30,
    ];

    $this->actingAs($owner)->patch(route('online_booking.update'), $payload)->assertRedirect();

    $this->getJson(route('public_booking.slug', ['public_slug' => 'roma-club']))
        ->assertSuccessful()
        ->assertJsonPath('unit.description', 'Barbearia moderna')
        ->assertJsonPath(
            'unit.contacts.facebook_url',
            'https://www.facebook.com/roma-club',
        )
        ->assertJsonPath('unit.brand_color', '#112233');
});
