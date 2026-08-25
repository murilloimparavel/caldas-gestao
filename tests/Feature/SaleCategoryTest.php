<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\IdempotencyKey;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\SaleCategory;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function saleCategoryTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('creates, updates, searches and inactivates sale categories with tenant-unit scoping', function () {
    [$owner, $tenant, $unit] = saleCategoryTestWorkspace();

    $createResponse = $this->actingAs($owner)->post(route('sale-categories.store'), [
        'name' => 'Barbearia Clássica',
        'key' => 'barbearia',
        'type' => 'service',
        'uniqueness_scope' => 'customer',
        'is_active' => true,
    ]);
    expect($createResponse->status())->toBe(302);

    $category = SaleCategory::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $createResponse->assertRedirect(route('sale-categories.show', $category));
    expect($category->unit_id)->toBe($unit->getKey())
        ->and($category->name)->toBe('Barbearia Clássica')
        ->and($category->key)->toBe('barbearia')
        ->and($category->type)->toBe('service')
        ->and($category->uniqueness_scope)->toBe('customer')
        ->and($category->is_active)->toBeTrue()
        ->and($category->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'sale_category.created')->where('resource_id', $category->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('sale-categories.index', ['search' => 'Barbearia']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sale-categories/index')
            ->where('filters.search', 'Barbearia')
            ->has('categories.data', 1)
            ->where('categories.data.0.id', $category->getKey()));

    $this->flushHeaders()->actingAs($owner)
        ->patch(route('sale-categories.update', $category), [
            'name' => 'Barbearia & Estética',
            'key' => 'barbearia-estetica',
            'type' => 'mixed',
            'uniqueness_scope' => 'customer',
            'lock_version' => 1,
        ])
        ->assertRedirect(route('sale-categories.show', $category));

    expect($category->fresh()->name)->toBe('Barbearia & Estética')
        ->and($category->fresh()->key)->toBe('barbearia-estetica')
        ->and($category->fresh()->type)->toBe('mixed')
        ->and($category->fresh()->lock_version)->toBe(2)
        ->and(AuditEvent::query()->where('action', 'sale_category.updated')->where('resource_id', $category->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('sale-categories.destroy', $category), ['lock_version' => 2])
        ->assertRedirect(route('sale-categories.index'));

    expect($category->fresh()->is_active)->toBeFalse()
        ->and($category->fresh()->lock_version)->toBe(3)
        ->and(AuditEvent::query()->where('action', 'sale_category.deactivated')->where('resource_id', $category->getKey())->exists())->toBeTrue();
});

it('replays an idempotent sale category creation mutation', function () {
    [$owner, $tenant] = saleCategoryTestWorkspace();
    $payload = [
        'name' => 'Loja de Cosméticos',
        'key' => 'loja',
        'type' => 'product',
        'uniqueness_scope' => 'none',
    ];

    $first = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'sale-cat-create-1')
        ->post(route('sale-categories.store'), $payload);
    $category = SaleCategory::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $second = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'sale-cat-create-1')
        ->post(route('sale-categories.store'), $payload);

    $first->assertRedirect(route('sale-categories.show', $category));
    $second->assertRedirect(route('sale-categories.show', $category));
    expect(SaleCategory::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(IdempotencyKey::query()->where('tenant_id', $tenant->getKey())->where('key', 'sale-cat-create-1')->firstOrFail()->status->value)->toBe('succeeded');
});

it('rejects stale lock_version when updating sale categories', function () {
    [$owner, $tenant, $unit] = saleCategoryTestWorkspace();
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'lock_version' => 1,
    ]);

    $this->actingAs($owner)
        ->patch(route('sale-categories.update', $category), [
            'name' => 'Conflito',
            'key' => 'conflito',
            'type' => 'mixed',
            'uniqueness_scope' => 'none',
            'lock_version' => 999,
        ])
        ->assertStatus(409);

    expect($category->fresh()->name)->not->toBe('Conflito')
        ->and($category->fresh()->lock_version)->toBe(1);
});

it('enforces RBAC permissions on sale categories', function () {
    [$owner, $tenant, $unit] = saleCategoryTestWorkspace();
    $reader = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $reader->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'sale-category-reader']);
    $permission = Permission::query()->where('key', 'sale_category.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $this->actingAs($reader)
        ->get(route('sale-categories.index'))
        ->assertInertia(fn (Assert $page) => $page->component('sale-categories/index'));

    $this->actingAs($reader)
        ->post(route('sale-categories.store'), [
            'name' => 'Blocked Category',
            'key' => 'blocked',
            'type' => 'service',
            'uniqueness_scope' => 'none',
        ])
        ->assertForbidden();
});

it('isolates sale categories across tenants and units', function () {
    [$owner, $tenant, $unit] = saleCategoryTestWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignUnit = Unit::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $foreignCategory = SaleCategory::factory()->create([
        'tenant_id' => $foreignTenant->getKey(),
        'unit_id' => $foreignUnit->getKey(),
    ]);

    $this->actingAs($owner)
        ->get(route('sale-categories.show', $foreignCategory))
        ->assertForbidden();
});
