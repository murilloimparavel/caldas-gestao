<?php

namespace App\Support\Integrations;

use App\Models\Integrations\OAuthGrant;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;

/**
 * Holds the authenticated OAuth context while Passport completes one request.
 *
 * Passport's repository interfaces do not receive the authorization code or
 * refresh token request, so the application binds that identifier before
 * handing control to the official authorization server.
 */
final class OAuthContextBinding
{
    /** @var array{user_id:string, client_id:string, tenant_id:string, unit_id:string|null, resource:string, capabilities:list<string>}|null */
    private ?array $authorization = null;

    private ?string $authCodeId = null;

    private ?string $refreshTokenId = null;

    /**
     * @return array{user_id:string, client_id:string, tenant_id:string, unit_id:string|null, resource:string, capabilities:list<string>}|null
     */
    public function authorization(): ?array
    {
        return $this->authorization;
    }

    /**
     * @param  list<string>  $capabilities
     */
    public function bindAuthorization(
        User $user,
        string $clientId,
        Tenant $tenant,
        ?Unit $unit,
        string $resource,
        array $capabilities,
    ): void {
        $this->authorization = [
            'user_id' => (string) $user->getAuthIdentifier(),
            'client_id' => $clientId,
            'tenant_id' => (string) $tenant->getKey(),
            'unit_id' => $unit?->getKey(),
            'resource' => $resource,
            'capabilities' => array_map(strval(...), $capabilities),
        ];
    }

    public function bindAuthorizationGrant(OAuthGrant $grant): void
    {
        $this->authorization = [
            'user_id' => (string) $grant->user_id,
            'client_id' => (string) $grant->client_id,
            'tenant_id' => (string) $grant->tenant_id,
            'unit_id' => $grant->unit_id === null ? null : (string) $grant->unit_id,
            'resource' => $grant->resource,
            'capabilities' => array_map(strval(...), $grant->capabilities),
        ];
    }

    public function bindAuthCode(string $authCodeId): void
    {
        $this->authCodeId = $authCodeId;
        $this->refreshTokenId = null;
    }

    public function bindRefreshToken(string $refreshTokenId): void
    {
        $this->refreshTokenId = $refreshTokenId;
        $this->authCodeId = null;
    }

    public function authCodeId(): ?string
    {
        return $this->authCodeId;
    }

    public function refreshTokenId(): ?string
    {
        return $this->refreshTokenId;
    }

    public function clearExchange(): void
    {
        $this->authCodeId = null;
        $this->refreshTokenId = null;
        $this->authorization = null;
    }
}
