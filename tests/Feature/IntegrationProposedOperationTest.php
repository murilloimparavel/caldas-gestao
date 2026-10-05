<?php

use App\Actions\Identity\OnboardTenant;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\ProposedOperation;
use App\Models\Integrations\StepUpProof;
use App\Models\Professional;
use App\Models\Service;
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
    Passport::loadKeysFrom(proposalPassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('Proposal API tests', 'users');
});

afterAll(function (): void {
    $directory = proposalPassportKeyDirectory();

    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function proposalPassportKeyDirectory(): string
{
    static $directory;

    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/proposal-passport-'.Str::uuid();
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
function proposalWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Proposal test '.Str::random(8),
        'slug' => 'proposal-test-'.Str::lower(Str::random(8)),
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
function proposalToken(User $owner, Tenant $tenant, Unit $unit, string $capability = 'operations:propose'): array
{
    $issued = $owner->createToken('Proposal test', [$capability]);
    $token = $issued->getToken();
    $credential = IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Proposal test',
        'capabilities' => [$capability],
        'expires_at' => $token->expires_at,
    ]);

    return [$issued->accessToken, $credential];
}

/** @return array<string, string> */
function proposalStepUpSession(User $user, Tenant $tenant, Unit $unit, ProposedOperation $proposal): array
{
    $passkey = Passkey::query()->forceCreate([
        'user_id' => $user->getKey(),
        'name' => 'Proposal review key',
        'credential_id' => 'proposal-'.Str::uuid(),
        'credential' => ['credentialId' => 'proposal-test'],
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

/** @param list<string> $professionalNames
 * @return array{operation: string, input: array{name: string, duration_minutes: int, price_cents: int, professional_names?: list<string>}}
 */
function serviceProposalPayload(array $professionalNames = []): array
{
    $input = [
        'name' => 'Corte clássico',
        'duration_minutes' => 30,
        'price_cents' => 4500,
    ];
    if ($professionalNames !== []) {
        $input['professional_names'] = $professionalNames;
    }

    return [
        'operation' => 'service.create',
        'input' => $input,
    ];
}

it('requires an explicit operations proposal capability and returns an idempotent proposal contract', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret] = proposalToken($owner, $tenant, $unit);
    $payload = serviceProposalPayload();

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-service-classic')
        ->postJson('/api/v1/operations', $payload);

    $response->assertStatus(202)
        ->assertJsonPath('data.operation', 'service.create')
        ->assertJsonPath('data.status', 'pending_confirmation')
        ->assertJsonPath('data.summary.changed_fields', ['name', 'duration_minutes', 'price_cents'])
        ->assertJsonMissingPath('data.summary.name')
        ->assertJsonMissingPath('data.summary.price_cents')
        ->assertJsonStructure(['data' => ['id', 'confirmation_url', 'expires_at', 'summary', 'changes']]);

    $proposalId = $response->json('data.id');
    expect(abs(ProposedOperation::query()->findOrFail($proposalId)->expires_at->diffInMinutes(now())))->toBeBetween(9, 10)
        ->and(Service::query()->where('name', 'Corte clássico')->exists())->toBeFalse();

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-service-classic')
        ->postJson('/api/v1/operations', $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.id', $proposalId);

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-service-classic')
        ->postJson('/api/v1/operations', [...$payload, 'input' => [...$payload['input'], 'price_cents' => 5000]])
        ->assertConflict();
});

it('builds proposal confirmation URLs from the configured app URL instead of the request host', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret] = proposalToken($owner, $tenant, $unit);
    $configuredAppUrl = (string) config('app.url');

    $response = $this->withToken($secret)
        ->withHeader('Host', 'attacker.example')
        ->withHeader('X-Idempotency-Key', 'host-header-poisoning')
        ->postJson('/api/v1/operations', serviceProposalPayload())
        ->assertStatus(202);

    expect($response->json('data.confirmation_url'))
        ->toStartWith(rtrim($configuredAppUrl, '/').'/settings/integrations/proposals/')
        ->not->toContain('attacker.example');
});

it('does not treat the existing context read capability as permission to propose writes', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret] = proposalToken($owner, $tenant, $unit, 'context:read');

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'read-only-cannot-write')
        ->postJson('/api/v1/operations', serviceProposalPayload())
        ->assertForbidden();
});

it('confirms a proposal once through the admin session and recent passkey step-up', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret] = proposalToken($owner, $tenant, $unit);
    $created = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-to-confirm')
        ->postJson('/api/v1/operations', serviceProposalPayload())
        ->assertStatus(202);
    $proposalId = $created->json('data.id');
    $proposal = ProposedOperation::query()->findOrFail($proposalId);

    $this->actingAs($owner)
        ->withSession(proposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposalId))
        ->assertRedirect(route('integration-proposals.show', $proposalId));

    $proposal = ProposedOperation::query()->findOrFail($proposalId);
    expect($proposal->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($proposal->result_reference['resource_type'])->toBe('service')
        ->and(Service::query()->where('tenant_id', $tenant->getKey())->where('unit_id', $unit->getKey())->where('name', 'Corte clássico')->count())->toBe(1);

    $this->actingAs($owner)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        ])
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->post(route('integration-proposals.confirm', $proposalId))
        ->assertForbidden();

    expect(Service::query()->where('name', 'Corte clássico')->count())->toBe(1);
});

it('rejects confirmation when the passkey proof was issued for another proposal in the same unit', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret] = proposalToken($owner, $tenant, $unit);
    $first = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-bound-first')
        ->postJson('/api/v1/operations', serviceProposalPayload())
        ->assertStatus(202);
    $second = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-bound-second')
        ->postJson('/api/v1/operations', serviceProposalPayload())
        ->assertStatus(202);
    $firstProposal = ProposedOperation::query()->findOrFail($first->json('data.id'));
    $secondProposalId = $second->json('data.id');

    $this->actingAs($owner)
        ->withSession(proposalStepUpSession($owner, $tenant, $unit, $firstProposal))
        ->post(route('integration-proposals.confirm', $secondProposalId))
        ->assertForbidden();

    expect(ProposedOperation::query()->findOrFail($firstProposal->getKey())->status)->toBe(ProposedOperation::STATUS_PENDING_CONFIRMATION)
        ->and(ProposedOperation::query()->findOrFail($secondProposalId)->status)->toBe(ProposedOperation::STATUS_PENDING_CONFIRMATION)
        ->and(Service::query()->where('name', 'Corte clássico')->exists())->toBeFalse();
});

it('expires a proposal after ten minutes without creating its service', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret] = proposalToken($owner, $tenant, $unit);
    $created = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-that-expires')
        ->postJson('/api/v1/operations', serviceProposalPayload())
        ->assertStatus(202);
    $proposalId = $created->json('data.id');
    $proposal = ProposedOperation::query()->findOrFail($proposalId);
    ProposedOperation::query()->whereKey($proposalId)->update(['expires_at' => now()->subSecond()]);

    $this->actingAs($owner)
        ->withSession(proposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposalId))
        ->assertConflict();

    expect(ProposedOperation::query()->findOrFail($proposalId)->status)->toBe(ProposedOperation::STATUS_EXPIRED)
        ->and(Service::query()->where('name', 'Corte clássico')->exists())->toBeFalse();
});

it('rejects confirmation when the credential was revoked after proposal creation', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret, $credential] = proposalToken($owner, $tenant, $unit);
    $created = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-revoked-credential')
        ->postJson('/api/v1/operations', serviceProposalPayload())
        ->assertStatus(202);
    $proposalId = $created->json('data.id');
    $proposal = ProposedOperation::query()->findOrFail($proposalId);
    $credential->passportToken()->firstOrFail()->revoke();

    $this->actingAs($owner)
        ->withSession(proposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposalId))
        ->assertForbidden();

    expect(ProposedOperation::query()->findOrFail($proposalId)->status)->toBe(ProposedOperation::STATUS_PENDING_CONFIRMATION)
        ->and(Service::query()->where('name', 'Corte clássico')->exists())->toBeFalse();
});

it('checks the immutable payload hash before executing a proposal', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret] = proposalToken($owner, $tenant, $unit);
    $created = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-payload-tamper')
        ->postJson('/api/v1/operations', serviceProposalPayload())
        ->assertStatus(202);
    $proposalId = $created->json('data.id');
    $proposal = ProposedOperation::query()->findOrFail($proposalId);
    ProposedOperation::query()->whereKey($proposalId)->update(['input' => json_encode([
        'duration_minutes' => 30,
        'name' => 'Alterado',
        'price_cents' => 4500,
    ], JSON_THROW_ON_ERROR)]);

    $this->actingAs($owner)
        ->withSession(proposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposalId))
        ->assertConflict();

    expect(ProposedOperation::query()->findOrFail($proposalId)->status)->toBe(ProposedOperation::STATUS_FAILED)
        ->and(Service::query()->where('name', 'Alterado')->exists())->toBeFalse();
});

it('shows selected professionals only on the authenticated review page', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret] = proposalToken($owner, $tenant, $unit);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbeiro da unidade',
    ]);
    $created = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-with-professional')
        ->postJson('/api/v1/operations', serviceProposalPayload([$professional->name]))
        ->assertStatus(202)
        ->assertJsonPath('data.summary.changed_fields', ['name', 'duration_minutes', 'price_cents', 'professional_names'])
        ->assertJsonMissingPath('data.summary.professional_count');

    expect($created->json('data.summary.professionals'))->toBeNull();

    $pageResponse = $this->actingAs($owner)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->get(route('integration-proposals.show', $created->json('data.id')));
    expect($pageResponse->status())->toBe(200, $pageResponse->getContent());
    $pageResponse->assertInertia(fn ($page) => $page
        ->where('proposal.summary.professionals.0.id', (string) $professional->getKey())
        ->where('proposal.summary.professionals.0.name', 'Barbeiro da unidade')
        ->where('proposal.summary.professionalAssignmentsValid', true)
        ->where('proposal.canConfirm', true));
});

it('blocks confirmation and warns when a selected professional moves outside the proposal unit', function (): void {
    [$owner, $tenant, $unit] = proposalWorkspace();
    [$secret] = proposalToken($owner, $tenant, $unit);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbeiro transferido',
    ]);
    $created = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'proposal-with-moved-professional')
        ->postJson('/api/v1/operations', serviceProposalPayload([$professional->name]))
        ->assertStatus(202);
    $proposalId = $created->json('data.id');
    $proposal = ProposedOperation::query()->findOrFail($proposalId);
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $professional->update(['unit_id' => $otherUnit->getKey()]);

    $this->actingAs($owner)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->get(route('integration-proposals.show', $proposalId))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('proposal.summary.professionals.0.id', (string) $professional->getKey())
            ->where('proposal.summary.professionals.0.name', null)
            ->where('proposal.summary.professionalAssignmentsValid', false)
            ->where('proposal.canConfirm', false));

    $this->actingAs($owner)
        ->withSession(proposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposalId))
        ->assertConflict();

    expect(ProposedOperation::query()->findOrFail($proposalId)->status)->toBe(ProposedOperation::STATUS_NEEDS_REFRESH)
        ->and(Service::query()->where('name', 'Corte clássico')->exists())->toBeFalse();
});
