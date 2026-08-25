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
        'unit.view', 'unit.update', 'unit.manage',
        'membership.view', 'membership.invite', 'membership.activate', 'membership.revoke', 'membership.assign_role', 'membership.manage',
        'role.view', 'role.update', 'role.delete', 'role.manage',
        'entitlement.view', 'entitlement.manage',
        'audit.view',
        'customer.view', 'customer.manage',
        'professional.view', 'professional.manage',
        'service.view', 'service.manage',
        'category.view', 'category.manage',
        'product.view', 'product.manage',
        'supplier.view', 'supplier.manage',
        'calendar.view', 'calendar.manage', 'calendar.configure',
        'sale_category.view', 'sale_category.manage',
        'sale.view', 'sale.manage', 'sale.discount', 'sale.close',
        'cash_shift.view', 'cash_shift.open', 'cash_shift.move', 'cash_shift.close',
    ];

    /**
     * Return the stable platform permission catalog.
     *
     * The catalog is data, not schema. It is deliberately kept in PHP so it
     * can be reconciled by seeders and onboarding without running DDL.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return self::KEYS;
    }

    /**
     * Ensure every catalog permission exists and has its canonical description.
     *
     * @return list<string> Permission IDs in catalog order
     */
    public function ensureCatalog(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $catalog = $this->syncCatalog($now);

        return $catalog['ids'];
    }

    /** @return array{ids:list<string>,changed:bool} */
    private function syncCatalog(CarbonInterface $now): array
    {
        $changed = false;

        foreach (self::KEYS as $key) {
            $inserted = Permission::query()->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'key' => $key,
                'description' => 'Owner catalog permission: '.$key,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $changed = $changed || $inserted > 0;

            $updated = Permission::query()
                ->where('key', $key)
                ->where('description', '!=', 'Owner catalog permission: '.$key)
                ->update([
                    'description' => 'Owner catalog permission: '.$key,
                    'updated_at' => $now,
                ]);
            $changed = $changed || $updated > 0;
        }

        return [
            'ids' => array_values(Permission::query()
                ->whereIn('key', self::KEYS)
                ->orderBy('key')
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all()),
            'changed' => $changed,
        ];
    }

    public function ensure(Tenant $tenant, Role $ownerRole, ?CarbonInterface $now = null): bool
    {
        $now ??= now();

        $catalog = $this->syncCatalog($now);
        $permissionIds = $catalog['ids'];
        $changed = $catalog['changed'];

        $removed = RolePermission::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('role_id', $ownerRole->getKey())
            ->whereNotIn('permission_id', $permissionIds)
            ->delete();
        $changed = $changed || $removed > 0;

        foreach ($permissionIds as $permissionId) {
            $inserted = RolePermission::query()->insertOrIgnore([
                'tenant_id' => $tenant->getKey(),
                'role_id' => $ownerRole->getKey(),
                'permission_id' => $permissionId,
                'created_at' => $now,
            ]);
            $changed = $changed || $inserted > 0;
        }

        return $changed;
    }
}
