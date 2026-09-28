<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function productTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('creates, updates, searches and inactivates physical products', function () {
    [$owner, $tenant, $unit] = productTestWorkspace();

    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Finalizadores',
        'type' => 'product',
    ]);

    $createResponse = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Pomada Modeladora Efeito Seco',
        'category_id' => $category->getKey(),
        'sku' => 'POM-SEC-01',
        'barcode' => '7891234567890',
        'cost_price_cents' => 2500,
        'sale_price_cents' => 6500,
        'unit_of_measure' => 'un',
        'min_stock' => 5,
        'current_stock' => 20,
        'is_active' => true,
    ]);
    expect($createResponse->status())->toBe(302);

    $product = Product::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $createResponse->assertRedirect(route('products.show', $product));
    expect($product->unit_id)->toBe($unit->getKey())
        ->and($product->category_id)->toBe($category->getKey())
        ->and($product->name)->toBe('Pomada Modeladora Efeito Seco')
        ->and($product->sku)->toBe('POM-SEC-01')
        ->and($product->sale_price_cents)->toBe(6500)
        ->and($product->cost_price_cents)->toBe(2500)
        ->and($product->current_stock)->toBe(20)
        ->and($product->min_stock)->toBe(5)
        ->and($product->is_active)->toBeTrue()
        ->and($product->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'product.created')->where('resource_id', $product->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('products.index', ['search' => 'POM-SEC']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->where('filters.search', 'POM-SEC')
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->getKey()));

    $this->flushHeaders()->actingAs($owner)
        ->patch(route('products.update', $product), [
            'name' => 'Pomada Modeladora Premium Efeito Seco',
            'category_id' => $category->getKey(),
            'sku' => 'POM-SEC-01',
            'barcode' => '7891234567890',
            'cost_price_cents' => 2800,
            'sale_price_cents' => 7500,
            'unit_of_measure' => 'un',
            'min_stock' => 10,
            'current_stock' => 30,
            'lock_version' => 1,
        ])
        ->assertRedirect(route('products.show', $product));

    expect($product->fresh()->name)->toBe('Pomada Modeladora Premium Efeito Seco')
        ->and($product->fresh()->sale_price_cents)->toBe(7500)
        ->and($product->fresh()->lock_version)->toBe(2)
        ->and(AuditEvent::query()->where('action', 'product.updated')->where('resource_id', $product->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('products.destroy', $product), ['lock_version' => 2])
        ->assertRedirect(route('products.index'));

    expect($product->fresh()->is_active)->toBeFalse()
        ->and($product->fresh()->lock_version)->toBe(3)
        ->and(AuditEvent::query()->where('action', 'product.deactivated')->where('resource_id', $product->getKey())->exists())->toBeTrue();
});

it('summarizes current inventory value by category for the active unit', function () {
    [$owner, $tenant, $unit] = productTestWorkspace();
    $clothing = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Roupas',
        'type' => 'product',
    ]);
    $perfume = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Perfumes',
        'type' => 'product',
    ]);

    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $clothing->getKey(),
        'current_stock' => 2,
        'cost_price_cents' => 100,
        'sale_price_cents' => 250,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $clothing->getKey(),
        'current_stock' => 3,
        'cost_price_cents' => 300,
        'sale_price_cents' => 500,
        'is_active' => false,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $perfume->getKey(),
        'current_stock' => 4,
        'cost_price_cents' => 250,
        'sale_price_cents' => 600,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 1,
        'cost_price_cents' => 1000,
        'sale_price_cents' => 2000,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 0,
        'cost_price_cents' => 9999,
        'sale_price_cents' => 9999,
    ]);
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'current_stock' => 100,
        'cost_price_cents' => 9999,
        'sale_price_cents' => 9999,
    ]);

    $this->actingAs($owner)
        ->get(route('products.index', ['search' => 'does-not-match', 'status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->missing('inventorySummary.total_units')
            ->where('inventorySummary.total_cost_cents', 3100)
            ->where('inventorySummary.total_sale_cents', 6400)
            ->has('inventorySummary.categories', 3)
            ->missing('inventorySummary.categories.0.units')
            ->where('inventorySummary.categories.0.name', 'Perfumes')
            ->where('inventorySummary.categories.0.cost_value_cents', 1000)
            ->where('inventorySummary.categories.0.sale_value_cents', 2400)
            ->where('inventorySummary.categories.1.name', 'Roupas')
            ->where('inventorySummary.categories.1.cost_value_cents', 1100)
            ->where('inventorySummary.categories.1.sale_value_cents', 2000)
            ->where('inventorySummary.categories.2.name', 'Sem categoria')
            ->where('inventorySummary.categories.2.cost_value_cents', 1000)
            ->where('inventorySummary.categories.2.sale_value_cents', 2000));
});

it('rejects categories from another unit during product creation', function () {
    [$owner, $tenant, $unit] = productTestWorkspace();
    $secondUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $foreignCategory = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $secondUnit->getKey(),
    ]);

    $this->actingAs($owner)
        ->postJson(route('products.store'), [
            'name' => 'Invalid Category Product',
            'category_id' => $foreignCategory->getKey(),
            'cost_price_cents' => 1000,
            'sale_price_cents' => 2000,
            'unit_of_measure' => 'un',
            'min_stock' => 1,
            'current_stock' => 5,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);

    expect(Product::query()->where('name', 'Invalid Category Product')->exists())->toBeFalse();
});

it('replays an idempotent product creation mutation', function () {
    [$owner, $tenant] = productTestWorkspace();
    $payload = [
        'name' => 'Shampoo Anticaspa 300ml',
        'cost_price_cents' => 1500,
        'sale_price_cents' => 3500,
        'unit_of_measure' => 'un',
        'min_stock' => 3,
        'current_stock' => 10,
    ];

    $first = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'product-create-1')
        ->post(route('products.store'), $payload);
    $product = Product::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $second = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'product-create-1')
        ->post(route('products.store'), $payload);

    $first->assertRedirect(route('products.show', $product));
    $second->assertRedirect(route('products.show', $product));
    expect(Product::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(IdempotencyKey::query()->where('tenant_id', $tenant->getKey())->where('key', 'product-create-1')->firstOrFail()->status->value)->toBe('succeeded');
});

it('rejects stale lock_version when updating products', function () {
    [$owner, $tenant, $unit] = productTestWorkspace();
    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'lock_version' => 1,
    ]);

    $this->actingAs($owner)
        ->patch(route('products.update', $product), [
            'name' => 'Conflito',
            'cost_price_cents' => 1000,
            'sale_price_cents' => 2000,
            'unit_of_measure' => 'un',
            'min_stock' => 1,
            'current_stock' => 5,
            'lock_version' => 999,
        ])
        ->assertStatus(409);

    expect($product->fresh()->name)->not->toBe('Conflito')
        ->and($product->fresh()->lock_version)->toBe(1);
});

it('returns a contextual validation error when inactivating a stale product', function () {
    [$owner, $tenant, $unit] = productTestWorkspace();
    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Produto alterado em outra tela',
        'lock_version' => 2,
    ]);

    $this->actingAs($owner)
        ->deleteJson(route('products.destroy', $product), ['lock_version' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'lock_version' => 'Este produto foi alterado em outra tela. Recarregue os dados antes de inativá-lo.',
        ]);

    expect($product->fresh()->is_active)->toBeTrue();
});

it('enforces RBAC permissions on products', function () {
    [$owner, $tenant, $unit] = productTestWorkspace();
    $reader = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $reader->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'product-reader']);
    $permission = Permission::query()->where('key', 'product.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $this->actingAs($reader)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page->component('products/index'));

    $this->actingAs($reader)
        ->post(route('products.store'), [
            'name' => 'Blocked Product',
            'cost_price_cents' => 1000,
            'sale_price_cents' => 2000,
            'unit_of_measure' => 'un',
            'min_stock' => 1,
            'current_stock' => 5,
        ])
        ->assertForbidden();
});

it('isolates products across foreign tenants and units', function () {
    [$owner, $tenant, $unit] = productTestWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignUnit = Unit::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $foreignProduct = Product::factory()->create([
        'tenant_id' => $foreignTenant->getKey(),
        'unit_id' => $foreignUnit->getKey(),
    ]);

    $this->actingAs($owner)
        ->get(route('products.show', $foreignProduct))
        ->assertForbidden();
});
