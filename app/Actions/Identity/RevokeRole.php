<?php

namespace App\Actions\Identity;

use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class RevokeRole
{
    public function __construct(private readonly AuthorizationService $authorization = new AuthorizationService) {}

    public function handle(User $actor, TenantContext $context, MembershipRole $assignment): MembershipRole
    {
        $membership = Membership::query()->whereKey($assignment->membership_id)->firstOrFail();
        $role = Role::query()->whereKey($assignment->role_id)->firstOrFail();
        $this->authorization->assertRoleManager($actor, $context, $membership, $role);

        if ($role->is_system) {
            throw new \LogicException('System roles are managed only by the platform onboarding flow.');
        }

        return DB::transaction(function () use ($assignment): MembershipRole {
            Tenant::query()->whereKey($assignment->tenant_id)->lockForUpdate()->firstOrFail();
            Membership::query()->whereKey($assignment->membership_id)->lockForUpdate()->firstOrFail();
            Role::query()->whereKey($assignment->role_id)->lockForUpdate()->firstOrFail();
            $lockedAssignment = MembershipRole::query()->whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAssignment->revoked_at === null) {
                $lockedAssignment->forceFill([
                    'revoked_at' => now(),
                    'lock_version' => $lockedAssignment->lock_version + 1,
                ])->save();
            }

            return $lockedAssignment->fresh();
        }, 5);
    }
}
