<?php

namespace App\Support\Integrations\Passport;

use App\Support\Integrations\OAuthContextBinding;
use App\Support\Integrations\OAuthGrantWriter;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;

final class AccessTokenRepository extends PassportAccessTokenRepository
{
    public function __construct(
        Dispatcher $events,
        private readonly OAuthContextBinding $binding,
        private readonly OAuthGrantWriter $grants,
    ) {
        parent::__construct($events);
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        parent::persistNewAccessToken($accessTokenEntity);

        $tokenId = (string) $accessTokenEntity->getIdentifier();
        $userId = (string) $accessTokenEntity->getUserIdentifier();
        $clientId = (string) $accessTokenEntity->getClient()->getIdentifier();

        if ($this->binding->authCodeId() !== null) {
            $this->grants->attachAccessTokenToAuthCode($this->binding->authCodeId(), $tokenId, $userId, $clientId);

            return;
        }

        if ($this->binding->refreshTokenId() !== null) {
            $this->grants->rotateAccessTokenFromRefreshToken($this->binding->refreshTokenId(), $tokenId);
        }
    }
}
