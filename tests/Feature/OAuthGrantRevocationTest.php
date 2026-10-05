<?php

use App\Actions\Identity\OnboardTenant;
use App\Http\Middleware\RequireOAuthGrant;
use App\Models\Integrations\OAuthGrant;
use App\Models\Integrations\StepUpProof;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use App\Policies\OAuthGrantPolicy;
use App\Support\Integrations\OAuthResource;
use App\Support\Integrations\StepUpSession;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Passkey;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    config([
        'integration.oauth.enabled' => true,
        'integration.oauth.resource' => 'https://mcp.example.test',
    ]);
});

it('lets the owning admin revoke one OAuth grant with a purpose and target bound Passkey proof', function (): void {
    [$owner, $tenant, $unit, $grant, $token, $refreshToken] = revocableOAuthFixture();
    $proofId = revocationProof($owner, $tenant->getKey(), $unit->getKey(), $grant);

    $this->actingAs($owner)
        ->withSession([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'integrations.step_up.proof_id' => $proofId,
        ])
        ->delete(route('integration-credentials.oauth-grants.destroy', [
            'purpose' => 'oauth.revoke',
            'grant' => $grant->getKey(),
        ]))
        ->assertNoContent();

    expect($grant->fresh()->revoked_at)->not->toBeNull()
        ->and($token->fresh()->revoked)->toBeTrue()
        ->and($refreshToken->fresh()->revoked)->toBeTrue()
        ->and(StepUpProof::query()->findOrFail($proofId)->consumed_at)->not->toBeNull();

    expect(fn () => app(RequireOAuthGrant::class)->handle(
        revocationMcpRequest($owner, $token),
        fn () => response()->noContent(),
    ))->toThrow(HttpException::class);

    expect(fn () => app(OAuthGrantPolicy::class)->assertExchange($grant->fresh(), $owner, OAuthResource::mcp()))
        ->toThrow(AuthorizationException::class);
});

it('does not let the owner use a Passkey proof for a grant in another tenant', function (): void {
    [$owner, $tenant, $unit, $grant] = revocableOAuthFixture();
    $otherTenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Other OAuth tenant '.Str::random(8),
        'slug' => 'other-oauth-'.Str::lower(Str::random(8)),
    ]);
    $otherUnit = $otherTenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $otherTenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);
    [$foreignGrant] = revocableOAuthFixture($owner, $otherTenant, $otherUnit);
    $proofId = revocationProof($owner, $tenant->getKey(), $unit->getKey(), $grant);

    $this->actingAs($owner)
        ->withSession([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'integrations.step_up.proof_id' => $proofId,
        ])
        ->deleteJson(route('integration-credentials.oauth-grants.destroy', [
            'purpose' => 'oauth.revoke',
            'grant' => $foreignGrant->getKey(),
        ]))
        ->assertUnprocessable();

    expect($foreignGrant->fresh()->revoked_at)->toBeNull()
        ->and($grant->fresh()->revoked_at)->toBeNull()
        ->and(StepUpProof::query()->findOrFail($proofId)->consumed_at)->toBeNull();
});

it('requires the oauth revoke proof purpose and consumes it once', function (): void {
    [$owner, $tenant, $unit, $grant] = revocableOAuthFixture();
    $proofId = revocationProof($owner, $tenant->getKey(), $unit->getKey(), $grant, 'credentials.revoke');

    $this->actingAs($owner)
        ->withSession([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'integrations.step_up.proof_id' => $proofId,
        ])
        ->delete(route('integration-credentials.oauth-grants.destroy', [
            'purpose' => 'oauth.revoke',
            'grant' => $grant->getKey(),
        ]))
        ->assertForbidden();

    expect($grant->fresh()->revoked_at)->toBeNull()
        ->and(StepUpProof::query()->findOrFail($proofId)->consumed_at)->toBeNull();
});

it('binds oauth revoke challenges and one-time proofs to the grant and tenant context', function (): void {
    [$owner, $tenant, $unit, $grant] = revocableOAuthFixture();
    $session = app('session')->driver();
    $session->put(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $request = Request::create('/integrations/step-up/oauth.revoke/options', 'GET', [
        'oauth_grant_id' => $grant->getKey(),
    ]);
    $request->setLaravelSession($session);
    $request->setUserResolver(fn (): User => $owner);
    $request->attributes->set(TenantContext::class, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()));
    $stepUp = app(StepUpSession::class);
    $options = app(GenerateVerificationOptions::class)($owner);
    $challengeId = $stepUp->issueChallenge($request, $options, 'oauth.revoke');

    $request->query->set('oauth_grant_id', (string) Str::uuid());
    expect(fn () => $stepUp->consumeChallenge($request, 'oauth.revoke', $challengeId))
        ->toThrow(ValidationException::class);

    $request->query->set('oauth_grant_id', $grant->getKey());
    $validChallengeId = $stepUp->issueChallenge($request, $options, 'oauth.revoke');
    $stepUp->consumeChallenge($request, 'oauth.revoke', $validChallengeId);
    $passkey = Passkey::query()->forceCreate([
        'user_id' => $owner->getKey(),
        'name' => 'OAuth revoke test key',
        'credential_id' => 'oauth-revoke-'.Str::uuid(),
        'credential' => ['credentialId' => 'oauth-revoke'],
    ]);
    $stepUp->grant($request, 'oauth.revoke', $passkey);
    expect($stepUp->consumeProof($request, 'oauth.revoke'))->toBeTrue()
        ->and($stepUp->consumeProof($request, 'oauth.revoke'))->toBeFalse();
});

/** @return array{0:User, 1:Tenant, 2:Unit, 3:OAuthGrant, 4:Token, 5:RefreshToken, 6:Client} */
function revocableOAuthFixture(?User $user = null, ?Tenant $tenant = null, ?Unit $unit = null): array
{
    $user ??= User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant ??= (new OnboardTenant)->handle($user, [
        'name' => 'Revocable OAuth '.Str::random(8),
        'slug' => 'revocable-oauth-'.Str::lower(Str::random(8)),
    ]);
    $unit ??= $tenant->units()->firstOrFail();

    if (! TenantSubscription::query()->where('tenant_id', $tenant->getKey())->exists()) {
        TenantSubscription::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'status' => 'active',
            'ends_at' => now()->addDays(30),
        ]);
    }

    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Revocation test client',
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
    $refreshToken = RefreshToken::query()->forceCreate([
        'id' => Str::random(80),
        'access_token_id' => $token->getKey(),
        'revoked' => false,
        'expires_at' => now()->addDays(30),
    ]);
    $grant = OAuthGrant::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'passport_refresh_token_id' => $refreshToken->getKey(),
        'client_id' => $client->getKey(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'resource' => OAuthResource::mcp(),
        'capabilities' => ['context:read', 'catalog:read'],
        'expires_at' => now()->addDays(30),
    ]);

    return [$user, $tenant, $unit, $grant, $token, $refreshToken, $client];
}

function revocationProof(User $user, string $tenantId, string $unitId, OAuthGrant $grant, string $purpose = 'oauth.revoke'): string
{
    $passkey = Passkey::query()->forceCreate([
        'user_id' => $user->getKey(),
        'name' => 'Revocation test key',
        'credential_id' => 'revoke-'.Str::uuid(),
        'credential' => ['credentialId' => 'revoke'],
    ]);
    $capabilities = $grant->capabilities;
    sort($capabilities, SORT_STRING);

    return (string) StepUpProof::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'passkey_id' => $passkey->getKey(),
        'factor' => 'passkey',
        'purpose' => $purpose,
        'target_id' => $purpose === 'oauth.revoke' ? $grant->getKey() : null,
        'command_hash' => $purpose === 'oauth.revoke'
            ? hash('sha256', $grant->client_id."\0".$grant->resource."\0".json_encode($capabilities, JSON_THROW_ON_ERROR))
            : null,
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(5),
    ])->getKey();
}

function revocationMcpRequest(User $user, Token $token): Request
{
    $user->withAccessToken(new AccessToken([
        'oauth_access_token_id' => $token->getKey(),
        'oauth_scopes' => ['mcp:use'],
    ]));
    $request = Request::create('/mcp', 'POST', ['resource' => OAuthResource::mcp()]);
    $request->setUserResolver(fn (): User => $user);

    return $request;
}
