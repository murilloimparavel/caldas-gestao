<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Category;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\ProposedOperation;
use App\Models\Integrations\StepUpProof;
use App\Models\MembershipUnit;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use App\Support\Integrations\ProposedOperationService;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Laravel\Passkeys\Passkey;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    Passport::loadKeysFrom(serviceUpdatePassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('Service update tests', 'users');
});

afterAll(function (): void {
    $directory = serviceUpdatePassportKeyDirectory();
    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function serviceUpdatePassportKeyDirectory(): string
{
    static $directory;
    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/service-update-passport-'.Str::uuid();
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
function serviceUpdateWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Service update '.Str::random(8),
        'slug' => 'service-update-'.Str::lower(Str::random(8)),
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
function serviceUpdateToken(User $owner, Tenant $tenant, Unit $unit): array
{
    $issued = $owner->createToken('Service update test', ['operations:propose']);
    $token = $issued->getToken();
    $credential = IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Service update test',
        'capabilities' => ['operations:propose'],
        'expires_at' => $token->expires_at,
    ]);

    return [$issued->accessToken, $credential];
}

/** @return array<string, string> */
function serviceUpdateStepUpSession(User $user, Tenant $tenant, Unit $unit, ProposedOperation $proposal): array
{
    $passkey = Passkey::query()->forceCreate([
        'user_id' => $user->getKey(),
        'name' => 'Service update key',
        'credential_id' => 'service-update-'.Str::uuid(),
        'credential' => ['credentialId' => 'service-update-test'],
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

/** @return array{operation: string, input: array{service_name: string, name: string, duration_minutes: int, price_cents: int, status: string}} */
function serviceUpdatePayload(Service $service, array $overrides = []): array
{
    return [
        'operation' => 'service.update',
        'input' => $overrides + [
            'service_name' => (string) $service->name,
            'name' => 'Nome proposto',
            'duration_minutes' => 45,
            'price_cents' => 12500,
            'status' => 'inactive',
        ],
    ];
}

it('proposes idempotent partial scalar updates without exposing current service values through the API', function (): void {
    [$owner, $tenant, $unit] = serviceUpdateWorkspace();
    [$secret] = serviceUpdateToken($owner, $tenant, $unit);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome atual privado',
        'description' => 'Descrição atual privada',
        'duration_minutes' => 90,
        'price_cents' => 9800,
        'status' => 'active',
        'lock_version' => 0,
    ]);
    $payload = serviceUpdatePayload($service);
    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'service-update-scalar')
        ->postJson('/api/v1/operations', $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.operation', 'service.update')
        ->assertJsonPath('data.summary.changed_fields', ['name', 'duration_minutes', 'price_cents', 'status'])
        ->assertJsonMissingPath('data.summary.service_id')
        ->assertJsonMissingPath('data.summary.expected_version')
        ->assertDontSee('Nome atual privado')
        ->assertDontSee('Descrição atual privada');
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'service-update-scalar')
        ->postJson('/api/v1/operations', $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.id', (string) $proposal->getKey());

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'service-update-scalar')
        ->postJson('/api/v1/operations', serviceUpdatePayload($service, ['name' => 'Outro nome']))
        ->assertConflict();

    $this->withToken($secret)
        ->getJson('/api/v1/operations/'.(string) $proposal->getKey())
        ->assertOk()
        ->assertJsonPath('data.summary.changed_fields', ['name', 'duration_minutes', 'price_cents', 'status'])
        ->assertDontSee('Nome atual privado')
        ->assertDontSee('Descrição atual privada')
        ->assertJsonMissingPath('data.currentService');

    expect($service->fresh()->name)->toBe('Nome atual privado')
        ->and($service->fresh()->lock_version)->toBe(0);
});

it('confirms the scalar service update once and preserves category and professional relations', function (): void {
    [$owner, $tenant, $unit] = serviceUpdateWorkspace();
    [$secret] = serviceUpdateToken($owner, $tenant, $unit);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
    ]);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $category->getKey(),
        'description' => 'Descrição atual privada',
        'lock_version' => 0,
    ]);
    $service->professionals()->attach($professional->getKey(), [
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'service-update-confirm-once')
        ->postJson('/api/v1/operations', serviceUpdatePayload($service))
        ->assertStatus(202);
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));

    $this->actingAs($owner)
        ->withSession(serviceUpdateStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertRedirect(route('integration-proposals.show', $proposal));

    $service->refresh();
    $proposal->refresh();
    expect($proposal->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($proposal->result_reference['resource_type'])->toBe('service')
        ->and($service->name)->toBe('Nome proposto')
        ->and($service->description)->toBe('Descrição atual privada')
        ->and($service->duration_minutes)->toBe(45)
        ->and($service->price_cents)->toBe(12500)
        ->and($service->status)->toBe('inactive')
        ->and($service->lock_version)->toBe(1)
        ->and($service->category_id)->toBe((string) $category->getKey())
        ->and($service->professionals()->pluck('professionals.id')->all())->toBe([(string) $professional->getKey()]);

    $this->actingAs($owner)
        ->withSession(serviceUpdateStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertConflict();

    expect($service->fresh()->lock_version)->toBe(1)
        ->and($service->professionals()->pluck('professionals.id')->all())->toBe([(string) $professional->getKey()]);
});

it('replaces service category and professional relations only when they are included in the proposal', function (): void {
    [$owner, $tenant, $unit] = serviceUpdateWorkspace();
    [$secret] = serviceUpdateToken($owner, $tenant, $unit);
    $oldCategory = Category::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'service']);
    $newCategory = Category::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'service']);
    $oldProfessional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $newProfessional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $oldCategory->getKey(),
        'lock_version' => 0,
    ]);
    $service->professionals()->attach($oldProfessional->getKey(), ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'service-update-relations')
        ->postJson('/api/v1/operations', serviceUpdatePayload($service, [
            'category_name' => (string) $newCategory->name,
            'professional_names' => [(string) $newProfessional->name],
        ]))
        ->assertAccepted()
        ->assertJsonPath('data.summary.changed_fields', ['name', 'duration_minutes', 'price_cents', 'status', 'category_name', 'professional_names']);
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));

    $this->actingAs($owner)
        ->withSession(serviceUpdateStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertRedirect(route('integration-proposals.show', $proposal));

    expect($proposal->fresh()->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($service->fresh()->category_id)->toBe((string) $newCategory->getKey())
        ->and($service->professionals()->pluck('professionals.id')->all())->toBe([(string) $newProfessional->getKey()])
        ->and($service->professionals()->whereKey($oldProfessional->getKey())->exists())->toBeFalse();
});

it('creates a professional through the confirmed operation and returns only safe setup metadata', function (): void {
    [$owner, $tenant, $unit] = serviceUpdateWorkspace();
    [$secret] = serviceUpdateToken($owner, $tenant, $unit);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $payload = [
        'operation' => 'professional.create',
        'input' => [
            'name' => 'Profissional inicial',
            'status' => 'active',
            'service_names' => [$service->name],
        ],
    ];

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'professional-create-confirmed')
        ->postJson('/api/v1/operations', $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.operation', 'professional.create')
        ->assertJsonPath('data.summary.changed_fields', ['name', 'status', 'service_names'])
        ->assertJsonMissingPath('data.summary.name')
        ->assertJsonMissingPath('data.summary.service_count')
        ->assertJsonMissingPath('data.summary.email')
        ->assertJsonMissingPath('data.summary.phone');
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));

    $this->actingAs($owner)
        ->withSession(serviceUpdateStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertRedirect(route('integration-proposals.show', $proposal));

    $professional = Professional::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->where('name', 'Profissional inicial')
        ->firstOrFail();
    expect($proposal->fresh()->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($proposal->fresh()->result_reference['resource_type'])->toBe('professional')
        ->and($professional->lock_version)->toBe(0)
        ->and($professional->services()->whereKey($service->getKey())->exists())->toBeTrue();
});

it('updates a professional and rejects a stale version without exposing private fields', function (): void {
    [$owner, $tenant, $unit] = serviceUpdateWorkspace();
    [$secret] = serviceUpdateToken($owner, $tenant, $unit);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome privado atual',
        'email' => 'private@example.test',
        'phone' => '+5511999999999',
        'lock_version' => 0,
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Serviço atual do profissional',
    ]);
    $professional->services()->attach($service->getKey(), [
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $payload = [
        'operation' => 'professional.update',
        'input' => [
            'professional_name' => $professional->name,
            'status' => 'inactive',
        ],
    ];

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'professional-update-confirmed')
        ->postJson('/api/v1/operations', $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.summary.changed_fields', ['status'])
        ->assertJsonMissingPath('data.summary.name')
        ->assertJsonMissingPath('data.summary.professional_id')
        ->assertJsonMissingPath('data.summary.expected_version')
        ->assertJsonMissingPath('data.summary.service_ids')
        ->assertDontSee('Nome privado atual')
        ->assertDontSee('private@example.test')
        ->assertJsonMissingPath('data.summary.email')
        ->assertJsonMissingPath('data.summary.phone');
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));
    expect($proposal->input['name'])->toBe('Nome privado atual')
        ->and($proposal->input['status'])->toBe('inactive')
        ->and($proposal->input['service_ids'])->toBe([(string) $service->getKey()])
        ->and($proposal->input['_provided_fields'])->toBe(['status']);
    $review = app(ProposedOperationService::class)->reviewDetails(
        $proposal,
        TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()),
    );
    expect($review['currentServices'][0]['name'])->toBe('Serviço atual do profissional')
        ->and($review['proposedServices'][0]['name'])->toBe('Serviço atual do profissional');
    $this->actingAs($owner)
        ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
        ->get(route('integration-proposals.show', $proposal))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('proposal.professionalServices.currentServices.0.name', 'Serviço atual do profissional')
            ->where('proposal.professionalServices.proposedServices.0.name', 'Serviço atual do profissional')
            ->where('proposal.operationReview.current.name', 'Nome privado atual')
            ->where('proposal.operationReview.current.status', 'active')
            ->where('proposal.operationReview.proposed.name', 'Nome privado atual')
            ->where('proposal.operationReview.proposed.status', 'inactive')
            ->where('proposal.operationReview.proposed.service_names', ['Serviço atual do profissional'])
            ->where('proposal.operationReview.proposed.changed_fields', ['status'])
            ->where('proposal.summary.current.name', 'Nome privado atual')
            ->where('proposal.summary.current.service_names', ['Serviço atual do profissional'])
            ->where('proposal.summary.proposed.name', 'Nome privado atual')
            ->where('proposal.summary.proposed.service_names', ['Serviço atual do profissional'])
            ->where('proposal.summary.changed_fields', ['status'])
            ->missing('proposal.changes._snapshot')
            ->missing('proposal.changes.professional_id')
            ->missing('proposal.changes.service_ids')
            ->missing('proposal.summary.professional_id')
            ->missing('proposal.summary.expected_version'));
    $professional->forceFill(['name' => 'Alterado depois', 'lock_version' => 1])->save();

    $this->actingAs($owner)
        ->withSession(serviceUpdateStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertConflict();

    expect($proposal->fresh()->status)->toBe(ProposedOperation::STATUS_NEEDS_REFRESH)
        ->and($professional->fresh()->name)->toBe('Alterado depois')
        ->and($professional->fresh()->email)->toBe('private@example.test');
});

it('rejects service update targets outside the credential tenant and unit', function (): void {
    [$owner, $tenant, $unit] = serviceUpdateWorkspace();
    [$secret] = serviceUpdateToken($owner, $tenant, $unit);
    $otherTenantService = Service::factory()->create();
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    MembershipUnit::factory()
        ->forMembership($owner->memberships()->where('tenant_id', $tenant->getKey())->firstOrFail())
        ->forUnit($otherUnit)
        ->create();
    $otherUnitService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
    ]);

    foreach ([$otherTenantService, $otherUnitService] as $index => $service) {
        $this->withToken($secret)
            ->withHeader('X-Idempotency-Key', 'service-out-of-scope-'.$index)
            ->postJson('/api/v1/operations', serviceUpdatePayload($service))
            ->assertUnprocessable();
    }

    expect(ProposedOperation::query()->where('operation_key', 'service.update')->count())->toBe(0);
});

it('fails closed when an exact service or relationship name does not resolve uniquely in the bound unit', function (): void {
    [$owner, $tenant, $unit] = serviceUpdateWorkspace();
    [$secret] = serviceUpdateToken($owner, $tenant, $unit);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome de serviço duplicado',
    ]);
    Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => $service->name,
    ]);

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'service-name-ambiguous')
        ->postJson('/api/v1/operations', [
            'operation' => 'service.update',
            'input' => ['service_name' => $service->name, 'name' => 'Should not change'],
        ])
        ->assertUnprocessable()
        ->assertDontSee((string) $service->getKey());

    $uniqueService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Serviço de relação',
    ]);
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $outOfScopeProfessional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'name' => 'Profissional fora da unidade',
    ]);

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'service-relation-out-of-scope')
        ->postJson('/api/v1/operations', [
            'operation' => 'service.update',
            'input' => ['service_name' => $uniqueService->name, 'professional_names' => [$outOfScopeProfessional->name]],
        ])
        ->assertUnprocessable()
        ->assertDontSee((string) $outOfScopeProfessional->getKey());

    expect(ProposedOperation::query()->where('operation_key', 'service.update')->count())->toBe(0);
});

it('rejects invalid or relationship-changing service update fields and an empty patch', function (): void {
    [$owner, $tenant, $unit] = serviceUpdateWorkspace();
    [$secret] = serviceUpdateToken($owner, $tenant, $unit);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $validInput = serviceUpdatePayload($service)['input'];
    $invalidInputs = [
        ['service_name' => (string) $service->name],
        [...$validInput, 'service_id' => (string) $service->getKey()],
        [...$validInput, 'expected_version' => -1],
        [...$validInput, 'duration_minutes' => 1441],
        [...$validInput, 'price_cents' => -1],
        [...$validInput, 'description' => 'Texto livre'],
        [...$validInput, 'status' => 'draft'],
        [...$validInput, 'category_name' => 42],
        [...$validInput, 'professional_names' => ['']],
        [...$validInput, 'image' => null],
    ];

    foreach ($invalidInputs as $index => $input) {
        $this->withToken($secret)
            ->withHeader('X-Idempotency-Key', 'service-invalid-'.$index)
            ->postJson('/api/v1/operations', ['operation' => 'service.update', 'input' => $input])
            ->assertUnprocessable();
    }

    expect(ProposedOperation::query()->where('operation_key', 'service.update')->count())->toBe(0);
});

it('shows current scalar fields only on the authenticated review page and flags a stale version', function (): void {
    [$owner, $tenant, $unit] = serviceUpdateWorkspace();
    [$secret] = serviceUpdateToken($owner, $tenant, $unit);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome atual privado',
        'description' => 'Descrição atual privada',
        'duration_minutes' => 90,
        'price_cents' => 9800,
        'status' => 'active',
        'lock_version' => 0,
    ]);
    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'service-review-stale')
        ->postJson('/api/v1/operations', serviceUpdatePayload($service))
        ->assertStatus(202)
        ->assertDontSee('Nome atual privado')
        ->assertDontSee('Descrição atual privada');
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));
    $service->forceFill(['name' => 'Nome alterado em outra sessão', 'lock_version' => 1])->save();

    $this->withToken($secret)
        ->getJson('/api/v1/operations/'.(string) $proposal->getKey())
        ->assertOk()
        ->assertJsonPath('data.summary.changed_fields', ['name', 'duration_minutes', 'price_cents', 'status'])
        ->assertDontSee('Nome atual privado')
        ->assertDontSee('Nome alterado em outra sessão')
        ->assertDontSee('Descrição atual privada')
        ->assertJsonMissingPath('data.currentService');

    $this->actingAs($owner)
        ->withSession([
            'tenant_id' => (string) $tenant->getKey(),
            'unit_id' => (string) $unit->getKey(),
        ])
        ->get(route('integration-proposals.show', $proposal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('proposal.currentService.name', 'Nome alterado em outra sessão')
            ->where('proposal.currentService.duration_minutes', 90)
            ->where('proposal.currentService.price_cents', 9800)
            ->where('proposal.currentService.lock_version', 1)
            ->where('proposal.proposedService.name', 'Nome proposto')
            ->where('proposal.expectedServiceVersionMatches', false)
            ->where('proposal.canConfirm', false));

    $this->actingAs($owner)
        ->withSession(serviceUpdateStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertConflict();

    $proposal->refresh();
    expect($proposal->status)->toBe(ProposedOperation::STATUS_NEEDS_REFRESH)
        ->and($service->fresh()->name)->toBe('Nome alterado em outra sessão')
        ->and($service->fresh()->lock_version)->toBe(1);
});
