<?php

namespace App\Http\Controllers\Integrations\Passport;

use App\Models\Integrations\OAuthGrant;
use App\Models\User;
use App\Policies\OAuthGrantPolicy;
use App\Support\Integrations\OAuthContextBinding;
use App\Support\Integrations\OAuthResource;
use Defuse\Crypto\Crypto;
use Laravel\Passport\Http\Controllers\AccessTokenController as PassportAccessTokenController;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

final class AccessTokenController extends PassportAccessTokenController
{
    public function __construct(
        AuthorizationServer $server,
        private readonly OAuthContextBinding $binding,
        private readonly OAuthGrantPolicy $policy,
    ) {
        parent::__construct($server);
    }

    public function issueToken(ServerRequestInterface $psrRequest, ResponseInterface $psrResponse): Response
    {
        $this->prepareExchange($psrRequest);

        try {
            return parent::issueToken($psrRequest, $psrResponse);
        } finally {
            $this->binding->clearExchange();
        }
    }

    private function prepareExchange(ServerRequestInterface $psrRequest): void
    {
        $body = $psrRequest->getParsedBody();
        $body = is_array($body) ? $body : [];
        $resource = $body['resource'] ?? null;

        abort_unless(is_string($resource) && rtrim($resource, '/') === OAuthResource::mcp(), 403);

        $grantType = $body['grant_type'] ?? null;

        if ($grantType === 'authorization_code') {
            $authCodeId = $this->decodeIdentifier($body['code'] ?? null, 'auth_code_id');
            $grant = OAuthGrant::query()->where('auth_code_id', $authCodeId)->first();
            abort_unless($grant instanceof OAuthGrant && $grant->passport_token_id === null, 403);

            $this->assertClient($psrRequest, $grant->client_id);
            $user = $grant->user;
            abort_unless($user instanceof User, 403);
            $this->policy->assertExchange($grant, $user, rtrim($resource, '/'));
            $this->binding->bindAuthCode($authCodeId);

            return;
        }

        if ($grantType === 'refresh_token') {
            $refreshTokenId = $this->decodeIdentifier($body['refresh_token'] ?? null, 'refresh_token_id');
            $grant = OAuthGrant::query()->where('passport_refresh_token_id', $refreshTokenId)->first();
            abort_unless($grant instanceof OAuthGrant, 403);

            $this->assertClient($psrRequest, $grant->client_id);
            $user = $grant->user;
            abort_unless($user instanceof User, 403);
            $this->policy->assertExchange($grant, $user, rtrim($resource, '/'));
            $this->binding->bindRefreshToken($refreshTokenId);

            return;
        }

        abort(403, 'Only the authorization_code and refresh_token grants are allowed for MCP.');
    }

    private function decodeIdentifier(mixed $encrypted, string $key): string
    {
        abort_unless(is_string($encrypted) && $encrypted !== '', 400);

        try {
            $decoded = json_decode(
                Crypto::decryptWithPassword($encrypted, Passport::tokenEncryptionKey(app('encrypter'))),
                true,
                32,
                JSON_THROW_ON_ERROR,
            );
        } catch (\Throwable) {
            abort(400, 'The OAuth credential cannot be decoded.');
        }

        $identifier = $decoded[$key] ?? null;
        abort_unless(is_string($identifier) && $identifier !== '', 400);

        return $identifier;
    }

    private function assertClient(ServerRequestInterface $request, string $clientId): void
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $requestClientId = $body['client_id'] ?? null;

        if (! is_string($requestClientId) || $requestClientId === '') {
            $authorization = $request->getHeaderLine('Authorization');

            if (str_starts_with(strtolower($authorization), 'basic ')) {
                $decoded = base64_decode(substr($authorization, 6), true);
                $requestClientId = is_string($decoded) && str_contains($decoded, ':')
                    ? explode(':', $decoded, 2)[0]
                    : null;
            }
        }

        abort_unless($requestClientId === $clientId, 403);
    }
}
