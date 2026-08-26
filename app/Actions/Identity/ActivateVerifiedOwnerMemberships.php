<?php

namespace App\Actions\Identity;

use App\Enums\MembershipRoleScope;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

final class ActivateVerifiedOwnerMemberships
{
    public function __construct(
        private readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
    ) {}

    public function handle(Verified $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        DB::transaction(function () use ($event): void {
            $memberships = Membership::query()
                ->where('user_id', $event->user->getKey())
                ->where('status', MembershipStatus::Invited->value)
                ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
                ->whereHas('membershipRoles', fn ($query) => $query
                    ->whereNull('revoked_at')
                    ->where('scope_kind', MembershipRoleScope::Tenant->value)
                    ->whereHas('role', fn ($roleQuery) => $roleQuery
                        ->where('key', 'owner')
                        ->where('is_system', true)))
                ->with('tenant')
                ->lockForUpdate()
                ->get();

            foreach ($memberships as $membership) {
                $membership->forceFill([
                    'status' => MembershipStatus::Active,
                    'joined_at' => $membership->joined_at ?? now(),
                    'revoked_at' => null,
                    'lock_version' => $membership->lock_version + 1,
                ])->save();

                $tenant = $membership->tenant;

                if ($tenant instanceof Tenant) {
                    $this->events->recordForTenant($event->user, $tenant, 'membership.activated', $membership, [
                        'lock_version' => $membership->lock_version,
                        'scope_kind' => MembershipRoleScope::Tenant->value,
                    ]);
                }
            }
        }, 5);
    }
}
