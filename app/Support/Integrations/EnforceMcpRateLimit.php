<?php

namespace App\Support\Integrations;

use App\Models\Integrations\OAuthGrant;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

final class EnforceMcpRateLimit
{
    private const MAX_ATTEMPTS = 60;

    private const DECAY_SECONDS = 60;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function assertAllowed(OAuthGrant $grant, Request $request): void
    {
        $userId = $grant->user_id;
        abort_unless($userId !== '', 401);

        $key = 'mcp:'.hash('sha256', $userId.'|'.$request->ip());

        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $retryAfter = max(1, $this->limiter->availableIn($key));

            throw new ThrottleRequestsException('Too Many Attempts.', null, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => self::MAX_ATTEMPTS,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            ]);
        }

        $this->limiter->hit($key, self::DECAY_SECONDS);
    }
}
