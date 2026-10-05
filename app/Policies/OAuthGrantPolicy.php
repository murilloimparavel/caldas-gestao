<?php

namespace App\Policies;

use App\Models\Integrations\OAuthGrant;
use App\Models\User;
use App\Support\Integrations\OAuthResource;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Laravel\Passport\Token;

final class OAuthGrantPolicy
{
    public function __construct(private readonly IntegrationAdminPolicy $integrationAdmin) {}

    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $capabilities
     */
    public function authorizeContext(User $user, TenantContext $context, string $resource, array $scopes, array $capabilities = []): void
    {
        if ($resource !== OAuthResource::mcp()) {
            throw new AuthorizationException('The OAuth resource is not registered for this integration.');
        }

        if ($scopes !== ['mcp:use']) {
            throw new AuthorizationException('The OAuth request must ask only for the MCP scope.');
        }

        $this->assertCapabilities($capabilities === [] ? $this->defaultCapabilities() : $capabilities);

        if (! $this->integrationAdmin->allows($user, $context)) {
            throw new AuthorizationException('An eligible integration administrator is required.');
        }
    }

    public function activeForRequest(OAuthGrant $grant, User $user, Request $request): TenantContext
    {
        $resource = OAuthResource::fromRequest($request) ?? OAuthResource::mcp();

        if ($resource !== OAuthResource::mcp()) {
            throw new AuthorizationException('The OAuth resource does not match the grant.');
        }

        $context = TenantContext::forUser(
            $user,
            (string) $grant->tenant_id,
            $grant->unit_id === null ? null : (string) $grant->unit_id,
        );

        $this->assertActiveForContext($grant, $user, $context);

        return $context;
    }

    /**
     * Revalidate a grant without relying on the current HTTP request.
     *
     * Proposal review and confirmation happen in the authenticated web
     * session, so they cannot use the MCP request's resource parameter. The
     * grant's persisted resource and capability set remain authoritative.
     */
    public function assertActiveForContext(OAuthGrant $grant, User $user, TenantContext $context): void
    {
        if (! $grant->user->is($user)) {
            throw new AuthorizationException('The OAuth grant does not belong to this user.');
        }

        if ($grant->revoked_at !== null || $grant->expires_at->isPast()) {
            throw new AuthorizationException('The OAuth grant is inactive.');
        }

        $token = $grant->passportToken;

        if (! $token instanceof Token
            || $token->revoked
            || $token->expires_at?->isPast()
            || $token->user_id !== (string) $user->getKey()
            || ! $token->can('mcp:use')) {
            throw new AuthorizationException('The OAuth access token is inactive.');
        }

        if ($grant->resource !== OAuthResource::mcp()
            || $this->invalidCapabilities($grant->capabilities)
            || $context->user->isNot($user)
            || (string) $context->tenant->getKey() !== (string) $grant->tenant_id
            || (string) $context->unit?->getKey() !== (string) $grant->unit_id
            || ! $this->integrationAdmin->allows($user, $context)) {
            throw new AuthorizationException('The integration administrator is no longer eligible for this grant.');
        }
    }

    public function assertExchange(OAuthGrant $grant, User $user, string $resource): void
    {
        if (! $grant->user->is($user)
            || $grant->revoked_at !== null
            || $grant->expires_at->isPast()
            || $resource !== $grant->resource
            || $resource !== OAuthResource::mcp()) {
            throw new AuthorizationException('The OAuth resource does not match the grant.');
        }

        $context = TenantContext::forUser(
            $user,
            (string) $grant->tenant_id,
            $grant->unit_id === null ? null : (string) $grant->unit_id,
        );

        if (! $this->integrationAdmin->allows($user, $context)) {
            throw new AuthorizationException('The integration administrator is no longer eligible.');
        }

        $this->assertCapabilities($grant->capabilities);
    }

    /** @return list<string> */
    public function defaultCapabilities(): array
    {
        /** @var list<string> $capabilities */
        $capabilities = config('integration.oauth.capabilities', ['context:read']);

        return array_values(array_intersect($this->allowedCapabilities(), array_map(strval(...), $capabilities)));
    }

    /** @return list<string> */
    public function allowedCapabilities(): array
    {
        return ['context:read', 'catalog:read', 'setup:read', 'operations:propose'];
    }

    /** @param list<string> $capabilities */
    private function assertCapabilities(array $capabilities): void
    {
        if ($this->invalidCapabilities($capabilities)) {
            throw new AuthorizationException('The OAuth request contains an unsupported or duplicate MCP capability.');
        }
    }

    /** @param list<string> $capabilities */
    private function invalidCapabilities(array $capabilities): bool
    {
        return $capabilities === []
            || count($capabilities) !== count(array_unique($capabilities))
            || array_diff($capabilities, $this->allowedCapabilities()) !== [];
    }
}
