<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\OAuthGrant;
use App\Models\User;
use App\Policies\IntegrationAdminPolicy;
use App\Policies\OAuthGrantPolicy;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Laravel\Passport\Token;

class IntegrationMcpContextResolver
{
    public function __construct(
        private readonly IntegrationAdminPolicy $integrationAdmin,
        private readonly OAuthGrantPolicy $oauthGrantPolicy,
    ) {}

    public function resolve(): IntegrationMcpContext
    {
        $request = app(Request::class);
        $user = $request->user();
        $context = $request->attributes->get(TenantContext::class);
        $credential = $request->attributes->get(IntegrationCredential::class);
        $grant = $request->attributes->get(OAuthGrant::class);

        if ($grant instanceof OAuthGrant) {
            if ($credential instanceof IntegrationCredential) {
                throw new AuthorizationException('The MCP request contains conflicting integration credentials.');
            }

            if (! $user instanceof User) {
                throw new AuthorizationException('An authenticated MCP integration context is required.');
            }

            $grantContext = $this->oauthGrantPolicy->activeForRequest($grant, $user, $request);

            if ($context instanceof TenantContext
                && ((string) $context->tenant->getKey() !== (string) $grantContext->tenant->getKey()
                    || (string) $context->unit?->getKey() !== (string) $grantContext->unit?->getKey())) {
                throw new AuthorizationException('The MCP OAuth context does not match the active grant.');
            }

            return new IntegrationMcpContext($user, $grantContext, $grant);
        }

        if (! $user instanceof User
            || ! $context instanceof TenantContext
            || ! $credential instanceof IntegrationCredential) {
            throw new AuthorizationException('An authenticated MCP integration context is required.');
        }

        if (! $context->user->is($user)
            || $credential->user_id !== (string) $user->getKey()
            || $credential->tenant_id !== (string) $context->tenant->getKey()
            || $credential->unit_id !== (string) $context->unit?->getKey()
            || $credential->revoked_at !== null
            || ! $credential->expires_at->isFuture()) {
            throw new AuthorizationException('The MCP integration context is no longer valid.');
        }

        if (! $this->integrationAdmin->allows($user, $context)) {
            throw new AuthorizationException('Only an eligible administrative account may use the MCP integration.');
        }

        $token = $credential->passportToken()->first();

        if (! $token instanceof Token
            || $token->revoked
            || ! $token->expires_at?->isFuture()
            || $token->user_id !== (string) $user->getKey()) {
            throw new AuthorizationException('The MCP integration credential is no longer valid.');
        }

        return new IntegrationMcpContext($user, $context, $credential);
    }
}
