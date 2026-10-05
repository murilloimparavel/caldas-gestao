<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\OAuthGrant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class IntegrationMcpContext
{
    public function __construct(
        public User $user,
        public TenantContext $tenantContext,
        public IntegrationCredential|OAuthGrant $credential,
    ) {}

    public function requireCapability(string $capability): self
    {
        if (! in_array($capability, $this->credential->capabilities, true)) {
            throw new AuthorizationException("The MCP capability [{$capability}] is not granted.");
        }

        return $this;
    }
}
