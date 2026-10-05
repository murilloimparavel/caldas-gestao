<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\ProposedOperation;
use App\Models\Integrations\StepUpProof;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passkeys\Passkey;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    Passport::loadKeysFrom(unitUpdatePassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('Unit update tests', 'users');
});

afterAll(function (): void {
    $directory = unitUpdatePassportKeyDirectory();
    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function unitUpdatePassportKeyDirectory(): string
{
    static $directory;
    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/unit-update-passport-'.Str::uuid();
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
function unitUpdateWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Unit update '.Str::random(8),
        'slug' => 'unit-update-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);

    return [$owner, $tenant, $unit];
}

/** @return array{string, IntegrationCredential} */
function unitUpdateToken(User $owner, Tenant $tenant, Unit $unit): array
{
    $issued = $owner->createToken('Unit update test', ['operations:propose']);
    $token = $issued->getToken();
    $credential = IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Unit update test',
        'capabilities' => ['operations:propose'],
        'expires_at' => $token->expires_at,
    ]);

    return [$issued->accessToken, $credential];
}

/** @return array<string, string> */
function unitUpdateStepUpSession(User $user, Tenant $tenant, Unit $unit, ProposedOperation $proposal): array
{
    $passkey = Passkey::query()->forceCreate([
        'user_id' => $user->getKey(),
        'name' => 'Unit settings key',
        'credential_id' => 'unit-update-'.Str::uuid(),
        'credential' => ['credentialId' => 'unit-update-test'],
    ]);
    $proof = StepUpProof::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'passkey_id' => $passkey->getKey(),
        'factor' => 'passkey',
        'purpose' => 'operations.confirm',
        'target_id' => (string) $proposal->getKey(),
        'command_hash' => hash('sha256', $proposal->operation_key."\0".$proposal->input_hash),
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(5),
    ]);

    return [
        'tenant_id' => (string) $tenant->getKey(),
        'unit_id' => (string) $unit->getKey(),
        'integrations.step_up.proof_id' => (string) $proof->getKey(),
    ];
}

/** @return array{operation: string, input: array<string, mixed>} */
function unitUpdatePayload(Unit $unit, array $overrides = []): array
{
    return [
        'operation' => 'unit.update',
        'input' => $overrides + [
            'name' => 'Nome da unidade atualizado',
            'timezone' => 'America/Sao_Paulo',
            'online_booking_enabled' => true,
            'appointment_sales_automation_enabled' => true,
        ],
    ];
}

it('proposes safe unit settings without exposing current unit values through the external API and confirms with a passkey proof', function (): void {
    [$owner, $tenant, $unit] = unitUpdateWorkspace();
    [$secret] = unitUpdateToken($owner, $tenant, $unit);
    $unit->forceFill([
        'name' => 'Nome atual privado',
        'address' => ['street' => 'Endereço atual privado', 'city' => 'Cidade privada'],
        'timezone' => 'UTC',
        'online_booking_enabled' => false,
        'appointment_sales_automation_enabled' => false,
        'lock_version' => 3,
    ])->save();

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'unit-update-settings')
        ->postJson('/api/v1/operations', unitUpdatePayload($unit))
        ->assertStatus(202)
        ->assertJsonPath('data.operation', 'unit.update')
        ->assertJsonPath('data.summary.changed_fields', [
            'name',
            'timezone',
            'online_booking_enabled',
            'appointment_sales_automation_enabled',
        ])
        ->assertJsonMissingPath('data.summary.name')
        ->assertJsonMissingPath('data.summary.address')
        ->assertJsonMissingPath('data.summary.expected_version')
        ->assertDontSee('Nome da unidade atualizado')
        ->assertDontSee('Nome atual privado')
        ->assertDontSee('Endereço atual privado');
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));

    $this->withToken($secret)
        ->getJson('/api/v1/operations/'.(string) $proposal->getKey())
        ->assertOk()
        ->assertDontSee('Nome atual privado')
        ->assertDontSee('Endereço atual privado')
        ->assertJsonMissingPath('data.currentUnit');

    $this->actingAs($owner)
        ->withSession(unitUpdateStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertRedirect(route('integration-proposals.show', $proposal));

    $unit->refresh();
    $proposal->refresh();
    expect($proposal->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($proposal->result_reference['resource_type'])->toBe('unit')
        ->and($unit->name)->toBe('Nome da unidade atualizado')
        ->and($unit->timezone)->toBe('America/Sao_Paulo')
        ->and($unit->address)->toEqualCanonicalizing(['street' => 'Endereço atual privado', 'city' => 'Cidade privada'])
        ->and($unit->online_booking_enabled)->toBeTrue()
        ->and($unit->appointment_sales_automation_enabled)->toBeTrue()
        ->and($unit->lock_version)->toBe(4);
});

it('rejects a unit settings proposal with a stale version without modifying the unit', function (): void {
    [$owner, $tenant, $unit] = unitUpdateWorkspace();
    [$secret] = unitUpdateToken($owner, $tenant, $unit);
    $unit->forceFill(['lock_version' => 2])->save();
    $payload = unitUpdatePayload($unit);

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'unit-update-stale-version')
        ->postJson('/api/v1/operations', $payload)
        ->assertStatus(202);
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));
    $unit->forceFill(['lock_version' => 3])->save();

    $this->actingAs($owner)
        ->withSession(unitUpdateStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertConflict();

    expect($proposal->fresh()->status)->toBe(ProposedOperation::STATUS_NEEDS_REFRESH)
        ->and($unit->fresh()->lock_version)->toBe(3)
        ->and($unit->fresh()->name)->not->toBe('Nome da unidade atualizado');
});

it('patches only requested unit settings while preserving omitted fields', function (): void {
    [$owner, $tenant, $unit] = unitUpdateWorkspace();
    [$secret] = unitUpdateToken($owner, $tenant, $unit);
    $unit->forceFill([
        'name' => 'Unidade preservada',
        'timezone' => 'UTC',
        'online_booking_enabled' => false,
        'appointment_sales_automation_enabled' => true,
    ])->save();

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'unit-update-online-booking-only')
        ->postJson('/api/v1/operations', [
            'operation' => 'unit.update',
            'input' => ['online_booking_enabled' => true],
        ])
        ->assertAccepted()
        ->assertJsonPath('data.summary.changed_fields', ['online_booking_enabled'])
        ->assertJsonMissingPath('data.summary.expected_version');
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));

    $this->actingAs($owner)
        ->withSession(unitUpdateStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertRedirect(route('integration-proposals.show', $proposal));

    expect($proposal->fresh()->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($unit->fresh()->name)->toBe('Unidade preservada')
        ->and($unit->fresh()->timezone)->toBe('UTC')
        ->and($unit->fresh()->online_booking_enabled)->toBeTrue()
        ->and($unit->fresh()->appointment_sales_automation_enabled)->toBeTrue();
});

it('rejects non-editable unit fields and address changes', function (string $field): void {
    [$owner, $tenant, $unit] = unitUpdateWorkspace();
    [$secret] = unitUpdateToken($owner, $tenant, $unit);
    $input = unitUpdatePayload($unit)['input'];
    if ($field === 'address_key') {
        $input['address']['internal_note'] = 'Do not accept';
    } elseif ($field === 'address') {
        $input['address'] = ['city' => 'Cidade nova'];
    } else {
        $input[$field] = 'forbidden';
    }

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'unit-update-forbidden-'.Str::random(8))
        ->postJson('/api/v1/operations', ['operation' => 'unit.update', 'input' => $input])
        ->assertUnprocessable();
})->with(['status', 'slug', 'appointment_default_sale_category_id', 'address', 'address_key', 'expected_version']);
