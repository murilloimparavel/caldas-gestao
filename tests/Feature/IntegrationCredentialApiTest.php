<?php

use App\Actions\Identity\OnboardTenant;
use App\Enums\MembershipStatus;
use App\Enums\TenantStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\ProposedOperation;
use App\Models\Integrations\StepUpProof;
use App\Models\Membership;
use App\Models\MembershipUnit;
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
    Passport::loadKeysFrom(integrationPassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('Integration API feature tests', 'users');
});

afterAll(function (): void {
    $directory = integrationPassportKeyDirectory();

    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function integrationPassportKeyDirectory(): string
{
    static $directory;

    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/passport-feature-'.Str::uuid();
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
function integrationApiWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Integration API '.Str::random(8),
        'slug' => 'integration-api-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);

    return [$owner, $tenant, $unit];
}

function integrationStepUpSession(User $user, Tenant $tenant, Unit $unit, string $purpose): array
{
    $passkey = Passkey::query()->forceCreate([
        'user_id' => $user->getKey(),
        'name' => 'Test key',
        'credential_id' => 'test-'.Str::uuid(),
        'credential' => ['credentialId' => 'test'],
    ]);
    $proof = StepUpProof::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'passkey_id' => $passkey->getKey(),
        'factor' => 'passkey',
        'purpose' => $purpose,
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(5),
    ]);

    return [
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'integrations.step_up.proof_id' => $proof->getKey(),
    ];
}

function issueIntegrationApiToken(User $owner, Tenant $tenant, Unit $unit, string $label = 'Test assistant', string $capabilitySet = 'context:read'): array
{
    $response = test()->actingAs($owner)
        ->withSession(integrationStepUpSession($owner, $tenant, $unit, 'credentials.issue'))
        ->post(route('integration-credentials.store', ['purpose' => 'credentials.issue']), [
            'label' => $label,
            'capability_set' => $capabilitySet,
        ]);

    $response->assertCreated();

    return [$response->json('secret'), $response->json('data.id')];
}

it('issues a one-time read-only secret and lists metadata without the bearer secret', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();

    $response = $this->actingAs($owner)
        ->withSession(integrationStepUpSession($owner, $tenant, $unit, 'credentials.issue'))
        ->post(route('integration-credentials.store', ['purpose' => 'credentials.issue']), ['label' => 'ChatGPT MCP']);

    $response->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.label', 'ChatGPT MCP')
        ->assertJsonPath('data.capabilities', ['context:read'])
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['secret', 'data' => ['id', 'created_at', 'expires_at', 'last_used_at']]);

    $secret = $response->json('secret');
    $credential = IntegrationCredential::query()->findOrFail($response->json('data.id'));

    expect($secret)->toBeString()
        ->and($credential->passportToken->scopes)->toBe(['context:read'])
        ->and($credential->passportToken->user_id)->toBe($owner->getKey())
        ->and($credential->tenant_id)->toBe($tenant->getKey())
        ->and($credential->unit_id)->toBe($unit->getKey())
        ->and(abs($credential->expires_at->diffInDays(now())))->toBeBetween(29, 30)
        ->and(json_encode($credential->getAttributes()))->not->toContain($secret);

    $this->actingAs($owner)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->get(route('integration-credentials.index'))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache')
        ->assertJsonPath('data.0.label', 'ChatGPT MCP')
        ->assertJsonMissingPath('data.0.secret');
});

it('issues an operations proposal credential with an exact matching Passport scope under step-up', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();

    $response = $this->actingAs($owner)
        ->withSession(integrationStepUpSession($owner, $tenant, $unit, 'credentials.issue'))
        ->postJson(route('integration-credentials.store', ['purpose' => 'credentials.issue']), [
            'label' => 'Service setup assistant',
            'capability_set' => 'operations:propose',
        ]);

    $response->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.capabilities', ['operations:propose'])
        ->assertJsonStructure(['secret', 'data' => ['id']]);

    $credential = IntegrationCredential::query()->findOrFail($response->json('data.id'));

    expect($credential->capabilities)->toBe(['operations:propose'])
        ->and($credential->passportToken->scopes)->toBe(['operations:propose']);
});

it('issues one combined read-only credential without granting write proposals', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();

    [$secret, $credentialId] = issueIntegrationApiToken($owner, $tenant, $unit, 'Catalog and setup reader', 'catalog:read+setup:read');

    $credential = IntegrationCredential::query()->findOrFail($credentialId);
    expect($credential->capabilities)->toBe(['catalog:read', 'setup:read'])
        ->and($credential->passportToken->scopes)->toBe(['catalog:read', 'setup:read']);

    $this->withToken($secret)->getJson('/api/v1/services')->assertSuccessful();
    $this->withToken($secret)->getJson('/api/v1/setup/status')->assertSuccessful();
    $this->withToken($secret)->postJson('/api/v1/operations', [
        'operation' => 'service.create',
        'input' => [
            'name' => 'Proposta bloqueada',
            'duration_minutes' => 30,
            'price_cents' => 4000,
        ],
    ])->assertForbidden();
});

it('rejects wildcard, combined, duplicate, and unknown capability sets', function (mixed $capabilitySet): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();

    $this->actingAs($owner)
        ->withSession(integrationStepUpSession($owner, $tenant, $unit, 'credentials.issue'))
        ->postJson(route('integration-credentials.store', ['purpose' => 'credentials.issue']), [
            'label' => 'Invalid integration credential',
            'capability_set' => $capabilitySet,
        ])
        ->assertUnprocessable();

    expect(IntegrationCredential::query()->count())->toBe(0);
})
    ->with([
        'wildcard' => '*',
        'combined string' => 'context:read operations:propose',
        'combined list' => [['context:read', 'operations:propose']],
        'duplicate list' => [['context:read', 'context:read']],
        'unknown capability' => 'customers:read',
    ]);

it('denies collaborators even when they have an active membership', function (): void {
    [, $tenant, $unit] = integrationApiWorkspace();
    $collaborator = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $collaborator->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create();

    $this->actingAs($collaborator)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->post(route('integration-credentials.store', ['purpose' => 'credentials.issue']), ['label' => 'Blocked'])
        ->assertForbidden();
});

it('exposes integration passkey readiness only to eligible owners', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();
    $collaborator = User::factory()->create(['email_verified_at' => now()]);
    $collaboratorMembership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $collaborator->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    MembershipUnit::factory()->forMembership($collaboratorMembership)->forUnit($unit)->create();

    $this->actingAs($owner)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(request()))
        ->get(route('settings.api'))
        ->assertSuccessful()
        ->assertJsonPath('props.canManageIntegrations', true)
        ->assertJsonPath('props.hasIntegrationPasskey', false)
        ->assertJsonPath('props.integrationCredentials', []);

    Passkey::query()->forceCreate([
        'user_id' => $owner->getKey(),
        'name' => 'Test key',
        'credential_id' => 'test-'.Str::uuid(),
        'credential' => ['credentialId' => 'test'],
    ]);

    $this->actingAs($owner)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(request()))
        ->get(route('settings.api'))
        ->assertSuccessful()
        ->assertJsonPath('props.hasIntegrationPasskey', true);

    $this->actingAs($collaborator)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(request()))
        ->get(route('settings.api'))
        ->assertForbidden();
});

it('rejects tenant and unit overrides in the issue payload', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();
    $otherOwner = User::factory()->create();
    $otherTenant = (new OnboardTenant)->handle($otherOwner, [
        'name' => 'Other '.Str::random(8),
        'slug' => 'other-'.Str::lower(Str::random(8)),
    ]);
    $otherUnit = $otherTenant->units()->firstOrFail();

    $this->actingAs($owner)
        ->withSession(integrationStepUpSession($owner, $tenant, $unit, 'credentials.issue'))
        ->postJson(route('integration-credentials.store', ['purpose' => 'credentials.issue']), [
            'label' => 'Tampered',
            'tenant_id' => $otherTenant->getKey(),
            'unit_id' => $otherUnit->getKey(),
        ])
        ->assertUnprocessable();

    expect(IntegrationCredential::query()->count())->toBe(0);
});

it('revokes a credential and blocks its bearer token while retaining lifecycle metadata', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();
    [$secret, $credentialId] = issueIntegrationApiToken($owner, $tenant, $unit);

    $credential = IntegrationCredential::query()->findOrFail($credentialId);
    $this->actingAs($owner)
        ->withSession(integrationStepUpSession($owner, $tenant, $unit, 'credentials.revoke'))
        ->delete(route('integration-credentials.destroy', [
            'purpose' => 'credentials.revoke',
            'credential' => $credentialId,
        ]))
        ->assertNoContent();

    expect($credential->passportToken->fresh()->revoked)->toBeTrue()
        ->and($credential->fresh()->revoked_at)->not->toBeNull();

    $this->withToken($secret)->getJson(route('api.v1.context'))->assertUnauthorized();

    $this->actingAs($owner)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->get(route('integration-credentials.index'))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache')
        ->assertJsonPath('data.0.status', 'revoked')
        ->assertJsonMissingPath('data.0.secret');
});

it('returns only boolean context linkage and updates the usage timestamp for a valid bearer', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();
    [$secret, $credentialId] = issueIntegrationApiToken($owner, $tenant, $unit);

    $apiResponse = $this->withToken($secret)->getJson(route('api.v1.context'));
    $apiResponse
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'data' => [
                'actor' => ['authenticated' => true],
                'tenant' => ['bound' => true],
                'unit' => ['bound' => true],
                'capabilities' => ['context:read'],
            ],
        ]);

    expect(IntegrationCredential::query()->findOrFail($credentialId)->last_used_at)->not->toBeNull();
});

it('exposes the versioned capability and operation schemas to an authorized integration', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();
    [$secret] = issueIntegrationApiToken($owner, $tenant, $unit);

    $this->getJson(route('api.v1.capabilities'))->assertUnauthorized();

    $response = $this->withToken($secret)
        ->getJson(route('api.v1.capabilities'))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.capabilities.0.id', 'context:read')
        ->assertJsonPath('data.capabilities.1.id', 'operations:propose')
        ->assertJsonPath('data.capabilities.1.confirmation_required', true);

    $operations = collect($response->json('data.operations'))->keyBy('operation');
    expect($operations->keys()->sort()->values()->all())->toBe([
        'category.create',
        'category.update',
        'professional.create',
        'professional.update',
        'service.create',
        'service.update',
        'unit.update',
    ])
        ->and($operations->get('category.create')['description'])->toContain('category')
        ->and($operations->get('category.create')['capability'])->toBe('operations:propose')
        ->and($operations->get('category.create')['input_schema']['additionalProperties'])->toBeFalse()
        ->and($operations->get('category.create')['input_schema']['properties']['type']['enum'])->toBe(['service', 'product', 'general'])
        ->and($operations->get('category.create')['input_schema']['properties']['is_active']['default'])->toBeTrue()
        ->and($operations->get('category.update')['input_schema']['required'])->toBe([
            'category_name',
        ])
        ->and($operations->get('category.update')['input_schema']['properties'])->not->toHaveKey('expected_version')
        ->and($operations->get('service.create')['capability'])->toBe('operations:propose')
        ->and($operations->get('service.create')['description'])->toContain('service')
        ->and($operations->get('service.create')['input_schema']['additionalProperties'])->toBeFalse()
        ->and($operations->get('service.create')['input_schema']['properties']['professional_names']['items']['type'])->toBe('string')
        ->and($operations->get('service.update')['input_schema']['required'])->toBe([
            'service_name',
        ])
        ->and($operations->get('service.update')['input_schema']['properties'])->not->toHaveKey('expected_version');

    expect($operations->get('professional.create')['input_schema']['required'])->toBe(['name', 'status'])
        ->and($operations->get('professional.update')['input_schema']['required'])->toBe(['professional_name'])
        ->and($operations->get('professional.update')['input_schema']['minProperties'])->toBe(2)
        ->and($operations->get('category.create')['input_schema']['properties'])->not->toHaveKey('description')
        ->and($operations->get('service.create')['input_schema']['properties'])->not->toHaveKey('description')
        ->and($operations->get('service.update')['input_schema']['properties'])->not->toHaveKey('description')
        ->and($operations->get('unit.update')['input_schema']['properties'])->not->toHaveKey('address');
});

it('rejects unapproved external proposal operations and free text or address fields server-side', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();
    [$secret] = issueIntegrationApiToken($owner, $tenant, $unit, 'Proposal API', 'operations:propose');

    foreach (['availability_rule.create', 'schedule_block.create', 'booking.settings.update'] as $index => $operation) {
        $this->withToken($secret)
            ->withHeader('X-Idempotency-Key', 'unsupported-operation-'.$index)
            ->postJson('/api/v1/operations', ['operation' => $operation, 'input' => []])
            ->assertForbidden();
    }

    foreach ([
        ['category.create', ['name' => 'Serviços', 'type' => 'service', 'description' => 'Texto livre']],
        ['service.create', ['name' => 'Corte', 'duration_minutes' => 30, 'price_cents' => 4500, 'description' => 'Texto livre']],
        ['unit.update', ['name' => 'Unidade', 'timezone' => 'America/Sao_Paulo', 'address' => ['city' => 'Cidade'], 'online_booking_enabled' => true, 'appointment_sales_automation_enabled' => true]],
    ] as $index => [$operation, $input]) {
        $this->withToken($secret)
            ->withHeader('X-Idempotency-Key', 'forbidden-field-'.$index)
            ->postJson('/api/v1/operations', ['operation' => $operation, 'input' => $input])
            ->assertUnprocessable();
    }

    expect(ProposedOperation::query()->count())->toBe(0);
});

it('rejects expired credentials but keeps their inactive metadata visible', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();
    [$secret, $credentialId] = issueIntegrationApiToken($owner, $tenant, $unit);
    $credential = IntegrationCredential::query()->findOrFail($credentialId);
    $credential->forceFill(['expires_at' => now()->subMinute()])->save();
    $credential->passportToken->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->withToken($secret)->getJson(route('api.v1.context'))->assertUnauthorized();

    $this->actingAs($owner)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->get(route('integration-credentials.index'))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache')
        ->assertJsonPath('data.0.status', 'expired');
});

it('revalidates membership eligibility for every bearer request', function (): void {
    [$owner, $tenant, $unit] = integrationApiWorkspace();
    [$secret] = issueIntegrationApiToken($owner, $tenant, $unit);
    $membership = Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $owner->getKey())->firstOrFail();
    $membership->forceFill(['status' => MembershipStatus::Revoked, 'revoked_at' => now()])->save();

    $this->withToken($secret)->getJson(route('api.v1.context'))->assertForbidden();
});

it('revalidates tenant eligibility for every bearer request', function (): void {
    [$tenantOwner, $inactiveTenant, $inactiveUnit] = integrationApiWorkspace();
    [$tenantSecret] = issueIntegrationApiToken($tenantOwner, $inactiveTenant, $inactiveUnit);
    $inactiveTenant->forceFill(['status' => TenantStatus::Suspended])->save();

    $this->withToken($tenantSecret)->getJson(route('api.v1.context'))->assertForbidden();
});

it('revalidates SaaS eligibility for every bearer request', function (): void {
    [$subscriptionOwner, $subscriptionTenant, $subscriptionUnit] = integrationApiWorkspace();
    [$subscriptionSecret] = issueIntegrationApiToken($subscriptionOwner, $subscriptionTenant, $subscriptionUnit);
    TenantSubscription::query()
        ->where('tenant_id', $subscriptionTenant->getKey())
        ->latest()
        ->firstOrFail()
        ->forceFill(['status' => 'cancelled'])
        ->save();

    $this->withToken($subscriptionSecret)->getJson(route('api.v1.context'))->assertForbidden();
});
