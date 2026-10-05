<?php

namespace App\Support;

use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarOAuthState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GoogleCalendarOAuth
{
    public function configured(): bool
    {
        return filled(config('services.google_calendar.client_id'))
            && filled(config('services.google_calendar.client_secret'))
            && filled(config('services.google_calendar.redirect_uri'));
    }

    /**
     * @return array{status:string,configured:bool,can_configure:bool,configuration_blocked_reason:string|null,connection:array<string,mixed>|null}
     */
    public function status(TenantContext $context): array
    {
        $connection = GoogleCalendarConnection::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->first();

        $canConfigure = $context->membership->professional_id === null;

        return [
            'status' => $this->configured() ? ($connection->status ?? 'disconnected') : 'not_configured',
            'configured' => $this->configured(),
            'can_configure' => $canConfigure,
            'configuration_blocked_reason' => $canConfigure ? null : 'Google Calendar é gerenciado pelo administrador da unidade.',
            'connection' => $canConfigure ? $connection?->only([
                'id', 'provider', 'status', 'google_account_email', 'calendar_id', 'calendar_name', 'scopes', 'token_expires_at', 'last_error', 'last_synced_at',
            ]) : null,
        ];
    }

    public function authorizationUrl(TenantContext $context, Request $request): string
    {
        $this->ensureConfigured();

        if ($context->unit === null) {
            throw new AuthorizationException('An active unit is required to connect Google Calendar.');
        }

        $state = Str::random(64);
        $codeVerifier = Str::random(96);
        $redirectUri = (string) config('services.google_calendar.redirect_uri');
        $return = app(GoogleCalendarOAuthReturnUrl::class)->capture($request, $context);

        DB::transaction(function () use ($context, $state, $codeVerifier, $redirectUri, $return): void {
            GoogleCalendarOAuthState::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit->getKey())
                ->where('user_id', $context->user->getKey())
                ->whereNull('consumed_at')
                ->delete();

            GoogleCalendarOAuthState::query()->create([
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $context->unit->getKey(),
                'user_id' => $context->user->getKey(),
                'state_hash' => hash('sha256', $state),
                'code_verifier' => $codeVerifier,
                'redirect_uri' => $redirectUri,
                ...$return,
                'expires_at' => now()->addMinutes((int) config('services.google_calendar.state_ttl_minutes', 10)),
            ]);
        });

        $query = http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => config('services.google_calendar.scope'),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
            'code_challenge' => $this->codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return (string) config('services.google_calendar.authorization_url').'?'.$query;
    }

    public function complete(string $stateValue, ?string $code, ?string $oauthError = null): GoogleCalendarConnection
    {
        $state = DB::transaction(function () use ($stateValue): GoogleCalendarOAuthState {
            $state = GoogleCalendarOAuthState::query()
                ->where('state_hash', hash('sha256', $stateValue))
                ->lockForUpdate()
                ->first();

            if ($state === null || $state->consumed_at !== null || $state->expires_at->isPast()) {
                throw new GoogleCalendarOAuthException('The Google Calendar authorization state is invalid or expired. Start the connection again.');
            }

            $user = $state->user;

            if ($user === null) {
                throw new AuthorizationException('The Google Calendar authorization user is no longer available.');
            }

            try {
                $context = TenantContext::forUser($user, (string) $state->tenant_id, (string) $state->unit_id);
            } catch (AuthorizationException $exception) {
                throw new AuthorizationException('The Google Calendar authorization no longer belongs to an active tenant unit.', $exception->getCode(), $exception);
            }

            if ($context->unit === null) {
                throw new AuthorizationException('An active unit is required to connect Google Calendar.');
            }

            if ($context->membership->professional_id !== null) {
                throw new AuthorizationException('Google Calendar is managed by the tenant administrator for professional-linked collaborators.');
            }

            if (! app(AuthorizationService::class)->can($user, $context, 'calendar.configure', $context->unit)) {
                throw new AuthorizationException('The user is no longer allowed to configure Google Calendar.');
            }

            return $state;
        });

        if (filled($oauthError)) {
            $this->consume($state->getKey());
            throw new GoogleCalendarOAuthException('Google Calendar authorization was denied.');
        }

        if (blank($code)) {
            $this->consume($state->getKey());
            throw new GoogleCalendarOAuthException('Google Calendar did not return an authorization code. Start the connection again.');
        }

        $tokenResponse = Http::asForm()
            ->timeout((int) config('services.google_calendar.timeout', 10))
            ->connectTimeout((int) config('services.google_calendar.connect_timeout', 3))
            ->post((string) config('services.google_calendar.token_url'), [
                'client_id' => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'code' => $code,
                'code_verifier' => $state->code_verifier,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $state->redirect_uri,
            ]);

        if ($tokenResponse->failed()) {
            throw $this->remoteFailure($tokenResponse, 'Google rejected the authorization code. Start the connection again.');
        }

        $accessToken = $tokenResponse->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new GoogleCalendarOAuthException('Google returned an invalid access token. Start the connection again.');
        }

        $userinfoResponse = Http::withToken($accessToken)
            ->timeout((int) config('services.google_calendar.timeout', 10))
            ->connectTimeout((int) config('services.google_calendar.connect_timeout', 3))
            ->get((string) config('services.google_calendar.userinfo_url'));

        if ($userinfoResponse->failed()) {
            throw $this->remoteFailure($userinfoResponse, 'Google did not return account information. Start the connection again.');
        }

        $accountEmail = $userinfoResponse->json('email');
        $accountEmail = is_string($accountEmail) && $accountEmail !== '' ? $accountEmail : null;

        $this->consume($state->getKey());

        $existing = GoogleCalendarConnection::query()
            ->where('tenant_id', $state->tenant_id)
            ->where('unit_id', $state->unit_id)
            ->first();
        $refreshToken = $tokenResponse->json('refresh_token') ?: $existing?->refresh_token;
        $scopes = $this->scopes($tokenResponse->json('scope'));

        return DB::transaction(function () use ($accessToken, $accountEmail, $existing, $refreshToken, $scopes, $state, $tokenResponse): GoogleCalendarConnection {
            $connection = $existing ?? new GoogleCalendarConnection([
                'tenant_id' => $state->tenant_id,
                'unit_id' => $state->unit_id,
            ]);
            $connection->forceFill([
                'tenant_id' => $state->tenant_id,
                'unit_id' => $state->unit_id,
                'connected_by_user_id' => $state->user_id,
                'provider' => 'google',
                'status' => 'connected',
                'google_account_email' => $accountEmail,
                'calendar_id' => 'primary',
                'calendar_name' => 'Primary calendar',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'scopes' => $scopes,
                'token_expires_at' => now()->addSeconds((int) $tokenResponse->json('expires_in', 3600)),
                'last_error' => null,
            ]);
            $connection->save();

            return $connection;
        });
    }

    public function returnUrl(string $stateValue): ?string
    {
        $state = GoogleCalendarOAuthState::query()
            ->where('state_hash', hash('sha256', $stateValue))
            ->first();

        if ($state === null) {
            return null;
        }

        return app(GoogleCalendarOAuthReturnUrl::class)->url($state->return_host, $state->return_path, (string) $state->tenant_id);
    }

    public function disconnect(TenantContext $context): void
    {
        GoogleCalendarConnection::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->first()?->forceFill([
                'status' => 'disconnected',
                'google_account_email' => null,
                'calendar_id' => null,
                'calendar_name' => null,
                'access_token' => null,
                'refresh_token' => null,
                'scopes' => [],
                'token_expires_at' => null,
                'last_error' => null,
                'lock_version' => (int) (GoogleCalendarConnection::query()
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $context->unit?->getKey())
                    ->value('lock_version') ?? 0) + 1,
            ])?->save();
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new GoogleCalendarNotConfigured;
        }
    }

    private function consume(string $stateId): void
    {
        DB::transaction(function () use ($stateId): void {
            $state = GoogleCalendarOAuthState::query()->lockForUpdate()->find($stateId);

            if ($state === null || $state->consumed_at !== null || $state->expires_at->isPast()) {
                throw new GoogleCalendarOAuthException('The Google Calendar authorization state is invalid or expired. Start the connection again.');
            }

            $state->forceFill(['consumed_at' => now()])->save();
        });
    }

    private function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    /** @return list<string> */
    private function scopes(mixed $scope): array
    {
        if (is_array($scope)) {
            return array_values(array_filter(array_map(static fn (mixed $value): string => (string) $value, $scope)));
        }

        return array_values(array_filter(preg_split('/\s+/', (string) ($scope ?: config('services.google_calendar.scope'))) ?: []));
    }

    private function remoteFailure(Response $response, string $fallback): GoogleCalendarOAuthException
    {
        $description = $response->json('error_description');

        return new GoogleCalendarOAuthException(is_string($description) && $description !== '' ? $description : $fallback);
    }
}
