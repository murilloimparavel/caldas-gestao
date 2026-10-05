<?php

namespace App\Support\Integrations\Passport;

use App\Support\Integrations\OAuthContextBinding;
use App\Support\Integrations\OAuthGrantWriter;
use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

final class AuthCodeRepository extends PassportAuthCodeRepository
{
    public function __construct(
        private readonly OAuthContextBinding $binding,
        private readonly OAuthGrantWriter $grants,
    ) {}

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        parent::persistNewAuthCode($authCodeEntity);

        $context = $this->binding->authorization();

        if ($context === null) {
            throw new \LogicException('An OAuth context is required for integration authorization codes.');
        }

        $this->grants->createForAuthCode(
            (string) $authCodeEntity->getIdentifier(),
            $context,
            (string) $authCodeEntity->getUserIdentifier(),
            (string) $authCodeEntity->getClient()->getIdentifier(),
        );
    }
}
