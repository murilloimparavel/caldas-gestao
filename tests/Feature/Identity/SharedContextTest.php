<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Identity\RevokeMembership;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

it('normalizes request and correlation identifiers to UUIDv7 when invalid', function () {
    $owner = User::factory()->create();
    (new OnboardTenant)->handle($owner, ['name' => 'Identifier Workspace']);
    $validCorrelation = (string) Str::ulid();

    $response = $this->actingAs($owner)
        ->withHeader('X-Request-Id', 'invalid-request-id')
        ->withHeader('X-Correlation-Id', $validCorrelation)
        ->get(route('dashboard'));

    $props = $response->viewData('page')['props'];

    expect(Str::isUuid($props['requestId'], 7))->toBeTrue()
        ->and($props['correlationId'])->toBe($validCorrelation);
});

it('shares the canonical workspace contract on a real dashboard request', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Workspace Contract']);

    $response = $this->actingAs($owner)->get(route('dashboard'));

    $response->assertSuccessful();
    $props = $response->viewData('page')['props'];

    expect($props['schemaVersion'])->toBe(1)
        ->and($props['requestId'])->toBeString()->not->toBeEmpty()
        ->and($props['correlationId'])->toBeString()->not->toBeEmpty()
        ->and($props['auth']['user']['id'])->toBe($owner->getKey())
        ->and($props['auth']['permissions'])->toContain('tenant.manage')
        ->and($props['auth']['entitlements'])->toBe([])
        ->and($props['workspace']['tenant']['id'])->toBe($tenant->getKey())
        ->and($props['workspace']['activeUnit']['id'])->toBeString()
        ->and($props['workspace']['availableUnits'])->toHaveCount(1)
        ->and($props['ui']['sidebarOpen'])->toBeTrue();
});

it('rejects explicit cross-tenant and revoked dashboard contexts', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Owned Workspace']);
    $foreignTenant = Tenant::factory()->create();

    $this->actingAs($owner)
        ->withHeader('X-Tenant-Id', $foreignTenant->getKey())
        ->get(route('dashboard'))
        ->assertForbidden();

    $membership = Membership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $owner->getKey())
        ->firstOrFail();
    (new RevokeMembership)->handle($owner, TenantContext::forUser($owner, $tenant->getKey()), $membership);

    $this->actingAs($owner)
        ->withHeader('X-Tenant-Id', $tenant->getKey())
        ->get(route('dashboard'))
        ->assertForbidden();

});

it('resolves a selected active unit from a real dashboard request', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Unit Workspace']);
    $membership = Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $owner->getKey())->firstOrFail();
    $unit = Unit::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Second Unit']);
    MembershipUnit::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $membership->getKey(),
        'unit_id' => $unit->getKey(),
        'is_primary' => false,
    ]);

    $response = $this->actingAs($owner)
        ->withHeader('X-Tenant-Id', $tenant->getKey())
        ->withHeader('X-Unit-Id', $unit->getKey())
        ->get(route('dashboard'));

    $response->assertSuccessful();
    expect($response->viewData('page')['props']['workspace']['activeUnit']['id'])->toBe($unit->getKey());
});

it('evaluates persisted tenant and unit scoped permissions', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Permission Workspace']);
    $target = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $target->getKey(),
        'status' => 'active',
    ]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Unlinked Unit']);
    $foreignUnit = Unit::factory()->create(['tenant_id' => Tenant::factory()->create()->getKey()]);
    MembershipUnit::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $membership->getKey(),
        'unit_id' => $unit->getKey(),
        'is_primary' => true,
    ]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'unit-reader']);
    $permission = Permission::factory()->create(['key' => 'unit.custom']);
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
        'created_at' => now(),
    ]);
    MembershipRole::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $membership->getKey(),
        'role_id' => $role->getKey(),
        'scope_kind' => 'unit',
        'assignment_scope' => 'unit:'.$unit->getKey(),
        'unit_id' => $unit->getKey(),
        'lock_version' => 0,
        'revoked_at' => null,
        'created_at' => now(),
    ]);

    $context = TenantContext::forUser($target, $tenant->getKey(), $unit->getKey());
    $authorization = app(AuthorizationService::class);

    expect($authorization->can($target, $context, 'unit.custom'))->toBeTrue()
        ->and($authorization->can($target, $context, 'unit.custom', $otherUnit))->toBeFalse()
        ->and($authorization->can($target, $context, 'tenant.manage'))->toBeFalse()
        ->and($authorization->can($owner, TenantContext::forUser($owner, $tenant->getKey()), 'tenant.manage', $otherUnit))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $otherUnit))->toBeTrue()
        ->and($authorization->can($owner, TenantContext::forUser($owner, $tenant->getKey()), 'tenant.manage', $foreignUnit))->toBeFalse();

    $membership->membershipRoles()->where('role_id', $role->getKey())->update(['revoked_at' => now()]);
    expect($authorization->can($target, $context, 'unit.custom'))->toBeFalse();
});

it('does not let another user resume or recreate an existing tenant slug', function () {
    $owner = User::factory()->create();
    (new OnboardTenant)->handle($owner, ['name' => 'Protected Slug', 'slug' => 'protected-slug']);
    $other = User::factory()->create();

    expect(fn () => (new OnboardTenant)->handle($other, ['name' => 'Protected Slug', 'slug' => 'protected-slug']))
        ->toThrow(LogicException::class);
});
