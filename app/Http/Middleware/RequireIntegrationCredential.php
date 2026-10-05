<?php

namespace App\Http\Middleware;

use App\Models\Integrations\IntegrationCredential;
use App\Models\User;
use App\Policies\IntegrationAdminPolicy;
use App\Support\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use Symfony\Component\HttpFoundation\Response;

class RequireIntegrationCredential
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $accessToken = $user->currentAccessToken();
        abort_unless($accessToken instanceof AccessToken, 401);

        $token = Token::query()->find($accessToken->oauth_access_token_id);
        abort_unless(
            $token instanceof Token
                && ! $token->revoked
                && $token->expires_at?->isFuture()
                && $token->user_id === (string) $user->getAuthIdentifier(),
            401,
        );

        $credential = IntegrationCredential::query()
            ->where('passport_token_id', $token->getKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        abort_unless($credential instanceof IntegrationCredential, 401);
        $canonicalizeCapabilities = static function (array $capabilities): array {
            sort($capabilities);

            return $capabilities;
        };
        $tokenScopes = $canonicalizeCapabilities($token->scopes);
        $credentialCapabilities = $canonicalizeCapabilities($credential->capabilities);
        $allowedCapabilitySets = array_map($canonicalizeCapabilities, [
            ['context:read'],
            ['operations:propose'],
            ['catalog:read'],
            ['setup:read'],
            ['catalog:read', 'setup:read'],
            ['context:read', 'catalog:read', 'setup:read'],
        ]);
        abort_unless(
            $tokenScopes === $credentialCapabilities
                && in_array($credentialCapabilities, $allowedCapabilitySets, true),
            401,
        );

        abort_unless($user->hasVerifiedEmail() && ! $user->must_change_password, 403);

        try {
            $context = TenantContext::forUser(
                $user,
                (string) $credential->tenant_id,
                $credential->unit_id === null ? null : (string) $credential->unit_id,
            );
        } catch (AuthorizationException) {
            abort(403);
        }

        abort_unless(
            app(IntegrationAdminPolicy::class)->allows($user, $context),
            403,
        );

        $lastUsedAt = now()->toDateTimeString();
        IntegrationCredential::query()
            ->whereKey($credential->getKey())
            ->where(function ($query): void {
                $query->whereNull('last_used_at')
                    ->orWhere('last_used_at', '<=', now()->subMinute());
            })
            ->update(['last_used_at' => $lastUsedAt, 'updated_at' => $lastUsedAt]);

        $request->attributes->set(TenantContext::class, $context);
        $request->attributes->set(IntegrationCredential::class, $credential);
        app()->instance(TenantContext::class, $context);
        Gate::authorize('manage-integrations');

        return $next($request);
    }
}
