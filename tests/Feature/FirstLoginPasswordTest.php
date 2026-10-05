<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\OAuthGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\AuthCode;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

function firstLoginUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'must_change_password' => true,
        'temporary_password_expires_at' => now()->addHour(),
    ], $attributes));

    (new OnboardTenant)->handle($user, [
        'name' => 'First login '.Str::random(8),
        'slug' => 'first-login-'.Str::lower(Str::random(8)),
    ]);

    return $user;
}

it('redirects a first-time user to the password setup screen', function (): void {
    $user = firstLoginUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('first-login-password.edit', absolute: false));
});

it('renders the password setup screen for a first-time user', function (): void {
    $user = firstLoginUser();

    $this->actingAs($user)
        ->get(route('first-login-password.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/first-login-password')
            ->has('passwordRules')
        );
});

it('updates the password and completes the first login state', function (): void {
    $user = firstLoginUser();
    $user->forceFill(['remember_token' => 'old-token'])->saveQuietly();
    $rememberToken = $user->remember_token;
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert([
        'id' => 'first-login-password-session',
        'user_id' => $user->getKey(),
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    $response = $this->actingAs($user)->put(route('first-login-password.update'), [
        'password' => 'New-password-123!',
        'password_confirmation' => 'New-password-123!',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and($user->temporary_password_expires_at)->toBeNull()
        ->and($user->first_login_at)->not->toBeNull();
    expect(Hash::check('New-password-123!', $user->password))->toBeTrue();
    expect($user->remember_token)->not->toBe($rememberToken)
        ->and(DB::table('sessions')->where('id', 'first-login-password-session')->exists())->toBeFalse();
});

it('revokes integration access when a first-time user sets a password', function (): void {
    $user = firstLoginUser();
    $membership = $user->memberships()->firstOrFail();
    $tenant = $membership->tenant()->firstOrFail();
    $unit = $tenant->units()->firstOrFail();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'First login password test client',
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
        'label' => 'First login password credential',
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

    $this->actingAs($user)->put(route('first-login-password.update'), [
        'password' => 'New-password-123!',
        'password_confirmation' => 'New-password-123!',
    ])->assertRedirect(route('dashboard', absolute: false));

    expect($credential->fresh()->revoked_at)->not->toBeNull()
        ->and($access->fresh()->revoked)->toBeTrue()
        ->and($grant->fresh()->revoked_at)->not->toBeNull()
        ->and($refresh->fresh()->revoked)->toBeTrue()
        ->and($authCode->fresh()->revoked)->toBeTrue()
        ->and(IntegrationCredential::query()->whereKey($credential->getKey())->exists())->toBeTrue()
        ->and(OAuthGrant::query()->whereKey($grant->getKey())->exists())->toBeTrue();
});

it('blocks access when the temporary password has expired', function (): void {
    $user = firstLoginUser([
        'temporary_password_expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});
