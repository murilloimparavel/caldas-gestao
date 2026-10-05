<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Identity\OnboardTenant;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\OAuthGrant;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\AuthCode;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

it('revokes the changed user integration credential and OAuth grant while preserving history and isolation', function (): void {
    [$owner, $ownerAccess, $ownerCredential, $ownerGrant, $ownerRefresh, $ownerAuthCode] = passwordRevocationFixture();
    [$other, $otherAccess, $otherCredential, $otherGrant, $otherRefresh, $otherAuthCode] = passwordRevocationFixture();

    test()->actingAs($owner)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasNoErrors();

    expect($ownerCredential->fresh()->revoked_at)->not->toBeNull()
        ->and($ownerAccess->fresh()->revoked)->toBeTrue()
        ->and($ownerGrant->fresh()->revoked_at)->not->toBeNull()
        ->and($ownerRefresh->fresh()->revoked)->toBeTrue()
        ->and($ownerAuthCode->fresh()->revoked)->toBeTrue()
        ->and(IntegrationCredential::query()->whereKey($ownerCredential->getKey())->exists())->toBeTrue()
        ->and(OAuthGrant::query()->whereKey($ownerGrant->getKey())->exists())->toBeTrue()
        ->and($otherCredential->fresh()->revoked_at)->toBeNull()
        ->and($otherAccess->fresh()->revoked)->toBeFalse()
        ->and($otherGrant->fresh()->revoked_at)->toBeNull()
        ->and($otherRefresh->fresh()->revoked)->toBeFalse()
        ->and($otherAuthCode->fresh()->revoked)->toBeFalse()
        ->and($other->fresh()->password)->toBe($other->password);
});

it('revokes integration access when Fortify resets the password', function (): void {
    [$owner, $ownerAccess, $ownerCredential, $ownerGrant, $ownerRefresh, $ownerAuthCode] = passwordRevocationFixture();

    app(ResetUserPassword::class)->reset($owner, [
        'password' => 'reset-password-123',
        'password_confirmation' => 'reset-password-123',
    ]);

    expect($ownerCredential->fresh()->revoked_at)->not->toBeNull()
        ->and($ownerAccess->fresh()->revoked)->toBeTrue()
        ->and($ownerGrant->fresh()->revoked_at)->not->toBeNull()
        ->and($ownerRefresh->fresh()->revoked)->toBeTrue()
        ->and($ownerAuthCode->fresh()->revoked)->toBeTrue();
});

/** @return array{0:User, 1:Token, 2:IntegrationCredential, 3:OAuthGrant, 4:RefreshToken, 5:AuthCode} */
function passwordRevocationFixture(): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($user, [
        'name' => 'Password revoke '.Str::random(8),
        'slug' => 'password-revoke-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Password revocation test client',
        ['https://client.example.test/callback'],
        false,
    );
    $access = Token::query()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => $client->getKey(),
        'scopes' => ['context:read'],
        'revoked' => false,
        'expires_at' => now()->addMinutes(15),
    ]);
    $credential = IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $access->getKey(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Password revoke credential',
        'capabilities' => ['context:read'],
        'expires_at' => $access->expires_at,
    ]);
    $refresh = RefreshToken::query()->forceCreate([
        'id' => Str::random(80),
        'access_token_id' => $access->getKey(),
        'revoked' => false,
        'expires_at' => now()->addDays(30),
    ]);
    $authCode = AuthCode::query()->forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => $client->getKey(),
        'scopes' => json_encode(['mcp:use'], JSON_THROW_ON_ERROR),
        'revoked' => false,
        'expires_at' => now()->addMinutes(5),
    ]);
    $grant = OAuthGrant::query()->create([
        'id' => (string) Str::uuid(),
        'auth_code_id' => $authCode->getKey(),
        'passport_token_id' => $access->getKey(),
        'passport_refresh_token_id' => $refresh->getKey(),
        'client_id' => $client->getKey(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'resource' => 'https://mcp.example.test',
        'capabilities' => ['context:read'],
        'expires_at' => now()->addDays(30),
    ]);

    return [$user, $access, $credential, $grant, $refresh, $authCode];
}
