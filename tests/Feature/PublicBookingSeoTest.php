<?php

use App\Models\OnlineBookingSetting;
use App\Support\PublicBookingSeo;

it('renders public SEO in the initial HTML before JavaScript runs', function (): void {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $unit->update(['online_booking_enabled' => true, 'address' => ['street' => 'Rua Roma', 'number' => '10', 'city' => 'Caldas Novas', 'state' => 'GO']]);
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'public_slug' => $unit->slug,
        'phone' => '(64) 99999-1234', 'instagram_url' => 'https://instagram.com/romawear',
        'public_hours' => ['1' => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '18:00']],
    ]);

    $response = $this->get(route('public_booking.show', [$tenant, $unit]))->assertSuccessful();
    $response->assertSee('data-inertia="description"', false)
        ->assertSee('data-inertia="canonical"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('name="twitter:card"', false);
    preg_match('/<script data-inertia="business-schema" type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $schema = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    expect($schema['name'])->toBe($unit->name)
        ->and($schema['address']['addressLocality'])->toBe('Caldas Novas')
        ->and($schema['telephone'])->toBe('(64) 99999-1234')
        ->and($schema['sameAs'])->toBe(['https://instagram.com/romawear'])
        ->and($schema['openingHoursSpecification'][0]['dayOfWeek'])->toBe('https://schema.org/Monday');
});

it('escapes schema content and scopes the initial dark background to Atelier', function (): void {
    $unit = ['name' => '</script><script>alert(1)</script>', 'template_key' => 'atelier-barber', 'is_preview' => true];
    $unit['public_meta'] = PublicBookingSeo::forUnit($unit);
    $html = view('app', ['page' => ['component' => 'public-booking/show', 'props' => ['unit' => $unit]]])->render();
    expect($html)->toContain('class="atelier-booking"', 'content="#0e0e0e"', 'content="noindex,nofollow,noarchive"', '\\u003C/script\\u003E')
        ->not->toContain('<script>alert(1)</script>');

    $privateHtml = view('app', ['page' => ['component' => 'auth/login', 'props' => ['unit' => $unit]]])->render();
    expect($privateHtml)->not->toContain('class="atelier-booking"', 'business-schema', 'data-inertia="canonical"');
});

it('omits unsafe links empty fields and malformed opening hours', function (): void {
    $meta = PublicBookingSeo::forUnit([
        'name' => 'Roma', 'contacts' => ['instagram_url' => 'javascript:alert(1)', 'website_url' => 'https://romawear.com.br'],
        'public_hours' => [
            '0' => ['enabled' => false, 'starts_at' => '09:00', 'ends_at' => '18:00'],
            '1' => ['enabled' => true, 'starts_at' => '25:00', 'ends_at' => '18:00'],
            '2' => ['enabled' => true, 'starts_at' => '18:00', 'ends_at' => '09:00'],
            '7' => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '18:00'],
        ],
    ]);
    expect($meta['structured_data']['sameAs'])->toBe(['https://romawear.com.br'])
        ->and($meta['structured_data'])->not->toHaveKeys(['address', 'telephone', 'openingHoursSpecification', 'image']);
});

it('keeps disabled public sections out of search metadata', function (): void {
    $meta = PublicBookingSeo::forUnit([
        'name' => 'Roma', 'sections' => ['contact' => false, 'hours' => false, 'hero' => false],
        'contacts' => ['phone' => '123', 'instagram_url' => 'https://instagram.com/roma'],
        'address' => ['city' => 'Caldas Novas'], 'cover_image_url' => 'https://example.com/cover.jpg',
        'public_hours' => ['1' => ['starts_at' => '09:00', 'ends_at' => '18:00']],
    ]);
    expect($meta['image'])->toBeNull()
        ->and($meta['structured_data'])->not->toHaveKeys(['telephone', 'sameAs', 'address', 'image', 'openingHoursSpecification']);
});

it('supports legacy hours and falls back from blank SEO values', function (): void {
    $meta = PublicBookingSeo::forUnit([
        'name' => 'Roma', 'seo' => ['title' => ' ', 'description' => ''],
        'public_hours' => ['1' => ['start' => '09:00', 'end' => '18:00']],
    ]);
    expect($meta['title'])->toBe('Agendar · Roma')
        ->and($meta['description'])->toBe('Agende seu horário em Roma.')
        ->and($meta['structured_data']['openingHoursSpecification'][0]['opens'])->toBe('09:00');
});

it('lists each valid display period without publishing lunch breaks or disabled days', function (): void {
    $periods = [
        ['starts_at' => '09:00', 'ends_at' => '12:00'],
        ['starts_at' => '14:00', 'ends_at' => '18:00'],
        ['starts_at' => '25:00', 'ends_at' => '26:00'],
        ['starts_at' => '18:00', 'ends_at' => '09:00'],
    ];
    $meta = PublicBookingSeo::forUnit([
        'name' => 'Roma',
        'public_hours' => [
            '1' => ['enabled' => true, 'starts_at' => '08:00', 'ends_at' => '19:00', 'display_periods' => $periods],
            '2' => ['enabled' => false, 'display_periods' => $periods],
            '3' => ['enabled' => true, 'starts_at' => '08:00', 'ends_at' => '19:00', 'display_periods' => []],
        ],
    ]);
    expect($meta['structured_data']['openingHoursSpecification'])->toHaveCount(2)
        ->and(array_column($meta['structured_data']['openingHoursSpecification'], 'opens'))->toBe(['09:00', '14:00'])
        ->and(array_column($meta['structured_data']['openingHoursSpecification'], 'closes'))->toBe(['12:00', '18:00']);
});
