<?php

namespace App\Http\Middleware;

use App\Models\Integrations\OAuthGrant;
use App\Models\User;
use App\Policies\OAuthGrantPolicy;
use App\Support\Integrations\EnforceMcpRateLimit;
use App\Support\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use Symfony\Component\HttpFoundation\Response;

final class RequireOAuthGrant
{
    public function __construct(
        private readonly OAuthGrantPolicy $policy,
        private readonly EnforceMcpRateLimit $rateLimit,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $accessToken = $user->currentAccessToken();
        abort_unless($accessToken instanceof AccessToken, 401);

        $token = Token::query()->find($accessToken->oauth_access_token_id);
        abort_unless($token instanceof Token, 401);

        $grant = OAuthGrant::query()
            ->where('passport_token_id', $token->getKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->first();
        abort_unless($grant instanceof OAuthGrant, 401);

        try {
            $context = $this->policy->activeForRequest($grant, $user, $request);
        } catch (AuthorizationException) {
            abort(403, 'The OAuth grant is no longer authorized.');
        }

        $this->rateLimit->assertAllowed($grant, $request);

        $now = now();
        OAuthGrant::query()
            ->whereKey($grant->getKey())
            ->where(function ($query): void {
                $query->whereNull('last_used_at')
                    ->orWhere('last_used_at', '<=', now()->subMinute());
            })
            ->update(['last_used_at' => $now, 'updated_at' => $now]);

        $request->attributes->set(TenantContext::class, $context);
        $request->attributes->set(OAuthGrant::class, $grant);
        app()->instance(TenantContext::class, $context);

        return $next($request);
    }
}
