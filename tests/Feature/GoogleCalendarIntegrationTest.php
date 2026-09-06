<?php

use App\Jobs\SyncGoogleCalendarAppointment;
use App\Models\Appointment;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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
        'https://calendar.test/v3/calendars/primary/events' => Http::sequence()
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
