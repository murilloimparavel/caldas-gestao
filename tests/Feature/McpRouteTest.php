<?php

use App\Actions\Identity\OnboardTenant;
use App\Enums\MembershipStatus;
use App\Models\Integrations\OAuthGrant;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use App\Support\Integrations\OAuthResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\PersonalAccessTokenFactory;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    config([
        'app.url' => 'https://mcp.example.test',
        'mcp.authorization_server' => 'https://mcp.example.test',
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'integration.oauth.enabled' => true,
        'integration.oauth.resource' => 'https://mcp.example.test/mcp/integration',
    ]);
    Passport::loadKeysFrom(mcpRoutePassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('MCP route tests', 'users');
});

afterAll(function (): void {
    $directory = mcpRoutePassportKeyDirectory();

    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
});

it('keeps the MCP transport and metadata unavailable while OAuth is disabled', function (): void {
    config(['integration.oauth.enabled' => false]);

    $this->postJson('/mcp/integration', [])->assertNotFound();
    $this->getJson('/.well-known/oauth-protected-resource')->assertNotFound();
    $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
});

it('does not charge the MCP IP quota while the integration feature is disabled', function (): void {
    $ip = '192.0.2.62';
    $key = 'mcp-ip:'.hash('sha256', $ip);

    config(['integration.oauth.enabled' => false]);

    $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/mcp/integration', [])
        ->assertNotFound();

    expect(RateLimiter::attempts($key))->toBe(0);

    config(['integration.oauth.enabled' => true]);

    $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->call('POST', '/mcp/integration', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'CONTENT_TYPE' => 'text/plain',
        ], 'malformed')
        ->assertUnsupportedMediaType();

    expect(RateLimiter::attempts($key))->toBe(1);
});

it('caps unauthenticated MCP traffic by IP without sharing the budget with another IP', function (): void {
    for ($call = 1; $call <= 600; $call++) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.60'])
            ->withHeaders(mcpHeaders('server/discover'))
            ->postJson('/mcp/integration', mcpRequest('server/discover', $call))
            ->assertUnauthorized();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.60'])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 601))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.61'])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 602))
        ->assertUnauthorized();
});

it('charges malformed and cross-origin unauthenticated requests to the MCP IP quota before validation', function (): void {
    $ip = '198.51.100.62';

    for ($call = 1; $call <= 600; $call++) {
        if ($call % 2 === 0) {
            $this->call('POST', '/mcp/integration', [], [], [], [
                'REMOTE_ADDR' => $ip,
                'HTTP_ORIGIN' => 'https://attacker.example',
                'CONTENT_TYPE' => 'text/plain',
            ], 'malformed request')
                ->assertUnsupportedMediaType();

            continue;
        }

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders([...mcpHeaders('server/discover'), 'Origin' => 'https://attacker.example'])
            ->postJson('/mcp/integration', mcpRequest('server/discover', $call))
            ->assertForbidden();
    }

    $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 601))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('keeps IP and validated-grant quota enforcement attached when routes are cached', function (): void {
    $routeCachePath = sys_get_temp_dir().'/mcp-route-cache-'.Str::uuid().'.php';
    $environment = [
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_ROUTES_CACHE' => $routeCachePath,
    ];

    $cache = new Process([
        PHP_BINARY,
        'artisan',
        'route:cache',
        '--no-ansi',
    ], base_path(), $environment);

    try {
        $cache->mustRun();
        expect($cache->getExitCode())->toBe(0);

        $probe = new Process([
            PHP_BINARY,
            '-r',
            <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            if (! $app->routesAreCached()) {
                exit(2);
            }

            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('mcp.integration');
            $middleware = $app->make(\Illuminate\Routing\Router::class)->gatherRouteMiddleware($route);

            if (! in_array(\App\Http\Middleware\EnforceMcpIpRateLimit::class, $middleware, true)) {
                exit(3);
            }

            if (! in_array(\App\Http\Middleware\RequireOAuthGrant::class, $middleware, true)) {
                exit(1);
            }

            $ipGuardPosition = array_search(\App\Http\Middleware\EnforceMcpIpRateLimit::class, $middleware, true);
            $validationPosition = array_search(\App\Http\Middleware\ValidateMcpRequest::class, $middleware, true);
            $authPosition = null;

            foreach ($middleware as $position => $name) {
                if ($name === 'auth:api' || head(explode(':', $name)) === \Illuminate\Auth\Middleware\Authenticate::class) {
                    $authPosition = $position;
                    break;
                }
            }

            if ($ipGuardPosition === false || $validationPosition === false || $authPosition === null || $ipGuardPosition >= $validationPosition || $validationPosition >= $authPosition) {
                exit(4);
            }
            PHP,
        ], base_path(), $environment);

        $probe->mustRun();
        expect($probe->getExitCode())->toBe(0);
    } finally {
        (new Process([
            PHP_BINARY,
            'artisan',
            'route:clear',
            '--no-ansi',
        ], base_path(), $environment))->run();

        if (is_file($routeCachePath)) {
            unlink($routeCachePath);
        }
    }
});

it('publishes fixed protected-resource and authorization metadata without DCR', function (): void {
    $protected = $this->getJson('/.well-known/oauth-protected-resource/mcp/integration')
        ->assertSuccessful();
    $protected->assertJsonPath('resource', 'https://mcp.example.test/mcp/integration')
        ->assertJsonPath('authorization_servers.0', 'https://mcp.example.test')
        ->assertJsonPath('scopes_supported', ['mcp:use'])
        ->assertJsonMissingPath('registration_endpoint');

    $authorization = $this->getJson('/.well-known/oauth-authorization-server')
        ->assertSuccessful();
    $authorization->assertJsonPath('authorization_endpoint', 'https://mcp.example.test/oauth/authorize')
        ->assertJsonPath('token_endpoint', 'https://mcp.example.test/oauth/token')
        ->assertJsonPath('code_challenge_methods_supported', ['S256'])
        ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token'])
        ->assertJsonMissingPath('registration_endpoint')
        ->assertJsonPath('client_id_metadata_document_supported', false);

    $this->postJson('/oauth/register', [])->assertNotFound();
});

it('keeps OAuth discovery anchored to canonical HTTPS config when the request Host is forged', function (): void {
    $this->getJson('/.well-known/oauth-protected-resource/mcp/integration', ['Host' => 'attacker.example'])
        ->assertSuccessful()
        ->assertJsonPath('resource', 'https://mcp.example.test/mcp/integration')
        ->assertJsonPath('authorization_servers.0', 'https://mcp.example.test');

    $this->getJson('/.well-known/oauth-authorization-server', ['Host' => 'attacker.example'])
        ->assertSuccessful()
        ->assertJsonPath('issuer', 'https://mcp.example.test')
        ->assertJsonPath('authorization_endpoint', 'https://mcp.example.test/oauth/authorize')
        ->assertJsonPath('token_endpoint', 'https://mcp.example.test/oauth/token');
});

it('fails closed when OAuth canonical endpoint config is not HTTPS', function (): void {
    config(['mcp.authorization_server' => 'http://mcp.example.test']);

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertServiceUnavailable();
});

it('requires a Passport bearer grant with the MCP scope', function (): void {
    $fixture = mcpRouteFixture(['context:read']);

    $this->withToken($fixture['access_token'])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 1))
        ->assertSuccessful()
        ->assertJsonPath('result.resultType', 'complete');

    $fixture['grant']->forceFill(['revoked_at' => now()])->save();

    $this->withToken($fixture['access_token'])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 2))
        ->assertForbidden();
});

it('serves discovery, initialize, and tools over the protected MCP transport', function (): void {
    $fixture = mcpRouteFixture(['context:read']);
    $headers = mcpHeaders('server/discover');

    $this->withToken($fixture['access_token'])->withHeaders($headers)
        ->postJson('/mcp/integration', mcpRequest('server/discover', 1))
        ->assertSuccessful()
        ->assertJsonPath('result.supportedVersions.0', '2026-07-28');

    $this->withToken($fixture['access_token'])->withHeaders(mcpHeaders('initialize'))
        ->postJson('/mcp/integration', mcpRequest('initialize', 2, [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'clientInfo' => ['name' => 'route-test', 'version' => '1.0.0'],
        ]))
        ->assertSuccessful()
        ->assertJsonPath('result.protocolVersion', '2025-11-25');

    $this->withToken($fixture['access_token'])->withHeaders(mcpHeaders('tools/call', 'read-integration-context'))
        ->postJson('/mcp/integration', mcpRequest('tools/call', 3, [
            'name' => 'read-integration-context',
            'arguments' => [],
        ]))
        ->assertSuccessful()
        ->assertJsonPath('result.resultType', 'complete')
        ->assertJsonPath('result.structuredContent.data.actor.authenticated', true);
});

it('blocks a collaborator from listing or calling MCP tools despite an active grant', function (): void {
    $fixture = mcpRouteCollaboratorFixture(['context:read']);

    $this->withToken($fixture['access_token'])
        ->withHeaders(mcpHeaders('tools/list'))
        ->postJson('/mcp/integration', mcpRequest('tools/list', 1))
        ->assertForbidden();

    $this->withToken($fixture['access_token'])
        ->withHeaders(mcpHeaders('tools/call', 'read-integration-context'))
        ->postJson('/mcp/integration', mcpRequest('tools/call', 2, [
            'name' => 'read-integration-context',
            'arguments' => [],
        ]))
        ->assertForbidden();

    expect($fixture['grant']->fresh()->last_used_at)->toBeNull();
});

it('does not let an owners MCP grant be rebound to another tenants unit', function (): void {
    $first = mcpRouteFixture(['context:read']);
    $second = mcpRouteFixture(['context:read']);

    expect($first['owner']->is($second['owner']))->toBeFalse()
        ->and($first['tenant']->is($second['tenant']))->toBeFalse()
        ->and($first['unit']->is($second['unit']))->toBeFalse();

    $first['grant']->forceFill([
        'tenant_id' => $second['tenant']->getKey(),
        'unit_id' => $second['unit']->getKey(),
    ])->save();

    Auth::forgetGuards();
    $this->withToken($first['access_token'])
        ->withHeaders(mcpHeaders('tools/call', 'read-integration-context'))
        ->postJson('/mcp/integration', mcpRequest('tools/call', 1, [
            'name' => 'read-integration-context',
            'arguments' => [],
        ]))
        ->assertForbidden();

    expect($first['grant']->fresh()->last_used_at)->toBeNull();

    $first['grant']->forceFill([
        'tenant_id' => $first['tenant']->getKey(),
        'unit_id' => $first['unit']->getKey(),
    ])->save();

    Auth::forgetGuards();
    $this->withToken($second['access_token'])
        ->withHeaders(mcpHeaders('tools/call', 'read-integration-context'))
        ->postJson('/mcp/integration', mcpRequest('tools/call', 2, [
            'name' => 'read-integration-context',
            'arguments' => [],
        ]))
        ->assertSuccessful()
        ->assertJsonPath('result.resultType', 'complete');

    expect($second['grant']->fresh()->last_used_at)->not->toBeNull();
});

it('enforces the MCP limiter after 60 calls and separates the same user by IP', function (): void {
    $first = mcpRouteFixture(['context:read']);

    for ($id = 1; $id <= 60; $id++) {
        $response = $this->withToken($first['access_token'])
            ->withHeaders(mcpHeaders('server/discover'))
            ->postJson('/mcp/integration', mcpRequest('server/discover', $id));

        expect($response->status())->toBe(200, 'MCP call '.$id.' was rate limited too early.');
    }

    $this->withToken($first['access_token'])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 61))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    $secondIpResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->withToken($first['access_token'])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 62));

    expect($secondIpResponse->status())->toBe(200, 'The same OAuth user shared a quota across different IPs.');
});

it('gives distinct OAuth users independent MCP quotas from the same IP', function (): void {
    $first = mcpRouteFixture(['context:read']);
    $second = mcpRouteFixture(['context:read']);

    expect($first['owner']->is($second['owner']))->toBeFalse();
    expect($first['access_token'])->not->toBe($second['access_token']);

    foreach ([[$first, 1], [$second, 1001]] as [$fixture, $requestId]) {
        Auth::forgetGuards();

        for ($call = 0; $call < 60; $call++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
                ->withToken($fixture['access_token'])
                ->withHeaders(mcpHeaders('server/discover'))
                ->postJson('/mcp/integration', mcpRequest('server/discover', $requestId + $call));

            expect($response->status())->toBe(200, 'An OAuth user was rate limited before their own 60-call quota.');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.25'])
            ->withToken($fixture['access_token'])
            ->withHeaders(mcpHeaders('server/discover'))
            ->postJson('/mcp/integration', mcpRequest('server/discover', $requestId + 60))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }
});

it('shares the MCP user and IP quota across that users OAuth grants', function (): void {
    $fixture = mcpRouteFixture(['context:read']);
    $secondCredential = mcpRouteAdditionalCredential($fixture, ['context:read']);

    for ($call = 1; $call <= 60; $call++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.44'])
            ->withToken($fixture['access_token'])
            ->withHeaders(mcpHeaders('server/discover'))
            ->postJson('/mcp/integration', mcpRequest('server/discover', $call))
            ->assertSuccessful();
    }

    Auth::forgetGuards();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.44'])
        ->withToken($secondCredential['access_token'])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 61))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');

    expect($secondCredential['grant']->fresh()->last_used_at)->toBeNull();
});

it('does not charge an owners MCP quota for unauthenticated or denied requests', function (): void {
    $fixture = mcpRouteFixture(['context:read']);
    $collaborator = mcpRouteCollaboratorFixture(['context:read']);

    for ($call = 1; $call <= 5; $call++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
            ->withHeaders(mcpHeaders('server/discover'))
            ->postJson('/mcp/integration', mcpRequest('server/discover', $call))
            ->assertUnauthorized();
    }

    Auth::forgetGuards();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
        ->withToken($collaborator['access_token'])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 6))
        ->assertForbidden();

    Auth::forgetGuards();
    for ($call = 1; $call <= 60; $call++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
            ->withToken($fixture['access_token'])
            ->withHeaders(mcpHeaders('server/discover'))
            ->postJson('/mcp/integration', mcpRequest('server/discover', $call))
            ->assertSuccessful();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
        ->withToken($fixture['access_token'])
        ->withHeaders(mcpHeaders('server/discover'))
        ->postJson('/mcp/integration', mcpRequest('server/discover', 61))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('rejects non-json and cross-origin MCP requests', function (): void {
    $fixture = mcpRouteFixture(['context:read']);

    $this->withToken($fixture['access_token'])
        ->withHeaders([...mcpHeaders('server/discover'), 'Origin' => 'https://attacker.example'])
        ->postJson('/mcp/integration', mcpRequest('server/discover', 1))
        ->assertForbidden();

    $this->call('POST', '/mcp/integration', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer '.$fixture['access_token'],
        'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
        'HTTP_MCP_METHOD' => 'server/discover',
        'HTTP_ORIGIN' => (string) config('app.url'),
        'CONTENT_TYPE' => 'text/plain',
    ], json_encode(mcpRequest('server/discover', 2), JSON_THROW_ON_ERROR))
        ->assertUnsupportedMediaType();
});

it('rejects MCP origins containing any user information, path, query, fragment, or invalid URL structure', function (string $origin): void {
    $fixture = mcpRouteFixture(['context:read']);

    $this->withToken($fixture['access_token'])
        ->withHeaders([...mcpHeaders('server/discover'), 'Origin' => $origin])
        ->postJson('/mcp/integration', mcpRequest('server/discover', 1))
        ->assertForbidden()
        ->assertJsonPath('error', 'invalid_origin');
})->with([
    'userinfo' => 'https://user@mcp.example.test',
    'password' => 'https://user:secret@mcp.example.test',
    'path' => 'https://mcp.example.test/path',
    'query' => 'https://mcp.example.test?x=1',
    'fragment' => 'https://mcp.example.test#section',
    'missing host' => 'https://',
    'malformed port' => 'https://mcp.example.test:invalid',
]);

it('accepts a valid MCP origin with case-insensitive scheme and host and matching port', function (): void {
    config(['app.url' => 'https://mcp.example.test:8443']);
    $fixture = mcpRouteFixture(['context:read']);

    $this->withToken($fixture['access_token'])
        ->withHeaders([...mcpHeaders('server/discover'), 'Origin' => 'HTTPS://MCP.EXAMPLE.TEST:8443'])
        ->postJson('/mcp/integration', mcpRequest('server/discover', 1))
        ->assertSuccessful()
        ->assertJsonPath('result.resultType', 'complete');
});

it('shows the selected context and explicit least-privilege capability choices in consent', function (): void {
    $fixture = mcpRouteFixture(['context:read']);
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'MCP consent test',
        ['https://client.example.test/callback'],
        false,
    );

    $query = [
        'response_type' => 'code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.example.test/callback',
        'scope' => 'mcp:use',
        'resource' => OAuthResource::mcp(),
        'tenant_id' => $fixture['tenant']->getKey(),
        'unit_id' => $fixture['unit']->getKey(),
        'code_challenge' => Str::random(43),
        'code_challenge_method' => 'S256',
        'state' => Str::random(20),
    ];

    $this->actingAs($fixture['owner'])
        ->get('/oauth/authorize?'.http_build_query($query))
        ->assertSuccessful()
        ->assertSee($fixture['tenant']->name)
        ->assertSee($fixture['unit']->name)
        ->assertSee('context:read')
        ->assertSee('catalog:read')
        ->assertSee('setup:read')
        ->assertSee('operations:propose')
        ->assertSee('Customers, contacts, notes, sales');
});

/** @param list<string> $capabilities */
function mcpRouteFixture(array $capabilities): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'MCP route '.Str::random(8),
        'slug' => 'mcp-route-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);

    $result = app(PersonalAccessTokenFactory::class)->make(
        (string) $owner->getKey(),
        'MCP route test',
        ['mcp:use'],
        'users',
    );
    $token = $result->getToken();
    expect($token)->not->toBeNull();

    $grant = OAuthGrant::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'client_id' => $token->client_id,
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'resource' => OAuthResource::mcp(),
        'capabilities' => $capabilities,
        'expires_at' => now()->addMinutes(15),
    ]);

    return [
        'owner' => $owner,
        'tenant' => $tenant,
        'unit' => $unit,
        'grant' => $grant,
        'access_token' => $result->accessToken,
    ];
}

/** @param array{owner: User, tenant: Tenant, unit: Unit} $fixture
 * @param  list<string>  $capabilities
 */
function mcpRouteAdditionalCredential(array $fixture, array $capabilities): array
{
    $result = app(PersonalAccessTokenFactory::class)->make(
        (string) $fixture['owner']->getKey(),
        'Additional MCP route test',
        ['mcp:use'],
        'users',
    );
    $token = $result->getToken();
    expect($token)->not->toBeNull();

    $grant = OAuthGrant::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'client_id' => $token->client_id,
        'user_id' => $fixture['owner']->getKey(),
        'tenant_id' => $fixture['tenant']->getKey(),
        'unit_id' => $fixture['unit']->getKey(),
        'resource' => OAuthResource::mcp(),
        'capabilities' => $capabilities,
        'expires_at' => now()->addMinutes(15),
    ]);

    return ['grant' => $grant, 'access_token' => $result->accessToken];
}

/** @param list<string> $capabilities */
function mcpRouteCollaboratorFixture(array $capabilities): array
{
    $fixture = mcpRouteFixture($capabilities);
    $collaborator = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $membership = Membership::factory()->create([
        'tenant_id' => $fixture['tenant']->getKey(),
        'user_id' => $collaborator->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($fixture['unit'])->create();

    $result = app(PersonalAccessTokenFactory::class)->make(
        (string) $collaborator->getKey(),
        'MCP collaborator route test',
        ['mcp:use'],
        'users',
    );
    $token = $result->getToken();
    expect($token)->not->toBeNull();

    $grant = OAuthGrant::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'client_id' => $token->client_id,
        'user_id' => $collaborator->getKey(),
        'tenant_id' => $fixture['tenant']->getKey(),
        'unit_id' => $fixture['unit']->getKey(),
        'resource' => OAuthResource::mcp(),
        'capabilities' => $capabilities,
        'expires_at' => now()->addMinutes(15),
    ]);

    return [
        ...$fixture,
        'owner' => $collaborator,
        'grant' => $grant,
        'access_token' => $result->accessToken,
    ];
}

function mcpRoutePassportKeyDirectory(): string
{
    static $directory;

    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/mcp-route-passport-'.Str::uuid();
        mkdir($directory, 0700, true);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        expect($key)->not->toBeFalse();
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        file_put_contents($directory.'/oauth-private.key', $privateKey);
        file_put_contents($directory.'/oauth-public.key', $details['key']);
        chmod($directory.'/oauth-private.key', 0600);
        chmod($directory.'/oauth-public.key', 0600);
    }

    return $directory;
}

/** @return array<string, string> */
function mcpHeaders(string $method, ?string $name = null): array
{
    return array_filter([
        'Accept' => 'application/json',
        'Origin' => (string) config('app.url'),
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => $method,
        'Mcp-Name' => $name,
    ]);
}

/** @param array<string, mixed> $params */
function mcpRequest(string $method, int $id, array $params = []): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => $method,
        'params' => [
            ...$params,
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => [],
            ],
        ],
    ];
}
