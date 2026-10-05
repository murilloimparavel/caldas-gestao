<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Category;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\ProposedOperation;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    Passport::loadKeysFrom(categoryReviewPassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('Category review tests', 'users');
});

afterAll(function (): void {
    $directory = categoryReviewPassportKeyDirectory();
    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function categoryReviewPassportKeyDirectory(): string
{
    static $directory;
    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/category-review-passport-'.Str::uuid();
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
function categoryReviewWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Category review '.Str::random(8),
        'slug' => 'category-review-'.Str::lower(Str::random(8)),
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
function categoryReviewToken(User $owner, Tenant $tenant, Unit $unit): array
{
    $issued = $owner->createToken('Category review test', ['operations:propose']);
    $token = $issued->getToken();
    $credential = IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Category review test',
        'capabilities' => ['operations:propose'],
        'expires_at' => $token->expires_at,
    ]);

    return [$issued->accessToken, $credential];
}

function categoryReviewPayload(Category $category): array
{
    return [
        'operation' => 'category.update',
        'input' => [
            'category_name' => (string) $category->name,
            'name' => 'Nome proposto',
            'type' => 'product',
            'is_active' => false,
        ],
    ];
}

it('shows current and proposed category values only on the authenticated review page', function (): void {
    [$owner, $tenant, $unit] = categoryReviewWorkspace();
    [$secret] = categoryReviewToken($owner, $tenant, $unit);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome atual privado',
        'type' => 'service',
        'description' => 'Descrição atual privada',
        'is_active' => true,
        'lock_version' => 3,
    ]);
    $proposalResponse = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-review-private')
        ->postJson('/api/v1/operations', categoryReviewPayload($category))
        ->assertStatus(202)
        ->assertDontSee('Nome atual privado')
        ->assertDontSee('Descrição atual privada');
    $proposal = ProposedOperation::query()->findOrFail($proposalResponse->json('data.id'));

    $this->withToken($secret)
        ->getJson('/api/v1/operations/'.(string) $proposal->getKey())
        ->assertOk()
        ->assertDontSee('Nome atual privado')
        ->assertDontSee('Descrição atual privada')
        ->assertJsonMissingPath('data.currentCategory');

    $this->actingAs($owner)
        ->withSession([
            'tenant_id' => (string) $tenant->getKey(),
            'unit_id' => (string) $unit->getKey(),
        ])
        ->get(route('integration-proposals.show', $proposal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/integrations/proposals/show')
            ->where('proposal.currentCategory.name', 'Nome atual privado')
            ->where('proposal.currentCategory.description', 'Descrição atual privada')
            ->where('proposal.currentCategory.lock_version', 3)
            ->where('proposal.proposedCategory.name', 'Nome proposto')
            ->where('proposal.expectedCategoryVersionMatches', true)
            ->where('proposal.canConfirm', true));
});

it('shows a stale category version and disables confirmation on the review page', function (): void {
    [$owner, $tenant, $unit] = categoryReviewWorkspace();
    [$secret] = categoryReviewToken($owner, $tenant, $unit);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome original',
        'lock_version' => 3,
    ]);
    $proposalResponse = $this->withToken($secret)
        ->withHeader('X-Idempotency-Key', 'category-review-stale')
        ->postJson('/api/v1/operations', categoryReviewPayload($category))
        ->assertStatus(202);
    $proposal = ProposedOperation::query()->findOrFail($proposalResponse->json('data.id'));
    $category->forceFill(['name' => 'Nome atualizado em outra sessão', 'lock_version' => 4])->save();

    $this->actingAs($owner)
        ->withSession([
            'tenant_id' => (string) $tenant->getKey(),
            'unit_id' => (string) $unit->getKey(),
        ])
        ->get(route('integration-proposals.show', $proposal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('proposal.currentCategory.name', 'Nome atualizado em outra sessão')
            ->where('proposal.currentCategory.lock_version', 4)
            ->where('proposal.proposedCategory.name', 'Nome proposto')
            ->where('proposal.expectedCategoryVersionMatches', false)
            ->where('proposal.canConfirm', false));
});
