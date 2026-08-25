<?php

namespace App\Actions\Identity;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class RevokeMembership
{
    public function __construct(private readonly AuthorizationService $authorization = new AuthorizationService) {}

    public function handle(User $actor, TenantContext $context, Membership $membership): Membership
    {
        $this->authorization->assertMembershipManager($actor, $context, $membership);

        return DB::transaction(function () use ($membership): Membership {
            Tenant::query()->whereKey($membership->tenant_id)->lockForUpdate()->firstOrFail();
            $lockedMembership = Membership::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();

            $unitIds = MembershipUnit::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->where('membership_id', $lockedMembership->getKey())
                ->orderBy('unit_id')
                ->pluck('unit_id');

            Unit::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->whereIn('id', $unitIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            MembershipUnit::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->where('membership_id', $lockedMembership->getKey())
                ->orderBy('unit_id')
                ->lockForUpdate()
                ->get();

            MembershipUnit::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->where('membership_id', $lockedMembership->getKey())
                ->delete();

            MembershipRole::query()
                ->where('tenant_id', $lockedMembership->tenant_id)
                ->where('membership_id', $lockedMembership->getKey())
                ->whereNull('revoked_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (MembershipRole $role): void {
                    $role->forceFill(['revoked_at' => now(), 'lock_version' => $role->lock_version + 1])->save();
                });

            $lockedMembership->forceFill([
                'status' => MembershipStatus::Revoked,
                'revoked_at' => now(),
                'lock_version' => $lockedMembership->lock_version + 1,
            ])->save();

            return $lockedMembership->fresh();
        }, 5);
    }
}
