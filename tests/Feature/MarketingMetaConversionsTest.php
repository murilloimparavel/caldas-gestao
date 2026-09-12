<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set([
        'services.meta.pixel_id' => '2058995828162676',
        'services.meta.conversions_api_token' => 'meta-test-token',
        'services.meta.conversions_api_version' => 'v25.0',
        'services.meta.conversions_api_url' => 'https://graph.facebook.com',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['events_received' => 1]),
    ]);
});

it('sends a PageView to the Graph CAPI as a form payload', function (): void {
    $response = $this->postJson(route('marketing.barber.meta-events.store'), [
        'event_name' => 'PageView',
        'event_id' => 'pageview-test-123',
        'event_source_url' => 'http://localhost/?lp=barber&utm_source=meta',
        'fbp' => 'fb.1.123456789.987654321',
        'fbc' => 'fb.1.123456789.AbCdEf',
    ]);

    $response->assertAccepted()->assertJson(['accepted' => true]);

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();
        $events = json_decode((string) ($payload['data'] ?? ''), true);
        $event = $events[0] ?? [];

        return $request->isForm()
            && $request->url() === 'https://graph.facebook.com/v25.0/2058995828162676/events'
            && $request->hasHeader('Authorization')
            && ! str_contains($request->url(), 'meta-test-token')
            && ! str_contains($request->body(), 'meta-test-token')
            && ($event['event_name'] ?? null) === 'PageView'
            && ($event['event_id'] ?? null) === 'pageview-test-123'
            && ($event['action_source'] ?? null) === 'website'
            && ($event['event_source_url'] ?? null) === 'http://localhost/?lp=barber&utm_source=meta';
    });
});

it('rejects events outside the Meta whitelist', function (): void {
    $this->postJson(route('marketing.barber.meta-events.store'), [
        'event_name' => 'Purchase',
        'event_id' => 'invalid-event-123',
        'event_source_url' => 'http://localhost/?lp=barber',
    ])->assertUnprocessable();

    Http::assertNothingSent();
});
