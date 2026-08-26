<?php

use App\Actions\Identity\OnboardTenant;
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

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function navTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

/** @return array{0: User, 1: Tenant, 2: Unit} */
function navUserWithRole(string $roleKey, array $permissionKeys = []): array
{
    [$owner, $tenant, $unit] = navTestWorkspace();

    $user = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $user->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);

    $role = Role::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'key' => $roleKey.'-'.Str::random(6),
        'name' => ucfirst($roleKey),
    ]);

    foreach ($permissionKeys as $permKey) {
        $permission = Permission::query()->where('key', $permKey)->first();
        if ($permission) {
            RolePermission::query()->create([
                'tenant_id' => $tenant->getKey(),
                'role_id' => $role->getKey(),
                'permission_id' => $permission->getKey(),
            ]);
        }
    }

    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    return [$user, $tenant, $unit];
}

it('allows owner role to view all navigation permissions and access all protected routes', function () {
    [$owner, $tenant, $unit] = navTestWorkspace();

    $response = $this->actingAs($owner)
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auth.permissions')
            ->where('auth.permissions', function ($permissions) {
                $array = is_array($permissions) ? $permissions : $permissions->toArray();

                return in_array('financial.view', $array, true)
                    && in_array('commission.view', $array, true)
                    && in_array('cash_shift.view', $array, true)
                    && in_array('unit.view', $array, true)
                    && in_array('customer.view', $array, true)
                    && in_array('inventory.view', $array, true);
            })
        );

    // Protected route accesses for owner
    $this->actingAs($owner)->get(route('finance.dashboard'))->assertOk();
    $this->actingAs($owner)->get(route('financial_obligations.index'))->assertOk();
    $this->actingAs($owner)->get(route('commissions.index'))->assertOk();
    $this->actingAs($owner)->get(route('cash_shifts.index'))->assertOk();
    $this->actingAs($owner)->get(route('online_booking.index'))->assertOk();
});

it('allows manager role with management permissions to access financial and configuration routes', function () {
    [$manager, $tenant, $unit] = navUserWithRole('manager', [
        'financial.view',
        'commission.view',
        'cash_shift.view',
        'unit.view',
        'customer.view',
        'professional.view',
        'calendar.view',
        'sale.view',
    ]);

    $response = $this->actingAs($manager)
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', function ($permissions) {
                $array = is_array($permissions) ? $permissions : $permissions->toArray();

                return in_array('financial.view', $array, true)
                    && in_array('commission.view', $array, true)
                    && in_array('cash_shift.view', $array, true)
                    && in_array('unit.view', $array, true);
            })
        );

    $this->actingAs($manager)->get(route('finance.dashboard'))->assertOk();
    $this->actingAs($manager)->get(route('financial_obligations.index'))->assertOk();
    $this->actingAs($manager)->get(route('commissions.index'))->assertOk();
    $this->actingAs($manager)->get(route('online_booking.index'))->assertOk();
});

it('restricts professional role navigation permissions and blocks restricted financial routes', function () {
    [$professional, $tenant, $unit] = navUserWithRole('professional', [
        'calendar.view',
        'customer.view',
        'sale.view',
    ]);

    $response = $this->actingAs($professional)
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', function ($permissions) {
                $array = is_array($permissions) ? $permissions : $permissions->toArray();

                return in_array('calendar.view', $array, true)
                    && in_array('customer.view', $array, true)
                    && in_array('sale.view', $array, true)
                    && ! in_array('financial.view', $array, true)
                    && ! in_array('commission.view', $array, true)
                    && ! in_array('cash_shift.view', $array, true)
                    && ! in_array('unit.view', $array, true);
            })
        );

    // Blocked routes
    $this->actingAs($professional)->get(route('finance.dashboard'))->assertForbidden();
    $this->actingAs($professional)->get(route('financial_obligations.index'))->assertForbidden();
    $this->actingAs($professional)->get(route('commissions.index'))->assertForbidden();
    $this->actingAs($professional)->get(route('cash_shifts.index'))->assertForbidden();
    $this->actingAs($professional)->get(route('online_booking.index'))->assertForbidden();

    // Allowed route
    $this->actingAs($professional)->get(route('calendar.index'))->assertOk();
});

it('restricts receptionist role navigation permissions and blocks sensitive financial routes', function () {
    [$receptionist, $tenant, $unit] = navUserWithRole('receptionist', [
        'calendar.view',
        'customer.view',
        'sale.view',
        'cash_shift.view',
    ]);

    $response = $this->actingAs($receptionist)
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', function ($permissions) {
                $array = is_array($permissions) ? $permissions : $permissions->toArray();

                return in_array('calendar.view', $array, true)
                    && in_array('customer.view', $array, true)
                    && in_array('sale.view', $array, true)
                    && in_array('cash_shift.view', $array, true)
                    && ! in_array('financial.view', $array, true)
                    && ! in_array('commission.view', $array, true)
                    && ! in_array('unit.view', $array, true);
            })
        );

    // Blocked routes
    $this->actingAs($receptionist)->get(route('finance.dashboard'))->assertForbidden();
    $this->actingAs($receptionist)->get(route('financial_obligations.index'))->assertForbidden();
    $this->actingAs($receptionist)->get(route('commissions.index'))->assertForbidden();
    $this->actingAs($receptionist)->get(route('online_booking.index'))->assertForbidden();

    // Allowed routes for receptionist
    $this->actingAs($receptionist)->get(route('cash_shifts.index'))->assertOk();
    $this->actingAs($receptionist)->get(route('calendar.index'))->assertOk();
});

it('verifies Inertia shared props structure for navigation frontend', function () {
    [$owner, $tenant, $unit] = navTestWorkspace();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auth.user', fn ($user) => $user
                ->has('id')
                ->has('name')
                ->has('email')
                ->etc()
            )
            ->has('auth.permissions')
            ->has('workspace', fn ($workspace) => $workspace
                ->has('tenant', fn ($tenantProp) => $tenantProp
                    ->where('id', (string) $tenant->getKey())
                    ->where('name', $tenant->name)
                    ->etc()
                )
                ->has('activeUnit', fn ($unitProp) => $unitProp
                    ->where('id', (string) $unit->getKey())
                    ->where('name', $unit->name)
                    ->etc()
                )
                ->has('availableUnits')
            )
        );
});
