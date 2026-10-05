<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    Passport::loadKeysFrom(integrationCapabilitySetPassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('Integration capability set tests', 'users');
});

afterAll(function (): void {
    $directory = integrationCapabilitySetPassportKeyDirectory();

    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function integrationCapabilitySetPassportKeyDirectory(): string
{
    static $directory;

    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/integration-capability-set-'.Str::uuid();
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

/** @return array{User, Tenant, Unit} */
function integrationCapabilitySetWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Capability set '.Str::random(8),
        'slug' => 'capability-set-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);

    return [$owner, $tenant, $unit];
}

/** @param list<string> $tokenScopes
 * @param  list<string>  $credentialCapabilities
 */
function integrationCapabilitySetBearer(User $user, Tenant $tenant, Unit $unit, array $tokenScopes, array $credentialCapabilities): string
{
    $issuedToken = $user->createToken('Capability set test', $tokenScopes);
    $token = $issuedToken->getToken();

    IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Capability set test',
        'capabilities' => $credentialCapabilities,
        'expires_at' => $token->expires_at,
    ]);

    return $issuedToken->accessToken;
}

it('accepts the authorized three-capability set regardless of scope and credential order', function (array $tokenScopes, array $credentialCapabilities): void {
    [$owner, $tenant, $unit] = integrationCapabilitySetWorkspace();
    $bearer = integrationCapabilitySetBearer($owner, $tenant, $unit, $tokenScopes, $credentialCapabilities);

    $this->withToken($bearer)
        ->getJson('/api/v1/capabilities')
        ->assertSuccessful();
})->with([
    'token and credential use different orders' => [
        ['context:read', 'catalog:read', 'setup:read'],
        ['setup:read', 'context:read', 'catalog:read'],
    ],
    'reverse order is accepted' => [
        ['setup:read', 'catalog:read', 'context:read'],
        ['catalog:read', 'context:read', 'setup:read'],
    ],
]);

it('rejects the authorized three-capability set when an extra capability is present', function (): void {
    [$owner, $tenant, $unit] = integrationCapabilitySetWorkspace();
    $capabilities = ['context:read', 'catalog:read', 'setup:read', 'operations:propose'];
    $bearer = integrationCapabilitySetBearer($owner, $tenant, $unit, $capabilities, $capabilities);

    $this->withToken($bearer)
        ->getJson('/api/v1/capabilities')
        ->assertUnauthorized();
});
