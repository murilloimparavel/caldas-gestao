<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Support\OwnerPermissionCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reconcile-owner-permissions
    {--tenant= : Reconcile only the given tenant UUID}')]
#[Description('Reconcile the owner permission catalog for existing tenants')]
final class ReconcileOwnerPermissions extends Command
{
    public function handle(OwnerPermissionCatalog $catalog): int
    {
        $tenantId = trim((string) ($this->option('tenant') ?? ''));
        $roles = Role::query()
            ->with('tenant')
            ->where('key', 'owner')
            ->where('is_system', true)
            ->when($tenantId !== '', fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('tenant_id')
            ->get();
        $changed = 0;

        foreach ($roles as $role) {
            if ($role->tenant !== null && $catalog->ensure($role->tenant, $role)) {
                $changed++;
            }
        }

        $this->components->info("Reconciled {$roles->count()} owner role(s); {$changed} changed.");

        return self::SUCCESS;
    }
}
