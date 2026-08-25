<?php

namespace App\Actions\Identity;

use App\Enums\MembershipRoleScope;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class AssignRole
{
    public function __construct(private readonly AuthorizationService $authorization = new AuthorizationService) {}

    public function handle(User $actor, TenantContext $context, Membership $membership, Role $role, MembershipRoleScope|string $scope = MembershipRoleScope::Tenant, ?Unit $unit = null): MembershipRole
    {
        $scope = $scope instanceof MembershipRoleScope ? $scope : MembershipRoleScope::from($scope);

        $this->authorization->assertRoleManager($actor, $context, $membership, $role);

        if ($role->is_system) {
            throw new \LogicException('System roles are assigned only by the platform onboarding flow.');
        }

        return DB::transaction(function () use ($membership, $role, $scope, $unit): MembershipRole {
            Tenant::query()->whereKey($membership->tenant_id)->lockForUpdate()->firstOrFail();
            $lockedMembership = Membership::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();
            $lockedUnit = null;

            if ($lockedMembership->status->value !== 'active') {
                throw new \LogicException('Roles can only be assigned to active memberships.');
            }

            if ($lockedMembership->tenant_id !== $role->tenant_id) {
                throw new \InvalidArgumentException('Membership and role must belong to the same tenant.');
            }

            if ($scope === MembershipRoleScope::Unit) {
                if ($unit === null || $unit->tenant_id !== $lockedMembership->tenant_id) {
                    throw new \InvalidArgumentException('A unit-scoped role requires a unit from the membership tenant.');
                }

                $lockedUnit = Unit::query()->whereKey($unit->getKey())->lockForUpdate()->firstOrFail();

                if ($lockedUnit->tenant_id !== $lockedMembership->tenant_id) {
                    throw new \InvalidArgumentException('Unit does not belong to the membership tenant.');
                }

                if ($lockedUnit->status->value !== 'active') {
                    throw new \InvalidArgumentException('Unit-scoped roles require an active unit.');
                }
            }

            $lockedRole = Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            $query = MembershipRole::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->where('membership_id', $lockedMembership->getKey())
                ->where('role_id', $lockedRole->getKey())
                ->where('scope_kind', $scope->value)
                ->whereNull('revoked_at');

            $query = $scope === MembershipRoleScope::Unit
                ? $query->where('unit_id', $lockedUnit->getKey())
                : $query->whereNull('unit_id');

            $assignment = $query->lockForUpdate()->first();

            if ($assignment !== null) {
                return $assignment;
            }

            return MembershipRole::query()->create([
                'tenant_id' => $lockedMembership->tenant_id,
                'membership_id' => $lockedMembership->getKey(),
                'role_id' => $lockedRole->getKey(),
                'scope_kind' => $scope->value,
                'assignment_scope' => $scope === MembershipRoleScope::Tenant
                    ? MembershipRoleScope::Tenant->value
                    : 'unit:'.$lockedUnit->getKey(),
                'unit_id' => $lockedUnit?->getKey(),
                'lock_version' => 0,
            ]);
        }, 5);
    }
}
