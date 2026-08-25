<?php

namespace App\Actions\Identity;

use App\Enums\MembershipRoleScope;
use App\Enums\MembershipStatus;
use App\Enums\UnitStatus;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class ActivateMembership
{
    public function __construct(
        private readonly AuthorizationService $authorization = new AuthorizationService,
        private readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
    ) {}

    public function handle(User $actor, TenantContext $context, Membership $membership): Membership
    {
        $this->authorization->assertMembershipManager($actor, $context, $membership);

        return DB::transaction(function () use ($actor, $context, $membership): Membership {
            $tenant = Tenant::query()->whereKey($membership->tenant_id)->lockForUpdate()->firstOrFail();
            $lockedMembership = Membership::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();

            if ($tenant->status->value !== 'active') {
                throw new \LogicException('Membership activation requires an active tenant.');
            }

            if ($lockedMembership->status !== MembershipStatus::Invited) {
                throw new \LogicException('Only invited memberships can be activated; suspension requires an explicit reactivation flow.');
            }

            $user = $lockedMembership->user()->firstOrFail();

            if ($user->email_verified_at === null) {
                throw new \LogicException('Membership activation requires a verified identity.');
            }

            $units = Unit::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $unitIds = $units->where('status', UnitStatus::Active)->pluck('id');
            $hasActiveUnit = MembershipUnit::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->where('membership_id', $lockedMembership->getKey())
                ->whereIn('unit_id', $unitIds)
                ->exists();

            $assignmentRoleIds = MembershipRole::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->where('membership_id', $lockedMembership->getKey())
                ->whereNull('revoked_at')
                ->orderBy('id')
                ->where('scope_kind', MembershipRoleScope::Tenant)
                ->pluck('role_id');
            $hasTenantWideRole = Role::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->whereIn('id', $assignmentRoleIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->first() !== null;

            MembershipRole::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->where('membership_id', $lockedMembership->getKey())
                ->whereNull('revoked_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (! $hasActiveUnit && ! $hasTenantWideRole) {
                throw new \LogicException('Membership activation requires an active unit or tenant-wide role.');
            }

            $lockedMembership->forceFill([
                'status' => MembershipStatus::Active,
                'joined_at' => $lockedMembership->joined_at ?? now(),
                'revoked_at' => null,
                'lock_version' => $lockedMembership->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'membership.activated', $lockedMembership, [
                'lock_version' => $lockedMembership->lock_version,
            ]);

            return $lockedMembership->fresh();
        }, 5);
    }
}
