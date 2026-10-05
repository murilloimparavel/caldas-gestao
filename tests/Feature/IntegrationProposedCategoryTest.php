<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Category;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\ProposedOperation;
use App\Models\Integrations\StepUpProof;
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
    Passport::loadKeysFrom(categoryProposalPassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('Category proposal tests', 'users');
});

afterAll(function (): void {
    $directory = categoryProposalPassportKeyDirectory();
    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function categoryProposalPassportKeyDirectory(): string
{
    static $directory;
    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/category-proposal-passport-'.Str::uuid();
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
function categoryProposalWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Category proposal '.Str::random(8),
        'slug' => 'category-proposal-'.Str::lower(Str::random(8)),
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
function categoryProposalToken(User $owner, Tenant $tenant, Unit $unit): array
{
    $issued = $owner->createToken('Category proposal test', ['operations:propose']);
    $token = $issued->getToken();
    $credential = IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Category proposal test',
        'capabilities' => ['operations:propose'],
        'expires_at' => $token->expires_at,
    ]);

    return [$issued->accessToken, $credential];
}

/** @return array<string, string> */
function categoryProposalStepUpSession(User $user, Tenant $tenant, Unit $unit, ProposedOperation $proposal): array
{
    $passkey = Passkey::query()->forceCreate([
        'user_id' => $user->getKey(),
        'name' => 'Category proposal key',
        'credential_id' => 'category-proposal-'.Str::uuid(),
        'credential' => ['credentialId' => 'category-proposal-test'],
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

/** @return array{operation: string, input: array{name: string, type: string, is_active?: bool}} */
function categoryProposalPayload(array $input = []): array
{
    return [
        'operation' => 'category.create',
        'input' => $input + [
            'name' => 'Barba',
            'type' => 'service',
        ],
    ];
}

/** @return array{operation: string, input: array{category_name: string, name: string, type: string, is_active: bool}} */
function categoryUpdateProposalPayload(Category $category, array $input = []): array
{
    return [
        'operation' => 'category.update',
        'input' => $input + [
            'category_name' => (string) $category->name,
            'name' => 'Nome novo da categoria',
            'type' => 'product',
            'is_active' => false,
        ],
    ];
}

it('rejects extra scope and lock fields without creating a category or proposal', function (): void {
    [$owner, $tenant, $unit] = categoryProposalWorkspace();
    [$secret] = categoryProposalToken($owner, $tenant, $unit);

    foreach (['tenant_id' => (string) $tenant->getKey(), 'unit_id' => (string) $unit->getKey(), 'lock_version' => 77] as $key => $value) {
        $this->withToken($secret)
            ->withHeader('X-Idempotency-Key', 'category-extra-'.$key)
            ->postJson('/api/v1/operations', categoryProposalPayload([$key => $value]))
            ->assertUnprocessable();
    }

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-description-forbidden')
        ->postJson('/api/v1/operations', categoryProposalPayload(['description' => 'Texto livre']))
        ->assertUnprocessable();

    expect(ProposedOperation::query()->where('operation_key', 'category.create')->count())->toBe(0)
        ->and(Category::query()->where('name', 'Barba')->exists())->toBeFalse();
});

it('creates a pending idempotent category proposal without writing category data', function (): void {
    [$owner, $tenant, $unit] = categoryProposalWorkspace();
    [$secret] = categoryProposalToken($owner, $tenant, $unit);
    $payload = categoryProposalPayload();

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-create-barba')
        ->postJson('/api/v1/operations', $payload);

    $response->assertStatus(202)
        ->assertJsonPath('data.operation', 'category.create')
        ->assertJsonPath('data.summary.changed_fields', ['name', 'type', 'is_active'])
        ->assertJsonMissingPath('data.summary.name')
        ->assertJsonMissingPath('data.summary.type')
        ->assertJsonMissingPath('data.summary.is_active');
    $proposalId = $response->json('data.id');
    $proposal = ProposedOperation::query()->findOrFail($proposalId);
    expect($proposal->operation_key)->toBe('category.create')
        ->and($proposal->input['is_active'])->toBeTrue()
        ->and(Category::query()->where('tenant_id', $tenant->getKey())->where('unit_id', $unit->getKey())->exists())->toBeFalse();

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-create-barba')
        ->postJson('/api/v1/operations', $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.id', $proposalId);

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-create-barba')
        ->postJson('/api/v1/operations', categoryProposalPayload(['name' => 'Barba Premium']))
        ->assertConflict();
});

it('creates a category once only after an administrator confirms with the proposal-bound passkey proof', function (): void {
    [$owner, $tenant, $unit] = categoryProposalWorkspace();
    [$secret] = categoryProposalToken($owner, $tenant, $unit);
    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-create-confirmed')
        ->postJson('/api/v1/operations', categoryProposalPayload(['is_active' => false]))
        ->assertStatus(202);
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));

    $this->actingAs($owner)
        ->withSession(categoryProposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->get(route('integration-proposals.show', $proposal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/integrations/proposals/show')
            ->where('proposal.operation', 'category.create')
            ->where('proposal.summary.name', 'Barba')
            ->where('proposal.summary.type', 'service')
            ->where('proposal.summary.is_active', false));

    $this->actingAs($owner)
        ->withSession(categoryProposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertRedirect(route('integration-proposals.show', $proposal));

    $proposal->refresh();
    expect($proposal->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($proposal->result_reference['resource_type'])->toBe('category')
        ->and(Category::query()->where('tenant_id', $tenant->getKey())->where('unit_id', $unit->getKey())->where('name', 'Barba')->count())->toBe(1)
        ->and(Category::query()->where('name', 'Barba')->firstOrFail()->is_active)->toBeFalse();
});

it('proposes partial category changes without exposing values and applies them after passkey confirmation', function (): void {
    [$owner, $tenant, $unit] = categoryProposalWorkspace();
    [$secret] = categoryProposalToken($owner, $tenant, $unit);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome confidencial anterior',
        'type' => 'general',
        'description' => 'Descrição confidencial anterior',
        'is_active' => true,
        'lock_version' => 4,
    ]);
    $payload = categoryUpdateProposalPayload($category);

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-update-replacement')
        ->postJson('/api/v1/operations', $payload);

    $response->assertStatus(202)
        ->assertJsonPath('data.operation', 'category.update')
        ->assertJsonPath('data.summary.changed_fields', ['name', 'type', 'is_active'])
        ->assertJsonMissingPath('data.summary.category_id')
        ->assertJsonMissingPath('data.summary.expected_version')
        ->assertDontSee('Nome confidencial anterior')
        ->assertDontSee('Descrição confidencial anterior');
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));
    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-update-replacement')
        ->postJson('/api/v1/operations', $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.id', (string) $proposal->getKey())
        ->assertDontSee('Nome confidencial anterior')
        ->assertDontSee('Descrição confidencial anterior');

    $this->withToken($secret)
        ->getJson('/api/v1/operations/'.(string) $proposal->getKey())
        ->assertOk()
        ->assertJsonPath('data.summary.changed_fields', ['name', 'type', 'is_active'])
        ->assertDontSee('Nome confidencial anterior')
        ->assertDontSee('Descrição confidencial anterior');

    $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-update-replacement')
        ->postJson('/api/v1/operations', categoryUpdateProposalPayload($category, ['name' => 'Outro nome']))
        ->assertConflict();

    expect($proposal->status)->toBe(ProposedOperation::STATUS_PENDING_CONFIRMATION)
        ->and($category->fresh()->name)->toBe('Nome confidencial anterior')
        ->and($category->fresh()->description)->toBe('Descrição confidencial anterior');

    $this->actingAs($owner)
        ->withSession(categoryProposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertRedirect(route('integration-proposals.show', $proposal));

    $proposal->refresh();
    $category->refresh();
    expect($proposal->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($proposal->result_reference['resource_type'])->toBe('category')
        ->and($category->name)->toBe('Nome novo da categoria')
        ->and($category->type)->toBe('product')
        ->and($category->description)->toBe('Descrição confidencial anterior')
        ->and($category->is_active)->toBeFalse()
        ->and($category->lock_version)->toBe(5);
});

it('preserves omitted category fields in a partial proposal and requires at least one changed field', function (): void {
    [$owner, $tenant, $unit] = categoryProposalWorkspace();
    [$secret] = categoryProposalToken($owner, $tenant, $unit);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome confidencial preservado',
        'type' => 'general',
        'description' => 'Descrição mantida',
        'is_active' => true,
        'lock_version' => 4,
    ]);

    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-update-name-only')
        ->postJson('/api/v1/operations', [
            'operation' => 'category.update',
            'input' => [
                'category_name' => (string) $category->name,
                'name' => 'Nome alterado',
            ],
        ])
        ->assertAccepted()
        ->assertJsonPath('data.summary.changed_fields', ['name'])
        ->assertJsonMissingPath('data.summary.category_id')
        ->assertDontSee('Nome confidencial preservado');

    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));
    $this->actingAs($owner)
        ->withSession(categoryProposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertRedirect(route('integration-proposals.show', $proposal));

    $review = $this->actingAs($owner)
        ->withSession(['tenant_id' => (string) $tenant->getKey(), 'unit_id' => (string) $unit->getKey()])
        ->get(route('integration-proposals.show', $proposal));
    $review->assertOk();
    expect($review->viewData('page')['props']['proposal']['proposedCategory']['name'])->toBe('Nome alterado');

    $category->refresh();
    expect($category->name)->toBe('Nome alterado')
        ->and($category->type)->toBe('general')
        ->and($category->description)->toBe('Descrição mantida')
        ->and($category->is_active)->toBeTrue();

});

it('rejects category updates outside the bound tenant and unit', function (): void {
    [$owner, $tenant, $unit] = categoryProposalWorkspace();
    [$secret] = categoryProposalToken($owner, $tenant, $unit);
    $otherTenantCategory = Category::factory()->create();
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    MembershipUnit::factory()->forMembership($owner->memberships()->where('tenant_id', $tenant->getKey())->firstOrFail())->forUnit($otherUnit)->create();
    $otherUnitCategory = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
    ]);

    foreach ([$otherTenantCategory, $otherUnitCategory] as $index => $category) {
        $this->withToken($secret)
            ->withHeader('X-Idempotency-Key', 'category-out-of-scope-'.$index)
            ->postJson('/api/v1/operations', categoryUpdateProposalPayload($category))
            ->assertUnprocessable();
    }

    expect(ProposedOperation::query()->where('operation_key', 'category.update')->count())->toBe(0);
});

it('validates category update payloads strictly and requires at least one changed field', function (): void {
    [$owner, $tenant, $unit] = categoryProposalWorkspace();
    [$secret] = categoryProposalToken($owner, $tenant, $unit);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $validInput = categoryUpdateProposalPayload($category)['input'];
    $invalidInputs = [
        ['category_name' => (string) $category->name],
        [...$validInput, 'category_id' => (string) $category->getKey()],
        [...$validInput, 'expected_version' => 0],
        [...$validInput, 'is_active' => 'sometimes'],
        [...$validInput, 'unexpected' => 'value'],
    ];

    foreach ($invalidInputs as $index => $override) {
        $this->withToken($secret)
            ->withHeader('X-Idempotency-Key', 'category-invalid-'.$index)
            ->postJson('/api/v1/operations', ['operation' => 'category.update', 'input' => $override])
            ->assertUnprocessable();
    }

    expect(ProposedOperation::query()->where('operation_key', 'category.update')->count())->toBe(0);
});

it('marks a stale category update as needing refresh without mutating the category', function (): void {
    [$owner, $tenant, $unit] = categoryProposalWorkspace();
    [$secret] = categoryProposalToken($owner, $tenant, $unit);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome anterior',
        'lock_version' => 4,
    ]);
    $response = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-update-stale')
        ->postJson('/api/v1/operations', categoryUpdateProposalPayload($category))
        ->assertStatus(202);
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));
    $category->forceFill(['name' => 'Alterada por outra sessão', 'lock_version' => 5])->save();

    $this->actingAs($owner)
        ->withSession(categoryProposalStepUpSession($owner, $tenant, $unit, $proposal))
        ->post(route('integration-proposals.confirm', $proposal))
        ->assertConflict();

    $proposal->refresh();
    $category->refresh();
    expect($proposal->status)->toBe(ProposedOperation::STATUS_NEEDS_REFRESH)
        ->and($category->name)->toBe('Alterada por outra sessão')
        ->and($category->lock_version)->toBe(5);
});
