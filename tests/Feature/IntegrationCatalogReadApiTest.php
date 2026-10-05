<?php

use App\Actions\Identity\OnboardTenant;
use App\Enums\MembershipStatus;
use App\Models\Category;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\OnlineBookingDraft;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    Passport::loadKeysFrom(catalogReadPassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('Catalog read API tests', 'users');
});

afterAll(function (): void {
    $directory = catalogReadPassportKeyDirectory();

    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function catalogReadPassportKeyDirectory(): string
{
    static $directory;

    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/catalog-read-passport-'.Str::uuid();
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

/** @return array{0: User, 1: Tenant, 2: Unit} */
function catalogReadWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Catalog read '.Str::random(8),
        'slug' => 'catalog-read-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);

    return [$owner, $tenant, $unit];
}

function catalogReadToken(User $user, Tenant $tenant, Unit $unit, string $capability = 'catalog:read'): string
{
    $issued = $user->createToken('Catalog read test', [$capability]);
    $token = $issued->getToken();

    IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Catalog read test',
        'capabilities' => [$capability],
        'expires_at' => $token->expires_at,
    ]);

    return $issued->accessToken;
}

it('returns only the authorized catalog fields with stable pagination', function (): void {
    [$owner, $tenant, $unit] = catalogReadWorkspace();
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Cortes',
        'type' => 'service',
    ]);
    Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbas',
        'type' => 'service',
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $category->getKey(),
        'name' => 'Corte clássico',
        'duration_minutes' => 35,
        'price_cents' => 4500,
    ]);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'João Barbeiro',
    ]);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $secret = catalogReadToken($owner, $tenant, $unit);

    $this->withToken($secret)
        ->getJson('/api/v1/categories?per_page=1&page=1')
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonMissingPath('meta.total')
        ->assertJsonMissingPath('meta.last_page')
        ->assertJsonMissingPath('meta.from')
        ->assertJsonMissingPath('meta.to')
        ->assertJsonPath('data.0.name', 'Barbas');
    $this->withToken($secret)
        ->getJson('/api/v1/categories?per_page=1&page=2')
        ->assertSuccessful()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('data.0.name', 'Cortes');
    expect(array_keys($this->withToken($secret)->getJson('/api/v1/categories?search=Cortes')->json('data.0')))
        ->toBe(['name']);

    $serviceData = $this->withToken($secret)->getJson('/api/v1/services')->json('data.0');
    expect($serviceData)->toBe([
        'name' => 'Corte clássico',
        'duration_minutes' => 35,
        'price_cents' => 4500,
    ])->not->toHaveKey('description')
        ->not->toHaveKey('phone')
        ->not->toHaveKey('email')
        ->not->toHaveKey('id')
        ->not->toHaveKey('category_id')
        ->not->toHaveKey('status');

    expect($this->withToken($secret)->getJson('/api/v1/professionals')->json('data.0'))
        ->toBe(['name' => 'João Barbeiro']);

    expect($this->withToken($secret)->getJson('/api/v1/categories/'.$category->getKey())->json('data'))
        ->toBe(['name' => 'Cortes']);
    expect($this->withToken($secret)->getJson('/api/v1/services/'.$service->getKey())->json('data'))
        ->toBe([
            'name' => 'Corte clássico',
            'duration_minutes' => 35,
            'price_cents' => 4500,
        ]);
    expect($this->withToken($secret)->getJson('/api/v1/professionals/'.$professional->getKey())->json('data'))
        ->toBe(['name' => 'João Barbeiro']);
});

it('keeps every catalog detail and show endpoint tenant and unit scoped', function (): void {
    [$owner, $tenant, $unit] = catalogReadWorkspace();
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $otherTenant = Tenant::factory()->create();
    $otherTenantUnit = Unit::factory()->create(['tenant_id' => $otherTenant->getKey()]);
    $local = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Local']);
    $otherUnitService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $otherUnit->getKey(), 'name' => 'Outra unidade']);
    $otherTenantService = Service::factory()->create(['tenant_id' => $otherTenant->getKey(), 'unit_id' => $otherTenantUnit->getKey(), 'name' => 'Outro tenant']);
    $secret = catalogReadToken($owner, $tenant, $unit);

    $this->withToken($secret)->getJson('/api/v1/services')->assertJsonPath('data.0.name', 'Local')->assertJsonMissing(['name' => 'Outra unidade'])->assertJsonMissing(['name' => 'Outro tenant']);
    $this->withToken($secret)->getJson('/api/v1/services/'.$otherUnitService->getKey())->assertNotFound();
    $this->withToken($secret)->getJson('/api/v1/services/'.$otherTenantService->getKey())->assertNotFound();
});

it('requires the dedicated catalog capability and administrative ownership', function (): void {
    [$owner, $tenant, $unit] = catalogReadWorkspace();
    $contextSecret = catalogReadToken($owner, $tenant, $unit, 'context:read');
    $this->withToken($contextSecret)->getJson('/api/v1/services')->assertForbidden();

    $collaborator = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $collaborator->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create();
    $collaboratorSecret = catalogReadToken($collaborator, $tenant, $unit);

    $this->withToken($collaboratorSecret)->getJson('/api/v1/services')->assertForbidden();
});

it('reports setup indicators without creating booking records and matches publication readiness', function (): void {
    [$owner, $tenant, $unit] = catalogReadWorkspace();
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'status' => 'active']);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'status' => 'active']);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $secret = catalogReadToken($owner, $tenant, $unit, 'setup:read');

    $this->withToken($secret)->getJson('/api/v1/setup/status')
        ->assertSuccessful()
        ->assertJsonPath('data.unit.active', true)
        ->assertJsonPath('data.unit.timezone_configured', false)
        ->assertJsonPath('data.catalog.active_services', 1)
        ->assertJsonPath('data.catalog.active_professionals', 1)
        ->assertJsonPath('data.booking.publishable', false);
    expect(OnlineBookingSite::query()->where('unit_id', $unit->getKey())->count())->toBe(0);

    $unit->forceFill(['online_booking_enabled' => true])->save();
    $site = OnlineBookingSite::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => 'catalog-read-'.Str::lower(Str::random(8)),
        'status' => 'unpublished',
        'draft_revision' => 1,
        'lock_version' => 0,
    ]);
    OnlineBookingDraft::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'site_id' => $site->getKey(),
        'revision' => 1,
        'content' => ['service_ids' => [$service->getKey()], 'professional_ids' => [$professional->getKey()]],
        'content_hash' => hash('sha256', 'catalog-read-draft'),
        'updated_by' => $owner->getKey(),
    ]);

    $this->withToken($secret)->getJson('/api/v1/setup/status')
        ->assertSuccessful()
        ->assertJsonPath('data.booking.site_exists', true)
        ->assertJsonPath('data.booking.draft_exists', true)
        ->assertJsonPath('data.booking.publishable', true)
        ->assertJsonPath('data.booking.blockers', []);
    expect(OnlineBookingSite::query()->where('unit_id', $unit->getKey())->count())->toBe(1);
});

it('rejects invalid pagination instead of allowing unbounded reads', function (): void {
    [$owner, $tenant, $unit] = catalogReadWorkspace();
    $secret = catalogReadToken($owner, $tenant, $unit);

    $this->withToken($secret)->getJson('/api/v1/services?per_page=101')->assertUnprocessable();
    $this->withToken($secret)->getJson('/api/v1/services?page=0')->assertUnprocessable();
});
