<?php

namespace App\Support;

use App\Enums\UnitStatus;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

final class AuthorizationService
{
    public function assertTenantManager(User $actor, TenantContext $context, Tenant $tenant): void
    {
        try {
            $freshContext = $context->revalidate();
        } catch (AuthorizationException $exception) {
            throw $exception;
        }

        if (
            $freshContext->membership->isNot($context->membership)
            || $context->user->isNot($actor)
            || $context->tenant->isNot($tenant)
        ) {
            throw new AuthorizationException('The authorization context does not match the requested tenant.');
        }

        if (! $this->can($actor, $freshContext, 'tenant.manage')) {
            throw new AuthorizationException('The actor is not allowed to manage this tenant.');
        }
    }

    public function assertTenantOwner(User $actor, TenantContext $context, Tenant $tenant): void
    {
        $this->assertTenantManager($actor, $context, $tenant);

        $isOwner = $context->membership->membershipRoles()
            ->whereNull('revoked_at')
            ->where('scope_kind', 'tenant')
            ->whereNull('unit_id')
            ->whereHas('role', fn ($query) => $query->where('key', 'owner')->where('is_system', true))
            ->exists();

        if (! $isOwner) {
            throw new AuthorizationException('An active system owner role is required for onboarding reconciliation.');
        }
    }

    public function assertMembershipManager(User $actor, TenantContext $context, Membership $membership): void
    {
        if ($membership->tenant_id !== $context->tenant->getKey()) {
            throw new AuthorizationException('The membership belongs to another tenant.');
        }

        $this->assertTenantManager($actor, $context, $context->tenant);
    }

    public function assertRoleManager(User $actor, TenantContext $context, Membership $membership, Role $role): void
    {
        if ($membership->tenant_id !== $context->tenant->getKey()) {
            throw new AuthorizationException('The membership belongs to another tenant.');
        }

        if ($role->tenant_id !== $context->tenant->getKey()) {
            throw new AuthorizationException('The role belongs to another tenant.');
        }

        if (! $this->can($actor, $context, 'role.manage')) {
            throw new AuthorizationException('The actor is not allowed to manage roles.');
        }
    }

    /**
     * Determine effective persisted permissions for the request context.
     *
     * @return list<string>
     */
    public function permissions(User $user, TenantContext $context, ?Unit $unit = null): array
    {
        $freshContext = TenantContext::forUser($user, $context->tenant->getKey());

        if ($freshContext->membership->getKey() !== $context->membership->getKey()) {
            throw new AuthorizationException('The authorization context does not match the actor.');
        }

        $targetUnit = $unit ?? $context->unit;

        if ($targetUnit !== null) {
            $targetUnit = Unit::query()
                ->whereKey($targetUnit->getKey())
                ->where('tenant_id', $freshContext->tenant->getKey())
                ->where('status', UnitStatus::Active)
                ->first();

            if ($targetUnit === null) {
                return [];
            }
        }
        $assignmentTable = (new MembershipRole)->getTable();
        $membershipUnitTable = (new MembershipUnit)->getTable();
        $permissionTable = (new Permission)->getTable();
        $rolePermissionTable = (new RolePermission)->getTable();

        /** @var Collection<int, object{key:string}> $permissions */
        $permissions = Permission::query()
            ->select($permissionTable.'.key')
            ->join($rolePermissionTable, $rolePermissionTable.'.permission_id', '=', $permissionTable.'.id')
            ->join($assignmentTable, $assignmentTable.'.role_id', '=', $rolePermissionTable.'.role_id')
            ->where($rolePermissionTable.'.tenant_id', $freshContext->tenant->getKey())
            ->where($assignmentTable.'.tenant_id', $freshContext->tenant->getKey())
            ->where($assignmentTable.'.membership_id', $freshContext->membership->getKey())
            ->whereNull($assignmentTable.'.revoked_at')
            ->where(function ($query) use ($assignmentTable, $membershipUnitTable, $targetUnit): void {
                $query->where($assignmentTable.'.scope_kind', 'tenant');

                if ($targetUnit !== null) {
                    $query->orWhere(function ($unitQuery) use ($assignmentTable, $membershipUnitTable, $targetUnit): void {
                        $unitQuery->where($assignmentTable.'.scope_kind', 'unit')
                            ->where($assignmentTable.'.unit_id', $targetUnit->getKey())
                            ->whereExists(function ($membershipUnitQuery) use ($membershipUnitTable, $assignmentTable): void {
                                $membershipUnitQuery->selectRaw('1')
                                    ->from($membershipUnitTable)
                                    ->whereColumn($membershipUnitTable.'.tenant_id', $assignmentTable.'.tenant_id')
                                    ->whereColumn($membershipUnitTable.'.membership_id', $assignmentTable.'.membership_id')
                                    ->whereColumn($membershipUnitTable.'.unit_id', $assignmentTable.'.unit_id');
                            });
                    });
                }
            })
            ->distinct()
            ->orderBy($permissionTable.'.key')
            ->get();

        return array_values($permissions->pluck('key')->map(static fn (mixed $key): string => (string) $key)->values()->all());
    }

    public function can(User $user, TenantContext $context, string $permissionKey, ?Unit $unit = null): bool
    {
        return in_array($permissionKey, $this->permissions($user, $context, $unit), true);
    }
}
