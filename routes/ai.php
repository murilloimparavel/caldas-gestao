<?php

use App\Http\Middleware\EnforceMcpIpRateLimit;
use App\Http\Middleware\RequireIntegrationOAuthEnabled;
use App\Http\Middleware\RequireOAuthGrant;
use App\Http\Middleware\ValidateMcpRequest;
use App\Mcp\Servers\IntegrationServer;
use App\Support\Integrations\OAuthEndpointConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Passport;

$protectedResourceMetadata = static fn (): JsonResponse => response()->json([
    'resource' => app(OAuthEndpointConfiguration::class)->resource(),
    'authorization_servers' => [app(OAuthEndpointConfiguration::class)->authorizationServer()],
    'scopes_supported' => ['mcp:use'],
]);

$authorizationServerMetadata = static fn (): JsonResponse => response()->json([
    'issuer' => app(OAuthEndpointConfiguration::class)->authorizationServer(),
    'authorization_endpoint' => app(OAuthEndpointConfiguration::class)->endpoint('passport.authorizations.authorize'),
    'token_endpoint' => app(OAuthEndpointConfiguration::class)->endpoint('passport.token'),
    'response_types_supported' => ['code'],
    'code_challenge_methods_supported' => ['S256'],
    'scopes_supported' => ['mcp:use'],
    'grant_types_supported' => ['authorization_code', 'refresh_token'],
    'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
    'client_id_metadata_document_supported' => false,
]);

Passport::authorizationView('mcp.integration-authorize');

Route::middleware(RequireIntegrationOAuthEnabled::class)->group(function () use ($protectedResourceMetadata, $authorizationServerMetadata): void {
    Route::get('/.well-known/oauth-protected-resource', $protectedResourceMetadata)
        ->name('mcp.oauth.protected-resource');

    Route::get('/.well-known/oauth-protected-resource/{path}', function (string $path) use ($protectedResourceMetadata): JsonResponse {
        abort_unless($path === 'mcp/integration', 404);

        return $protectedResourceMetadata();
    })
        ->where('path', '.*')
        ->name('mcp.oauth.protected-resource.nested');

    Route::get('/.well-known/oauth-authorization-server', $authorizationServerMetadata)
        ->name('mcp.oauth.authorization-server');

    Route::get('/.well-known/oauth-authorization-server/{path}', function (string $path) use ($authorizationServerMetadata): JsonResponse {
        abort_unless($path === 'mcp/integration', 404);

        return $authorizationServerMetadata();
    })
        ->where('path', '.*')
        ->name('mcp.oauth.authorization-server.nested');
});

Mcp::web('/mcp/integration', IntegrationServer::class)
    ->middleware([
        RequireIntegrationOAuthEnabled::class,
        EnforceMcpIpRateLimit::class,
        ValidateMcpRequest::class,
        'auth:api',
        RequireOAuthGrant::class,
    ])
    ->name('mcp.integration');
