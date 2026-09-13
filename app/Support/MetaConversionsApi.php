<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

final class MetaConversionsApi
{
    /** @param array{event_name: string, event_id: string, event_source_url: string, fbp?: string|null, fbc?: string|null, custom_data?: array<string, mixed>|null} $event */
    public function send(array $event, Request $request): void
    {
        $pixelId = trim((string) config('services.meta.pixel_id'));
        $token = trim((string) config('services.meta.conversions_api_token'));
        $testEventCode = trim((string) config('services.meta.conversions_api_test_event_code'));

        if ($pixelId === '' || $token === '') {
            return;
        }

        $userData = array_filter([
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
            'fbp' => $event['fbp'] ?? null,
            'fbc' => $event['fbc'] ?? null,
        ], static fn (?string $value): bool => filled($value));

        try {
            $payload = [
                'data' => json_encode([[
                    'event_name' => $event['event_name'],
                    'event_time' => now()->timestamp,
                    'event_id' => $event['event_id'],
                    'action_source' => 'website',
                    'event_source_url' => $event['event_source_url'],
                    'user_data' => $userData,
                    'custom_data' => $event['custom_data'] ?? [],
                ]], JSON_THROW_ON_ERROR),
            ];

            if ($testEventCode !== '') {
                $payload['test_event_code'] = $testEventCode;
            }

            Http::withToken($token)
                ->acceptJson()
                ->asForm()
                ->retry([100, 500], when: static fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()))
                ->timeout(3)
                ->connectTimeout(2)
                ->throw()
                ->post($this->eventsUrl($pixelId), $payload);
        } catch (Throwable) {
            return;
        }
    }

    private function eventsUrl(string $pixelId): string
    {
        return rtrim((string) config('services.meta.conversions_api_url'), '/')
            .'/'.trim((string) config('services.meta.conversions_api_version', 'v25.0'), '/').'/'.$pixelId.'/events';
    }
}
