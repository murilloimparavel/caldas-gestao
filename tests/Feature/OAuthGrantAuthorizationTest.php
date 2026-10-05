<?php

use App\Actions\Identity\OnboardTenant;
use App\Enums\MembershipStatus;
use App\Http\Middleware\RequireOAuthGrant;
use App\Mcp\IntegrationMcpContextResolver;
use App\Models\Integrations\OAuthGrant;
use App\Models\Integrations\ProposedOperation;
use App\Models\Integrations\StepUpProof;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use App\Policies\OAuthGrantPolicy;
use App\Support\Integrations\OAuthContextBinding;
use App\Support\Integrations\OAuthGrantWriter;
use App\Support\Integrations\OAuthResource;
use App\Support\Integrations\Passport\AuthCodeRepository;
use App\Support\Integrations\ProposedOperationService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    config([
        'integration.oauth.enabled' => true,
        'integration.oauth.resource' => 'https://mcp.example.test',
    ]);
});

it('authorizes an active MCP grant and rehydrates its tenant unit context', function (): void {
    [$owner, $tenant, $unit, $grant, $token] = oauthGrantFixture();
    $request = oauthGrantRequest($owner, OAuthResource::mcp());

    $response = app(RequireOAuthGrant::class)->handle($request, fn (Request $request) => response()->json([
        'tenant_id' => $request->attributes->get(TenantContext::class)?->tenant->getKey(),
    ]));

    expect($response->getStatusCode())->toBe(200)
        ->and($request->attributes->get(TenantContext::class)->tenant->is($tenant))->toBeTrue()
        ->and($request->attributes->get(TenantContext::class)->unit->is($unit))->toBeTrue()
        ->and($grant->fresh()->last_used_at)->not->toBeNull()
        ->and($token->fresh()->revoked)->toBeFalse();
});

it('blocks a collaborator even when the grant and operational membership are active', function (): void {
    [$owner, $tenant, $unit] = oauthGrantWorkspace();
    $collaborator = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $collaborator->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create();
    [$grant, $token] = oauthGrantFor($collaborator, $tenant, $unit);
    $request = oauthGrantRequest($collaborator, OAuthResource::mcp());

    expect(fn () => app(RequireOAuthGrant::class)->handle($request, fn () => response()->noContent()))
        ->toThrow(HttpException::class);

    expect($grant->fresh()->last_used_at)->toBeNull()
        ->and($token->fresh()->revoked)->toBeFalse()
        ->and($owner->is($collaborator))->toBeFalse();
});

it('revalidates revocation, resource binding, and capability on every MCP call', function (): void {
    [$owner, $tenant, $unit, $grant] = oauthGrantFixture();

    $wrongResourceRequest = oauthGrantRequest($owner, 'https://other.example.test/mcp');
    expect(fn () => app(RequireOAuthGrant::class)->handle($wrongResourceRequest, fn () => response()->noContent()))
        ->toThrow(HttpException::class);

    $grant->forceFill(['revoked_at' => now()])->save();
    $revokedRequest = oauthGrantRequest($owner, OAuthResource::mcp());
    expect(fn () => app(RequireOAuthGrant::class)->handle($revokedRequest, fn () => response()->noContent()))
        ->toThrow(HttpException::class);

    $grant->forceFill(['revoked_at' => null, 'capabilities' => ['unknown:read']])->save();
    $insufficientRequest = oauthGrantRequest($owner, OAuthResource::mcp(), ['mcp:use']);
    expect(fn () => app(RequireOAuthGrant::class)->handle($insufficientRequest, fn () => response()->noContent()))
        ->toThrow(HttpException::class);

    expect($grant->tenant_id)->toBe($tenant->getKey())
        ->and($grant->unit_id)->toBe($unit->getKey());
});

it('revalidates membership and subscription before accepting a previously issued grant', function (): void {
    [$owner, $tenant, $unit, $grant] = oauthGrantFixture();
    $membership = Membership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $owner->getKey())
        ->firstOrFail();
    $membership->forceFill(['status' => MembershipStatus::Revoked, 'revoked_at' => now()])->save();

    expect(fn () => app(RequireOAuthGrant::class)->handle(
        oauthGrantRequest($owner, OAuthResource::mcp()),
        fn () => response()->noContent(),
    ))->toThrow(HttpException::class);

    $membership->forceFill(['status' => MembershipStatus::Active, 'revoked_at' => null])->save();
    TenantSubscription::query()->where('tenant_id', $tenant->getKey())->latest()->firstOrFail()->forceFill([
        'status' => 'cancelled',
    ])->save();

    expect(fn () => app(RequireOAuthGrant::class)->handle(
        oauthGrantRequest($owner, OAuthResource::mcp()),
        fn () => response()->noContent(),
    ))->toThrow(HttpException::class)
        ->and($grant->fresh()->passport_token_id)->not->toBeNull();
});

it('keeps Passport OAuth endpoints closed until the integration feature flag is enabled', function (): void {
    config(['integration.oauth.enabled' => false]);

    $this->postJson('/oauth/token', [])->assertNotFound();
});

it('does not expose dynamic client registration while the MCP transport is gated', function (): void {
    config(['integration.oauth.enabled' => true]);

    $this->postJson('/oauth/register', [
        'client_name' => 'Untrusted client',
        'redirect_uris' => ['https://client.example.test/callback'],
    ])->assertNotFound();
});

it('rejects collaborator consent and PKCE downgrade before Passport can issue a code', function (): void {
    [$owner, $tenant, $unit] = oauthGrantWorkspace();
    $collaborator = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $collaborator->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'MCP security test',
        ['https://client.example.test/callback'],
        false,
    );

    $query = [
        'response_type' => 'code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.example.test/callback',
        'scope' => 'mcp:use',
        'resource' => OAuthResource::mcp(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'code_challenge' => Str::random(43),
        'code_challenge_method' => 'S256',
        'state' => Str::random(20),
    ];

    $this->actingAs($collaborator)->get('/oauth/authorize?'.http_build_query($query))->assertForbidden();

    $query['code_challenge_method'] = 'plain';
    $this->actingAs($owner)->get('/oauth/authorize?'.http_build_query($query))->assertBadRequest();
});

it('rejects an unregistered authorization redirect without redirecting or issuing a code', function (): void {
    Passport::loadKeysFrom(oauthAuthorizationPassportKeyDirectory());
    [$owner, $tenant, $unit] = oauthGrantWorkspace();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'MCP redirect security test',
        ['https://client.example.test/callback'],
        false,
    );

    $query = [
        'response_type' => 'code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://attacker.example/callback',
        'scope' => 'mcp:use',
        'resource' => OAuthResource::mcp(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'code_challenge' => Str::random(43),
        'code_challenge_method' => 'S256',
        'state' => Str::random(20),
    ];

    $response = $this->actingAs($owner)->get('/oauth/authorize?'.http_build_query($query));

    $response->assertUnauthorized()
        ->assertHeaderMissing('Location')
        ->assertDontSee('https://attacker.example/callback')
        ->assertDontSee('auth_code_id');

    expect(OAuthGrant::query()->count())->toBe(0);
});

it('rejects authorization without a PKCE S256 challenge before Passport can issue a code', function (): void {
    Passport::loadKeysFrom(oauthAuthorizationPassportKeyDirectory());
    [$owner, $tenant, $unit] = oauthGrantWorkspace();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'MCP PKCE security test',
        ['https://client.example.test/callback'],
        false,
    );

    $query = [
        'response_type' => 'code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.example.test/callback',
        'scope' => 'mcp:use',
        'resource' => OAuthResource::mcp(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'state' => Str::random(20),
    ];

    $response = $this->actingAs($owner)->get('/oauth/authorize?'.http_build_query($query));

    $response->assertStatus(400)
        ->assertHeaderMissing('Location');

    expect(OAuthGrant::query()->count())->toBe(0)
        ->and(DB::table('oauth_auth_codes')->count())->toBe(0);
});

it('rejects an authorization-code exchange with an incorrect PKCE verifier without issuing an access token', function (): void {
    Passport::loadKeysFrom(oauthAuthorizationPassportKeyDirectory());
    [$owner, $tenant, $unit] = oauthGrantWorkspace();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'MCP PKCE verifier security test',
        ['https://client.example.test/callback'],
        false,
    );
    $codeVerifier = Str::random(64);
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    $capabilities = ['context:read'];
    $query = [
        'response_type' => 'code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.example.test/callback',
        'scope' => 'mcp:use',
        'resource' => OAuthResource::mcp(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'capabilities' => $capabilities,
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
        'state' => Str::random(20),
    ];

    $authorization = $this->actingAs($owner)->get('/oauth/authorize?'.http_build_query($query));
    $authorization->assertSuccessful();
    preg_match('/name="auth_token" value="([^"]+)"/', $authorization->getContent(), $authTokenMatches);
    expect($authTokenMatches[1] ?? null)->toBeString();

    $passkey = $owner->passkeys()->create([
        'name' => 'OAuth PKCE test key',
        'credential_id' => (string) Str::uuid(),
        'credential' => [],
    ]);
    $proofId = (string) Str::uuid();
    StepUpProof::query()->create([
        'id' => $proofId,
        'user_id' => (string) $owner->getKey(),
        'tenant_id' => (string) $tenant->getKey(),
        'unit_id' => (string) $unit->getKey(),
        'passkey_id' => $passkey->getKey(),
        'factor' => 'passkey',
        'purpose' => 'oauth.consent',
        'target_id' => (string) $client->getKey(),
        'command_hash' => hash('sha256', json_encode($capabilities, JSON_THROW_ON_ERROR)),
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(5),
    ]);

    $consent = $this->withSession(['integrations.step_up.proof_id' => $proofId])->post('/oauth/authorize', [
        'client_id' => (string) $client->getKey(),
        'auth_token' => html_entity_decode($authTokenMatches[1], ENT_QUOTES | ENT_HTML5),
        'capabilities' => $capabilities,
    ]);
    $consent->assertRedirect();
    $redirect = (string) $consent->headers->get('Location');
    expect(parse_url($redirect, PHP_URL_SCHEME))->toBe('https')
        ->and(parse_url($redirect, PHP_URL_HOST))->toBe('client.example.test')
        ->and(parse_url($redirect, PHP_URL_PATH))->toBe('/callback');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $redirectParameters);
    $authorizationCode = $redirectParameters['code'] ?? null;
    expect($authorizationCode)->toBeString()->not->toBeEmpty();

    $tokenCountBeforeExchange = Token::query()->count();
    $exchange = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => (string) $client->getKey(),
        'redirect_uri' => 'https://client.example.test/callback',
        'code' => $authorizationCode,
        'code_verifier' => Str::random(64),
        'resource' => OAuthResource::mcp(),
    ]);

    $exchange->assertStatus(400);

    $grant = OAuthGrant::query()->where('client_id', $client->getKey())->firstOrFail();
    expect(Token::query()->count())->toBe($tokenCountBeforeExchange)
        ->and($grant->passport_token_id)->toBeNull()
        ->and($grant->passport_refresh_token_id)->toBeNull();
});

it('resolves an OAuth MCP context and scopes proposal idempotency to the grant', function (): void {
    [$owner, $tenant, $unit, $grant] = oauthGrantFixture();
    $request = oauthGrantRequest($owner, OAuthResource::mcp());
    $request->attributes->set(OAuthGrant::class, $grant);
    $request->attributes->set(TenantContext::class, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()));
    $previousRequest = app(Request::class);
    $previousNamedRequest = app('request');
    app()->instance(Request::class, $request);
    app()->instance('request', $request);
    $request->setUserResolver(fn (): User => $owner);

    try {
        $context = app(IntegrationMcpContextResolver::class)->resolve();
        $input = [
            'name' => 'Corte OAuth',
            'description' => 'Proposta originada por MCP',
            'duration_minutes' => 30,
            'price_cents' => 4500,
        ];
        $operations = app(ProposedOperationService::class);
        $first = $operations->propose($grant, $context->tenantContext, 'service.create', $input, 'oauth-proposal-1');
        $second = $operations->propose($grant, $context->tenantContext, 'service.create', $input, 'oauth-proposal-1');

        expect($context->credential)->toBe($grant)
            ->and($first->getKey())->toBe($second->getKey())
            ->and($first->oauth_grant_id)->toBe($grant->getKey())
            ->and($first->credential_id)->toBeNull()
            ->and(ProposedOperation::query()->where('oauth_grant_id', $grant->getKey())->count())->toBe(1);
    } finally {
        app()->instance(Request::class, $previousRequest);
        app()->instance('request', $previousNamedRequest);
    }
});

it('revalidates an OAuth grant before reading or confirming its proposal', function (): void {
    [$owner, $tenant, $unit, $grant] = oauthGrantFixture();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $proposal = app(ProposedOperationService::class)->propose($grant, $context, 'service.create', [
        'name' => 'Corte revogável',
        'description' => null,
        'duration_minutes' => 30,
        'price_cents' => 4500,
    ], 'oauth-revoked-1');
    $grant->forceFill(['revoked_at' => now()])->save();
    $request = oauthGrantRequest($owner, OAuthResource::mcp());
    $request->attributes->set(OAuthGrant::class, $grant);
    $request->attributes->set(TenantContext::class, $context);
    $previousRequest = app(Request::class);
    $previousNamedRequest = app('request');
    app()->instance(Request::class, $request);
    app()->instance('request', $request);
    $request->setUserResolver(fn (): User => $owner);

    try {
        expect(fn () => app(ProposedOperationService::class)->show($proposal, $owner, $context))
            ->toThrow(AuthorizationException::class)
            ->and(fn () => app(IntegrationMcpContextResolver::class)->resolve())
            ->toThrow(AuthorizationException::class);
    } finally {
        app()->instance(Request::class, $previousRequest);
        app()->instance('request', $previousNamedRequest);
    }
});

it('keeps authorization-code grants refreshable through refresh-token lifetime and rotates expiry', function (): void {
    [$owner, $tenant, $unit] = oauthGrantWorkspace();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'MCP refresh lifecycle test',
        ['https://client.example.test/callback'],
        false,
    );
    $authCodeId = Str::random(80);
    $clientEntity = Mockery::mock(ClientEntityInterface::class);
    $clientEntity->shouldReceive('getIdentifier')->andReturn((string) $client->getKey());
    $authCode = Mockery::mock(AuthCodeEntityInterface::class);
    $authCode->shouldReceive('getIdentifier')->andReturn($authCodeId);
    $authCode->shouldReceive('getUserIdentifier')->andReturn((string) $owner->getKey());
    $authCode->shouldReceive('getClient')->andReturn($clientEntity);
    $authCode->shouldReceive('getScopes')->andReturn(['mcp:use']);
    $authCode->shouldReceive('getExpiryDateTime')->andReturn(now()->addMinutes(5)->toDateTimeImmutable());

    $binding = new OAuthContextBinding;
    $binding->bindAuthorization(
        $owner,
        (string) $client->getKey(),
        $tenant,
        $unit,
        OAuthResource::mcp(),
        ['context:read'],
    );
    $writer = app(OAuthGrantWriter::class);
    (new AuthCodeRepository($binding, $writer))->persistNewAuthCode($authCode);

    $initialAccessExpiresAt = now()->addMinutes(15);
    $accessTokenId = Str::random(80);
    $token = Token::query()->forceCreate([
        'id' => $accessTokenId,
        'user_id' => $owner->getKey(),
        'client_id' => $client->getKey(),
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => $initialAccessExpiresAt,
    ]);
    $grant = $writer->attachAccessTokenToAuthCode($authCodeId, $accessTokenId, (string) $owner->getKey(), (string) $client->getKey());
    $refreshExpiresAt = now()->addDays(30);
    $refreshTokenId = Str::random(80);
    RefreshToken::query()->forceCreate([
        'id' => $refreshTokenId,
        'access_token_id' => $token->getKey(),
        'revoked' => false,
        'expires_at' => $refreshExpiresAt,
    ]);
    $writer->attachRefreshTokenToAuthCode($authCodeId, $refreshTokenId, $refreshExpiresAt);

    expect(abs($grant->fresh()->expires_at->getTimestamp() - $refreshExpiresAt->getTimestamp()))
        ->toBeLessThanOrEqual(1);

    Carbon::setTestNow(now()->addMinutes(16));
    try {
        $grant->refresh();
        app(OAuthGrantPolicy::class)->assertExchange($grant, $owner, OAuthResource::mcp());
        expect($token->fresh()->expires_at->isPast())->toBeTrue()
            ->and($grant->expires_at->isFuture())->toBeTrue();

        $rotatedAccessTokenId = Str::random(80);
        $rotatedToken = Token::query()->forceCreate([
            'id' => $rotatedAccessTokenId,
            'user_id' => $owner->getKey(),
            'client_id' => $client->getKey(),
            'scopes' => ['mcp:use'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(15),
        ]);
        $rotatedRefreshExpiresAt = now()->addDays(30);
        $rotatedRefreshTokenId = Str::random(80);
        RefreshToken::query()->forceCreate([
            'id' => $rotatedRefreshTokenId,
            'access_token_id' => $rotatedToken->getKey(),
            'revoked' => false,
            'expires_at' => $rotatedRefreshExpiresAt,
        ]);

        $writer->rotateAccessTokenFromRefreshToken($refreshTokenId, $rotatedAccessTokenId);
        $writer->rotateRefreshToken($refreshTokenId, $rotatedRefreshTokenId, $rotatedRefreshExpiresAt);

        expect($grant->fresh()->passport_token_id)->toBe($rotatedAccessTokenId)
            ->and($grant->fresh()->passport_refresh_token_id)->toBe($rotatedRefreshTokenId)
            ->and(abs($grant->fresh()->expires_at->getTimestamp() - $rotatedRefreshExpiresAt->getTimestamp()))
            ->toBeLessThanOrEqual(1);
    } finally {
        Carbon::setTestNow();
    }
});

/** @return array{0:User, 1:Tenant, 2:Unit, 3:OAuthGrant, 4:Token} */
function oauthGrantFixture(): array
{
    [$owner, $tenant, $unit] = oauthGrantWorkspace();
    [$grant, $token] = oauthGrantFor($owner, $tenant, $unit);

    return [$owner, $tenant, $unit, $grant, $token];
}

/** @return array{0:User, 1:Tenant, 2:Unit} */
function oauthGrantWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'OAuth Grant '.Str::random(8),
        'slug' => 'oauth-grant-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);

    return [$owner, $tenant, $unit];
}

/** @return array{0:OAuthGrant, 1:Token} */
function oauthGrantFor(User $user, Tenant $tenant, Unit $unit): array
{
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'MCP grant fixture',
        ['https://client.example.test/callback'],
        false,
    );
    $token = Token::query()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => $client->getKey(),
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addMinutes(15),
    ]);
    $grant = OAuthGrant::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'client_id' => $client->getKey(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'resource' => OAuthResource::mcp(),
        'capabilities' => ['context:read', 'catalog:read', 'setup:read', 'operations:propose'],
        'expires_at' => now()->addMinutes(15),
    ]);

    return [$grant, $token];
}

/** @param list<string> $scopes */
function oauthGrantRequest(User $user, string $resource, array $scopes = ['mcp:use']): Request
{
    $grant = OAuthGrant::query()->where('user_id', $user->getKey())->latest()->firstOrFail();
    $user->withAccessToken(new AccessToken([
        'oauth_access_token_id' => $grant->passport_token_id,
        'oauth_scopes' => $scopes,
    ]));
    $request = Request::create('/mcp', 'POST', ['resource' => $resource]);
    $request->setUserResolver(fn (): User => $user);

    return $request;
}

function oauthAuthorizationPassportKeyDirectory(): string
{
    static $directory;

    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/oauth-authorization-passport-'.Str::uuid();
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
