<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function categoryTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('creates, updates, searches and inactivates categories with tenant-unit scoping', function () {
    [$owner, $tenant, $unit] = categoryTestWorkspace();

    $createResponse = $this->actingAs($owner)->post(route('categories.store'), [
        'name' => 'Tratamentos Capilares',
        'type' => 'service',
        'description' => 'Serviços de hidratação, nutrição e reconstrução.',
        'is_active' => true,
    ]);
    expect($createResponse->status())->toBe(302);

    $category = Category::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $createResponse->assertRedirect(route('categories.show', $category));
    expect($category->unit_id)->toBe($unit->getKey())
        ->and($category->name)->toBe('Tratamentos Capilares')
        ->and($category->type)->toBe('service')
        ->and($category->is_active)->toBeTrue()
        ->and($category->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'category.created')->where('resource_id', $category->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('categories.index', ['search' => 'Capilares']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/index')
            ->where('filters.search', 'Capilares')
            ->has('categories.data', 1)
            ->where('categories.data.0.id', $category->getKey()));

    $this->flushHeaders()->actingAs($owner)
        ->patch(route('categories.update', $category), [
            'name' => 'Tratamentos Capilares Premium',
            'type' => 'service',
            'description' => 'Linha especial.',
            'lock_version' => 1,
        ])
        ->assertRedirect(route('categories.show', $category));

    expect($category->fresh()->name)->toBe('Tratamentos Capilares Premium')
        ->and($category->fresh()->lock_version)->toBe(2)
        ->and(AuditEvent::query()->where('action', 'category.updated')->where('resource_id', $category->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('categories.destroy', $category), ['lock_version' => 2])
        ->assertRedirect(route('categories.index'));

    expect($category->fresh()->is_active)->toBeFalse()
        ->and($category->fresh()->lock_version)->toBe(3)
        ->and(AuditEvent::query()->where('action', 'category.deactivated')->where('resource_id', $category->getKey())->exists())->toBeTrue();
});

it('replays an idempotent category creation mutation', function () {
    [$owner, $tenant] = categoryTestWorkspace();
    $payload = [
        'name' => 'Cosméticos Naturais',
        'type' => 'product',
        'description' => 'Linha orgânica e vegana.',
    ];

    $first = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'category-create-1')
        ->post(route('categories.store'), $payload);
    $category = Category::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $second = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'category-create-1')
        ->post(route('categories.store'), $payload);

    $first->assertRedirect(route('categories.show', $category));
    $second->assertRedirect(route('categories.show', $category));
    expect(Category::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(IdempotencyKey::query()->where('tenant_id', $tenant->getKey())->where('key', 'category-create-1')->firstOrFail()->status->value)->toBe('succeeded');
});

it('rejects stale lock_version when updating categories', function () {
    [$owner, $tenant, $unit] = categoryTestWorkspace();
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'lock_version' => 1,
    ]);

    $this->actingAs($owner)
        ->patch(route('categories.update', $category), [
            'name' => 'Conflito',
            'type' => 'general',
            'lock_version' => 999,
        ])
        ->assertStatus(409);

    expect($category->fresh()->name)->not->toBe('Conflito')
        ->and($category->fresh()->lock_version)->toBe(1);
});

it('enforces RBAC permissions on categories', function () {
    [$owner, $tenant, $unit] = categoryTestWorkspace();
    $reader = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $reader->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'category-reader']);
    $permission = Permission::query()->where('key', 'category.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $this->actingAs($reader)
        ->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page->component('categories/index'));

    $this->actingAs($reader)
        ->post(route('categories.store'), [
            'name' => 'Blocked Category',
            'type' => 'general',
        ])
        ->assertForbidden();
});

it('isolates categories across tenants and units', function () {
    [$owner, $tenant, $unit] = categoryTestWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignUnit = Unit::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $foreignCategory = Category::factory()->create([
        'tenant_id' => $foreignTenant->getKey(),
        'unit_id' => $foreignUnit->getKey(),
    ]);

    $this->actingAs($owner)
        ->get(route('categories.show', $foreignCategory))
        ->assertForbidden();
});
