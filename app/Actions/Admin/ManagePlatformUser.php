<?php

namespace App\Actions\Admin;

use App\Enums\MembershipRoleScope;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditEventWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ManagePlatformUser
{
    public function __construct(private readonly AuditEventWriter $audit = new AuditEventWriter) {}

    /** @param array{name:string,email:string,role:string} $data */
    public function create(User $actor, Tenant $tenant, array $data): Membership
    {
        return DB::transaction(function () use ($actor, $tenant, $data): Membership {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(48),
                'must_change_password' => true,
                'temporary_password_expires_at' => now()->addDays(3),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $membership = Membership::query()->create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);
            $unit = $tenant->units()->where('status', 'active')->orderBy('id')->first();
            if ($unit !== null) {
                MembershipUnit::query()->create(['tenant_id' => $tenant->getKey(), 'membership_id' => $membership->getKey(), 'unit_id' => $unit->getKey(), 'is_primary' => true, 'created_at' => now()]);
            }
            $role = $tenant->roles()->where('key', $data['role'] === 'owner' ? 'owner' : 'manager')->first();
            if ($role === null && $data['role'] === 'staff') {
                $role = Role::query()->create(['tenant_id' => $tenant->getKey(), 'key' => 'manager', 'name' => 'Staff', 'description' => 'Platform assigned staff role', 'is_system' => false]);
            }
            if ($role !== null) {
                MembershipRole::query()->create(['tenant_id' => $tenant->getKey(), 'membership_id' => $membership->getKey(), 'role_id' => $role->getKey(), 'scope_kind' => MembershipRoleScope::Tenant, 'assignment_scope' => MembershipRoleScope::Tenant->value]);
            }
            $this->audit->record(['actor_user_id' => $actor->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.user.created', 'resource_type' => 'membership', 'resource_id' => $membership->getKey(), 'metadata' => ['key' => $data['role']]]);

            return $membership->fresh(['user', 'membershipRoles.role']);
        }, 5);
    }

    public function revoke(User $actor, Tenant $tenant, Membership $membership): Membership
    {
        abort_unless($membership->tenant_id === $tenant->getKey(), 404);

        return DB::transaction(function () use ($actor, $tenant, $membership): Membership {
            $membership->forceFill(['status' => MembershipStatus::Revoked, 'revoked_at' => now(), 'lock_version' => $membership->lock_version + 1])->save();
            MembershipRole::query()->where('tenant_id', $tenant->getKey())->where('membership_id', $membership->getKey())->whereNull('revoked_at')->update(['revoked_at' => now()]);
            MembershipUnit::query()->where('tenant_id', $tenant->getKey())->where('membership_id', $membership->getKey())->delete();
            $this->audit->record(['actor_user_id' => $actor->getKey(), 'tenant_id' => $tenant->getKey(), 'action' => 'platform.user.revoked', 'resource_type' => 'membership', 'resource_id' => $membership->getKey()]);

            return $membership->fresh();
        }, 5);
    }
}
