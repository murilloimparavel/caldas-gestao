<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SyncGoogleCalendarAppointment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly string $appointmentId)
    {
        $this->onQueue('google-calendar');
    }

    /**
     * Execute the job.
     */
    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 30, 120, 600];
    }

    public function handle(): void
    {
        $appointment = Appointment::query()->with('customer')->find($this->appointmentId);

        if ($appointment === null) {
            return;
        }

        $connection = GoogleCalendarConnection::query()
            ->where('tenant_id', $appointment->tenant_id)
            ->where('unit_id', $appointment->unit_id)
            ->where('status', 'connected')
            ->first();

        if ($connection === null) {
            return;
        }

        $event = GoogleCalendarEvent::query()->firstOrNew([
            'connection_id' => $connection->getKey(),
            'appointment_id' => $appointment->getKey(),
        ]);
        $event->forceFill([
            'tenant_id' => $appointment->tenant_id,
            'unit_id' => $appointment->unit_id,
            'appointment_lock_version' => $appointment->lock_version,
        ]);

        if (in_array($appointment->status, ['cancelled', 'no_show'], true)) {
            $this->deleteRemoteEvent($connection, $event);
            $event->forceFill(['sync_status' => 'deleted', 'operation' => 'delete', 'last_error' => null, 'synced_at' => now()])->save();

            return;
        }

        $payload = $this->payload($appointment);
        $event->forceFill([
            'sync_status' => 'pending',
            'operation' => 'upsert',
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'last_error' => null,
        ]);
        $accessToken = $this->accessToken($connection);
        $calendarId = rawurlencode((string) ($connection->calendar_id ?: 'primary'));
        $baseUrl = rtrim((string) config('services.google_calendar.calendar_url', 'https://www.googleapis.com/calendar/v3'), '/');

        if (filled($event->google_event_id)) {
            $response = $this->request($accessToken, 'patch', $baseUrl.'/calendars/'.$calendarId.'/events/'.rawurlencode((string) $event->google_event_id), $payload);
            if ($response->status() === 404) {
                $event->google_event_id = null;
            } else {
                $response->throw();
            }
        }

        if (blank($event->google_event_id)) {
            $response = $this->request($accessToken, 'post', $baseUrl.'/calendars/'.$calendarId.'/events', $payload);
            $response->throw();
            $remoteId = $response->json('id');
            if (! is_string($remoteId) || $remoteId === '') {
                throw new RuntimeException('Google Calendar did not return an event id.');
            }
            $event->google_event_id = $remoteId;
        }

        $event->forceFill(['sync_status' => 'synced', 'last_error' => null, 'synced_at' => now()])->save();
        $connection->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();
    }

    private function deleteRemoteEvent(GoogleCalendarConnection $connection, GoogleCalendarEvent $event): void
    {
        if (blank($event->google_event_id)) {
            return;
        }
        $accessToken = $this->accessToken($connection);
        $calendarId = rawurlencode((string) ($connection->calendar_id ?: 'primary'));
        $baseUrl = rtrim((string) config('services.google_calendar.calendar_url', 'https://www.googleapis.com/calendar/v3'), '/');
        $response = $this->request($accessToken, 'delete', $baseUrl.'/calendars/'.$calendarId.'/events/'.rawurlencode((string) $event->google_event_id));
        if (! in_array($response->status(), [204, 404], true)) {
            $response->throw();
        }
    }

    private function accessToken(GoogleCalendarConnection $connection): string
    {
        if (filled($connection->access_token) && $connection->token_expires_at?->isFuture()) {
            return (string) $connection->access_token;
        }
        if (blank($connection->refresh_token)) {
            throw new RuntimeException('Google Calendar connection has no usable access token.');
        }
        $response = Http::asForm()->timeout((int) config('services.google_calendar.timeout', 10))->connectTimeout((int) config('services.google_calendar.connect_timeout', 3))->post((string) config('services.google_calendar.token_url'), [
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        $response->throw();
        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google did not return a refreshed access token.');
        }
        $connection->forceFill(['access_token' => $token, 'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600))])->save();

        return $token;
    }

    private function request(string $accessToken, string $method, string $url, ?array $payload = null): Response
    {
        $request = Http::withToken($accessToken)->acceptJson()->timeout((int) config('services.google_calendar.timeout', 10))->connectTimeout((int) config('services.google_calendar.connect_timeout', 3));

        return match ($method) {
            'post' => $request->post($url, $payload ?? []),
            'patch' => $request->patch($url, $payload ?? []),
            'delete' => $request->delete($url),
            default => throw new RuntimeException('Unsupported Google Calendar HTTP method.'),
        };
    }

    /** @return array{summary:string,description:string,start:array{dateTime:string,timeZone:string},end:array{dateTime:string,timeZone:string}} */
    private function payload(Appointment $appointment): array
    {
        $summary = 'Appointment';
        $customer = $appointment->customer?->name;

        return [
            'summary' => $customer ? $summary.' - '.$customer : $summary,
            'description' => (string) ($appointment->notes ?? ''),
            'start' => ['dateTime' => $appointment->starts_at->toIso8601String(), 'timeZone' => $appointment->timezone],
            'end' => ['dateTime' => $appointment->ends_at->toIso8601String(), 'timeZone' => $appointment->timezone],
        ];
    }
}
