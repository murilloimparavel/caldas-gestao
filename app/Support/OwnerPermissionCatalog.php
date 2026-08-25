<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class OwnerPermissionCatalog
{
    /** @var list<string> */
    private const KEYS = [
        'tenant.view', 'tenant.update', 'tenant.manage',
        'unit.view', 'unit.update',
        'membership.view', 'membership.revoke', 'membership.assign_role', 'membership.manage',
        'role.view', 'role.update', 'role.delete', 'role.manage',
        'entitlement.view', 'entitlement.manage',
    ];

    public function ensure(Tenant $tenant, Role $ownerRole, ?CarbonInterface $now = null): void
    {
        $now ??= now();

        foreach (self::KEYS as $key) {
            Permission::query()->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'key' => $key,
                'description' => 'Owner catalog permission: '.$key,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissions = Permission::query()->whereIn('key', self::KEYS)->get();

        foreach ($permissions as $permission) {
            RolePermission::query()->insertOrIgnore([
                'tenant_id' => $tenant->getKey(),
                'role_id' => $ownerRole->getKey(),
                'permission_id' => $permission->getKey(),
                'created_at' => $now,
            ]);
        }
    }
}
