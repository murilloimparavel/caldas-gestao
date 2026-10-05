<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\PackageTemplate;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\SaleCategory;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

it('returns paginated active selector options scoped to the tenant unit', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Selector '.Str::random(8),
        'slug' => 'selector-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $otherTenant = (new OnboardTenant)->handle(User::factory()->create(), [
        'name' => 'Other '.Str::random(8),
        'slug' => 'other-'.Str::lower(Str::random(8)),
    ]);

    Customer::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'name' => 'Ana da Unidade']);
    Customer::factory()->create(['tenant_id' => $otherTenant->id, 'unit_id' => $otherTenant->units()->firstOrFail()->id, 'name' => 'Ana de Outro Tenant']);
    Service::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'name' => 'Corte Premium', 'duration_minutes' => 45, 'price_cents' => 12000]);
    Product::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'name' => 'Produto Inativo', 'is_active' => false]);
    Supplier::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => null, 'name' => 'Fornecedor Global']);

    $response = $this
        ->actingAs($owner)
        ->withHeaders(['X-Tenant-Id' => $tenant->id, 'X-Unit-Id' => $unit->id])
        ->getJson(route('selector-options.index', ['resource' => 'services', 'per_page' => 1]));

    $response->assertSuccessful()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Corte Premium')
        ->assertJsonPath('data.0.duration_minutes', 45)
        ->assertJsonPath('data.0.price_cents', 12000);

    $supplierResponse = $this
        ->actingAs($owner)
        ->withHeaders(['X-Tenant-Id' => $tenant->id, 'X-Unit-Id' => $unit->id])
        ->getJson(route('selector-options.index', ['resource' => 'suppliers']));

    $supplierResponse->assertSuccessful()->assertJsonPath('data.0.name', 'Fornecedor Global');

    $emptySearchResponse = $this
        ->actingAs($owner)
        ->withHeaders(['X-Tenant-Id' => $tenant->id, 'X-Unit-Id' => $unit->id])
        ->getJson(route('selector-options.index', ['resource' => 'services', 'search' => '']));

    $emptySearchResponse->assertSuccessful()->assertJsonPath('data.0.name', 'Corte Premium');
});

it('rejects unauthenticated and arbitrary selector resources', function () {
    $this->getJson(route('selector-options.index', ['resource' => 'customers']))
        ->assertUnauthorized();

    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Selector '.Str::random(8)]);
    $unit = $tenant->units()->firstOrFail();

    $this->actingAs($owner)
        ->withHeaders(['X-Tenant-Id' => $tenant->id, 'X-Unit-Id' => $unit->id])
        ->getJson(route('selector-options.index', ['resource' => 'users']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('resource');
});

it('limits professional selectors to the linked professional', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Selector '.Str::random(8)]);
    $unit = $tenant->units()->firstOrFail();
    $linked = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Profissional próprio']);
    $other = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Outro profissional']);
    $collaborator = User::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $collaborator->getKey(), 'professional_id' => $linked->getKey(), 'status' => 'active']);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Profissional', 'key' => 'professional-'.Str::lower(Str::random(8))]);
    $permission = Permission::query()->where('key', 'professional.view')->firstOrFail();
    RolePermission::query()->create(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $permission->getKey()]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $response = $this->actingAs($collaborator)
        ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
        ->getJson(route('selector-options.index', ['resource' => 'professionals']));

    $response->assertSuccessful()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $linked->getKey());
    expect($response->json('data'))->not->toContain(['id' => $other->getKey(), 'name' => $other->name, 'phone' => $other->phone]);
});

it('serializes only the allowlisted fields for every selector resource', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Selector '.Str::random(8),
        'slug' => 'selector-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $attributes = ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()];

    Customer::factory()->create([...$attributes, 'name' => 'Cliente seletor']);
    Professional::factory()->create([...$attributes, 'name' => 'Profissional seletor']);
    Service::factory()->create([...$attributes, 'name' => 'Serviço seletor']);
    Product::factory()->create([...$attributes, 'name' => 'Produto seletor']);
    Supplier::factory()->create([...$attributes, 'name' => 'Fornecedor seletor']);
    Category::factory()->create([...$attributes, 'name' => 'Categoria seletor']);
    SaleCategory::factory()->create([...$attributes, 'name' => 'Categoria de venda seletor']);
    PackageTemplate::factory()->create([...$attributes, 'name' => 'Pacote seletor']);
    SubscriptionPlan::factory()->create([...$attributes, 'name' => 'Plano seletor']);

    $expectedKeys = [
        'customers' => ['id', 'name', 'phone'],
        'professionals' => ['id', 'name', 'phone'],
        'services' => ['id', 'name', 'duration_minutes', 'price_cents', 'category_id'],
        'products' => ['id', 'name', 'sale_price_cents', 'current_stock', 'category_id'],
        'inventory-products' => ['id', 'name', 'current_stock', 'min_stock', 'unit_of_measure', 'lock_version', 'cost_price_cents'],
        'suppliers' => ['id', 'name', 'trade_name', 'phone', 'email'],
        'categories' => ['id', 'name', 'type'],
        'sale-categories' => ['id', 'name', 'type', 'uniqueness_scope'],
        'packages' => ['id', 'name', 'price_cents', 'total_sessions', 'validity_days'],
        'subscription-plans' => ['id', 'name', 'price_cents', 'billing_cycle'],
    ];

    foreach ($expectedKeys as $resource => $keys) {
        $response = $this
            ->actingAs($owner)
            ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
            ->getJson(route('selector-options.index', ['resource' => $resource]));

        $response->assertSuccessful();
        expect(array_keys($response->json('data.0')))->toBe($keys);
    }
});

it('supports selector search fields and caps page size', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Selector '.Str::random(8),
        'slug' => 'selector-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $headers = ['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()];

    Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Cliente por telefone',
        'phone' => '5511999991111',
    ]);
    Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'João da Silva',
    ]);
    Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Cliente com telefone formatado',
        'phone' => '(35) 99999-0004',
        'phone_normalized' => '35999990004',
    ]);
    Supplier::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => null,
        'name' => 'Fornecedor por contato',
        'trade_name' => 'Marca seletora',
        'email' => 'seletor@example.test',
    ]);
    Product::factory()->count(2)->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Produto inativo',
        'is_active' => false,
    ]);

    $this->actingAs($owner)->withHeaders($headers)
        ->getJson(route('selector-options.index', ['resource' => 'customers', 'search' => '  999991111  ']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Cliente por telefone');

    $this->actingAs($owner)->withHeaders($headers)
        ->getJson(route('selector-options.index', ['resource' => 'customers', 'search' => '  JOAO  ']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'João da Silva');

    $this->actingAs($owner)->withHeaders($headers)
        ->getJson(route('selector-options.index', ['resource' => 'customers', 'search' => '35 99999-0004']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Cliente com telefone formatado');

    $this->actingAs($owner)->withHeaders($headers)
        ->getJson(route('selector-options.index', ['resource' => 'suppliers', 'search' => 'Marca seletora']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Fornecedor por contato');

    $this->actingAs($owner)->withHeaders($headers)
        ->getJson(route('selector-options.index', ['resource' => 'suppliers', 'search' => 'seletor@example.test']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Fornecedor por contato');

    $this->actingAs($owner)->withHeaders($headers)
        ->getJson(route('selector-options.index', ['resource' => 'products', 'per_page' => 50]))
        ->assertSuccessful()
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonMissing(['name' => 'Produto inativo']);

    $this->actingAs($owner)->withHeaders($headers)
        ->getJson(route('selector-options.index', ['resource' => 'products', 'per_page' => 51]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('filters service and product selector options by category', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Selector '.Str::random(8),
        'slug' => 'selector-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $headers = ['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()];
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
        'name' => 'Categoria filtrada',
    ]);
    $otherCategory = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
        'name' => 'Outra categoria',
    ]);
    Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $category->getKey(),
        'name' => 'Serviço da categoria',
    ]);
    Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $otherCategory->getKey(),
        'name' => 'Serviço de outra categoria',
    ]);
    $this->actingAs($owner)->withHeaders($headers)
        ->getJson(route('selector-options.index', [
            'resource' => 'services',
            'category_id' => $category->getKey(),
        ]))
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Serviço da categoria');
});

it('returns paginated active inventory adjustment products scoped to the selected unit', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Inventory selector '.Str::random(8),
        'slug' => 'inventory-selector-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Outra unidade']);
    $headers = ['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()];

    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Produto ajustável',
        'current_stock' => 12,
        'min_stock' => 3,
        'unit_of_measure' => 'un',
        'lock_version' => 4,
        'cost_price_cents' => 2500,
        'is_active' => true,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Produto inativo',
        'is_active' => false,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'name' => 'Produto de outra unidade',
    ]);

    $this->actingAs($owner)->withHeaders($headers)
        ->getJson(route('selector-options.index', ['resource' => 'inventory-products', 'per_page' => 1]))
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('data.0.name', 'Produto ajustável')
        ->assertJsonPath('data.0.current_stock', 12)
        ->assertJsonPath('data.0.min_stock', 3)
        ->assertJsonPath('data.0.unit_of_measure', 'un')
        ->assertJsonPath('data.0.lock_version', 4)
        ->assertJsonPath('data.0.cost_price_cents', 2500);
});

it('authorizes selector resources through their viewAny policy', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Selector '.Str::random(8),
        'slug' => 'selector-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $reader = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $reader->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'customer-reader']);
    $permission = Permission::query()->where('key', 'customer.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $this->actingAs($reader)
        ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
        ->getJson(route('selector-options.index', ['resource' => 'customers']))
        ->assertSuccessful();

    $this->actingAs($reader)
        ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
        ->getJson(route('selector-options.index', ['resource' => 'professionals']))
        ->assertForbidden();
});

it('allows product managers to load inventory selector options', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Inventory selector '.Str::random(8),
        'slug' => 'inventory-selector-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $manager = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $manager->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'product-manager']);
    $permission = Permission::query()->where('key', 'product.manage')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Produto do gerente',
        'is_active' => true,
    ]);

    $this->actingAs($manager)
        ->withHeaders(['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()])
        ->getJson(route('selector-options.index', ['resource' => 'inventory-products']))
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Produto do gerente');
});
