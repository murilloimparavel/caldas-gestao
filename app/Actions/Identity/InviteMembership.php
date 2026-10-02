<?php

namespace App\Actions\Identity;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MembershipInvitation;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class InviteMembership
{
    public function __construct(
        private readonly AuthorizationService $authorization = new AuthorizationService,
        private readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
    ) {}

    public function handle(User $actor, TenantContext $context, Tenant $tenant, User $user): Membership
    {
        $this->authorization->assertTenantManager($actor, $context, $tenant);

        return DB::transaction(function () use ($actor, $context, $tenant, $user): Membership {
            $tenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $membership = Membership::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                $membership = Membership::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    'user_id' => $user->getKey(),
                    'status' => MembershipStatus::Invited,
                ]);

                $this->events->record($actor, $context, 'membership.invited', $membership);

                $user->notify(new MembershipInvitation($tenant->name, route('login')));

                return $membership;
            }

            if ($membership->status === MembershipStatus::Revoked) {
                $membership->forceFill([
                    'status' => MembershipStatus::Invited,
                    'revoked_at' => null,
                    'joined_at' => null,
                    'lock_version' => $membership->lock_version + 1,
                ])->save();
                $this->events->record($actor, $context, 'membership.reinvited', $membership);
                $user->notify(new MembershipInvitation($tenant->name, route('login')));
            }

            return $membership->fresh();
        }, 5);
    }
}
