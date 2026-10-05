<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Policies\OAuthGrantPolicy;
use App\Support\Integrations\OAuthContextBinding;
use App\Support\Integrations\OAuthResource;
use App\Support\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequireOAuthAuthorizationContext
{
    public function __construct(
        private readonly OAuthGrantPolicy $policy,
        private readonly OAuthContextBinding $binding,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('integration.oauth.enabled', false), 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless($user->hasVerifiedEmail() && ! $user->must_change_password, 403);

        try {
            if ($request->isMethod('GET')) {
                $this->bindInitialRequest($request, $user);
            } else {
                $this->bindConsentRequest($request, $user);
            }
        } catch (AuthorizationException) {
            abort(403, 'An eligible integration administrator is required.');
        }

        return $next($request);
    }

    private function bindInitialRequest(Request $request, User $user): void
    {
        // Passport can auto-approve a previously granted client. Always force
        // the administrative consent screen so the selected tenant, unit, and
        // application capabilities are reviewed on every authorization flow.
        $request->query->set('prompt', 'consent');
        $clientId = $this->requiredString($request->query('client_id'));
        $resource = $this->requiredResource($request);
        $scopes = $this->scopes($request->query('scope'));
        $capabilities = $this->capabilities($request->query('capabilities'));

        abort_unless($request->query('response_type') === 'code', 400);
        abort_unless($request->query('code_challenge_method') === 'S256', 400);
        abort_unless($this->requiredString($request->query('code_challenge')) !== null, 400);

        $tenantId = $this->optionalIdentifier($request->query('tenant_id'));
        $unitId = $this->optionalIdentifier($request->query('unit_id'));
        $context = TenantContext::forUser($user, $tenantId, $unitId);
        $request->attributes->set(TenantContext::class, $context);

        $this->policy->authorizeContext($user, $context, $resource, $scopes, $capabilities);
        $this->binding->bindAuthorization($user, $clientId, $context->tenant, $context->unit, $resource, $capabilities === [] ? $this->policy->defaultCapabilities() : $capabilities);
        $request->session()->put('integration.oauth.context', [
            'user_id' => (string) $user->getAuthIdentifier(),
            'client_id' => $clientId,
            'tenant_id' => (string) $context->tenant->getKey(),
            'unit_id' => $context->unit?->getKey(),
            'resource' => $resource,
            'capabilities' => $capabilities === [] ? $this->policy->defaultCapabilities() : $capabilities,
        ]);
    }

    private function bindConsentRequest(Request $request, User $user): void
    {
        $context = $request->session()->get('integration.oauth.context');

        abort_unless(is_array($context), 403);
        abort_unless(($context['user_id'] ?? null) === (string) $user->getAuthIdentifier(), 403);
        abort_unless(($context['client_id'] ?? null) === $this->requiredString($request->input('client_id')), 403);

        $tenantId = $this->requiredString($context['tenant_id'] ?? null);
        $unitId = $this->optionalIdentifier($context['unit_id'] ?? null);
        $resource = $this->requiredResourceValue($context['resource'] ?? null);
        $scopes = ['mcp:use'];
        $capabilities = $this->capabilities($request->input('capabilities'));
        abort_unless($capabilities !== [], 422, 'At least one MCP capability must be selected.');
        $tenantContext = TenantContext::forUser($user, $tenantId, $unitId);

        $this->policy->authorizeContext($user, $tenantContext, $resource, $scopes, $capabilities);
        $this->binding->bindAuthorization($user, (string) $context['client_id'], $tenantContext->tenant, $tenantContext->unit, $resource, $capabilities);
        $request->session()->put('integration.oauth.context.capabilities', $capabilities);
        $request->attributes->set(TenantContext::class, $tenantContext);
        app()->instance(TenantContext::class, $tenantContext);
    }

    private function requiredResource(Request $request): string
    {
        return $this->requiredResourceValue($request->query('resource'));
    }

    private function requiredResourceValue(mixed $resource): string
    {
        abort_unless(is_string($resource) && $resource !== '', 400);
        $resource = rtrim($resource, '/');

        abort_unless($resource === OAuthResource::mcp(), 403);

        return $resource;
    }

    /** @return list<string> */
    private function scopes(mixed $scopes): array
    {
        if (is_string($scopes)) {
            return array_values(array_filter(explode(' ', trim($scopes))));
        }

        if (is_array($scopes)) {
            return array_values(array_map(strval(...), $scopes));
        }

        return [];
    }

    /** @return list<string> */
    private function capabilities(mixed $capabilities): array
    {
        if (is_string($capabilities)) {
            return array_values(array_filter(explode(' ', trim($capabilities))));
        }

        if (is_array($capabilities)) {
            return array_values(array_map(strval(...), $capabilities));
        }

        return [];
    }

    private function requiredString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function optionalIdentifier(mixed $value): ?string
    {
        $identifier = $this->requiredString($value);

        if ($identifier === null) {
            return null;
        }

        abort_unless(Str::isUuid($identifier) || Str::isUlid($identifier), 400);

        return $identifier;
    }
}
