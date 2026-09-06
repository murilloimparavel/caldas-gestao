<?php

use App\Actions\Identity\OnboardTenant;
use App\Jobs\SyncGoogleCalendarAppointment;
use App\Models\Appointment;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use App\Models\GoogleCalendarOAuthState;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** @return array{0: User, 1: string, 2: string} */
function googleCalendarWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Google Calendar '.Str::random(8)]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, (string) $tenant->getKey(), (string) $unit->getKey()];
}

function configureGoogleCalendar(): void
{
    config()->set([
        'services.google_calendar.client_id' => 'client-id',
        'services.google_calendar.client_secret' => 'client-secret',
        'services.google_calendar.redirect_uri' => 'https://app.test/google-calendar/callback',
        'services.google_calendar.authorization_url' => 'https://accounts.test/oauth/authorize',
        'services.google_calendar.token_url' => 'https://oauth.test/token',
        'services.google_calendar.userinfo_url' => 'https://oauth.test/userinfo',
    ]);
}

test('creates and then updates a Google Calendar event idempotently', function (): void {
    config()->set('services.google_calendar.calendar_url', 'https://calendar.test/v3');

    $appointment = Appointment::factory()->create(['notes' => 'Initial consultation']);
    GoogleCalendarConnection::factory()->create([
        'tenant_id' => $appointment->tenant_id,
        'unit_id' => $appointment->unit_id,
        'access_token' => 'token',
        'token_expires_at' => now()->addHour(),
    ]);

    Http::fake([
        'https://calendar.test/v3/calendars/primary/events*' => Http::sequence()
            ->push(['id' => 'google-123'])
            ->push(['id' => 'google-123']),
    ]);

    (new SyncGoogleCalendarAppointment((string) $appointment->getKey()))->handle();
    $appointment->update(['notes' => 'Updated consultation']);
    (new SyncGoogleCalendarAppointment((string) $appointment->getKey()))->handle();

    $event = GoogleCalendarEvent::query()->firstOrFail();
    expect($event->google_event_id)->toBe('google-123')
        ->and($event->sync_status)->toBe('synced');

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->method() === 'POST');
    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH');
});

test('deletes a remote event when an appointment is cancelled', function (): void {
    config()->set('services.google_calendar.calendar_url', 'https://calendar.test/v3');

    $appointment = Appointment::factory()->create(['status' => 'cancelled', 'cancelled_at' => now()]);
    $connection = GoogleCalendarConnection::factory()->create([
        'tenant_id' => $appointment->tenant_id,
        'unit_id' => $appointment->unit_id,
        'access_token' => 'token',
        'token_expires_at' => now()->addHour(),
    ]);
    GoogleCalendarEvent::query()->create([
        'connection_id' => $connection->getKey(),
        'tenant_id' => $appointment->tenant_id,
        'unit_id' => $appointment->unit_id,
        'appointment_id' => $appointment->getKey(),
        'google_event_id' => 'google-123',
        'sync_status' => 'synced',
        'operation' => 'upsert',
        'appointment_lock_version' => $appointment->lock_version,
    ]);

    Http::fake(['https://calendar.test/v3/calendars/primary/events/google-123' => Http::response([], 204)]);

    (new SyncGoogleCalendarAppointment((string) $appointment->getKey()))->handle();

    expect(GoogleCalendarEvent::query()->firstOrFail()->sync_status)->toBe('deleted');
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE');
});

test('queues appointment synchronization after commit', function (): void {
    Queue::fake();

    SyncGoogleCalendarAppointment::dispatch('appointment-id')->afterCommit();

    Queue::assertPushed(SyncGoogleCalendarAppointment::class, fn (SyncGoogleCalendarAppointment $job): bool => $job->appointmentId === 'appointment-id');
});

test('returns not configured without creating an OAuth state', function (): void {
    [$owner, $tenantId, $unitId] = googleCalendarWorkspace();
    config()->set([
        'services.google_calendar.client_id' => null,
        'services.google_calendar.client_secret' => null,
        'services.google_calendar.redirect_uri' => null,
    ]);

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId])
        ->actingAs($owner)
        ->getJson(route('google_calendar.connect'))
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'not_configured');

    expect(GoogleCalendarOAuthState::query()->count())->toBe(0);
});

test('creates a bound OAuth state and keeps secrets out of status responses', function (): void {
    configureGoogleCalendar();
    [$owner, $tenantId, $unitId] = googleCalendarWorkspace();
    $connection = GoogleCalendarConnection::factory()->create([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'access_token' => 'access-secret',
        'refresh_token' => 'refresh-secret',
    ]);

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId])
        ->actingAs($owner)
        ->getJson(route('google_calendar.connect'))
        ->assertRedirect();

    $state = GoogleCalendarOAuthState::query()->sole();
    expect($state->tenant_id)->toBe($tenantId)
        ->and($state->unit_id)->toBe($unitId)
        ->and($state->user_id)->toBe($owner->getKey());

    $statusResponse = $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId])
        ->actingAs($owner)
        ->getJson(route('google_calendar.status'))
        ->assertOk();

    expect($statusResponse->json('connection.id'))->toBe($connection->getKey())
        ->and($statusResponse->json('connection'))->not->toHaveKey('access_token')
        ->and($statusResponse->json('connection'))->not->toHaveKey('refresh_token')
        ->and($statusResponse->getContent())->not->toContain('access-secret')
        ->and($statusResponse->getContent())->not->toContain('refresh-secret');
});

test('completes OAuth once and rejects replayed state', function (): void {
    configureGoogleCalendar();
    [$owner, $tenantId, $unitId] = googleCalendarWorkspace();
    $rawState = Str::random(64);
    GoogleCalendarOAuthState::factory()->create([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'user_id' => $owner->getKey(),
        'state_hash' => hash('sha256', $rawState),
        'redirect_uri' => config('services.google_calendar.redirect_uri'),
    ]);
    Http::fake([
        'https://oauth.test/token' => Http::response(['access_token' => 'access-secret', 'refresh_token' => 'refresh-secret', 'expires_in' => 3600, 'scope' => config('services.google_calendar.scope')]),
        'https://oauth.test/userinfo' => Http::response(['email' => 'owner@example.test']),
    ]);

    $callback = fn () => $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'Accept' => 'application/json'])
        ->actingAs($owner)
        ->getJson(route('google_calendar.callback', ['state' => $rawState, 'code' => 'authorization-code']));

    $callback()->assertOk()->assertJsonPath('status', 'connected');
    expect(GoogleCalendarConnection::query()->sole()->toArray())->not->toHaveKey('access_token');
    expect(GoogleCalendarOAuthState::query()->sole()->consumed_at)->not->toBeNull();

    $callback()->assertUnprocessable()->assertJsonPath('message', 'The Google Calendar authorization state is invalid or expired. Start the connection again.');
    Http::assertSentCount(2);
});

test('rejects invalid OAuth state before contacting Google', function (): void {
    configureGoogleCalendar();
    [$owner, $tenantId, $unitId] = googleCalendarWorkspace();
    Http::fake();

    $this->withHeaders(['X-Tenant-Id' => $tenantId, 'X-Unit-Id' => $unitId, 'Accept' => 'application/json'])
        ->actingAs($owner)
        ->getJson(route('google_calendar.callback', ['state' => 'invalid-state', 'code' => 'authorization-code']))
        ->assertUnprocessable();

    Http::assertNothingSent();
});

test('isolates OAuth state and connections by user and tenant permissions', function (): void {
    configureGoogleCalendar();
    [$owner, $tenantId, $unitId] = googleCalendarWorkspace();
    [$otherUser, $otherTenantId, $otherUnitId] = googleCalendarWorkspace();
    $rawState = Str::random(64);
    GoogleCalendarOAuthState::factory()->create([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'user_id' => $owner->getKey(),
        'state_hash' => hash('sha256', $rawState),
    ]);
    GoogleCalendarConnection::factory()->create([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'access_token' => 'tenant-one-secret',
    ]);

    $this->withHeaders(['X-Tenant-Id' => $otherTenantId, 'X-Unit-Id' => $otherUnitId, 'Accept' => 'application/json'])
        ->actingAs($otherUser)
        ->getJson(route('google_calendar.callback', ['state' => $rawState, 'code' => 'authorization-code']))
        ->assertForbidden();

    $status = $this->withHeaders(['X-Tenant-Id' => $otherTenantId, 'X-Unit-Id' => $otherUnitId])
        ->actingAs($otherUser)
        ->getJson(route('google_calendar.status'))
        ->assertOk();

    expect($status->json('status'))->toBe('disconnected')
        ->and($status->json('connection'))->toBeNull()
        ->and($status->getContent())->not->toContain('tenant-one-secret');
});
