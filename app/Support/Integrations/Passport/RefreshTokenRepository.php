<?php

namespace App\Support\Integrations\Passport;

use App\Support\Integrations\OAuthContextBinding;
use App\Support\Integrations\OAuthGrantWriter;
use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Passport\Bridge\RefreshTokenRepository as PassportRefreshTokenRepository;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

final class RefreshTokenRepository extends PassportRefreshTokenRepository
{
    public function __construct(
        Dispatcher $events,
        private readonly OAuthContextBinding $binding,
        private readonly OAuthGrantWriter $grants,
    ) {
        parent::__construct($events);
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        parent::persistNewRefreshToken($refreshTokenEntity);

        $refreshTokenId = (string) $refreshTokenEntity->getIdentifier();

        if ($this->binding->authCodeId() !== null) {
            $this->grants->attachRefreshTokenToAuthCode(
                $this->binding->authCodeId(),
                $refreshTokenId,
                Carbon::instance($refreshTokenEntity->getExpiryDateTime()),
            );

            return;
        }

        if ($this->binding->refreshTokenId() !== null) {
            $this->grants->rotateRefreshToken(
                $this->binding->refreshTokenId(),
                $refreshTokenId,
                Carbon::instance($refreshTokenEntity->getExpiryDateTime()),
            );
        }
    }
}
