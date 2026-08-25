<?php

namespace App\Actions\Identity;

use App\Enums\MembershipRoleScope;
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
use App\Support\OwnerPermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ResumeTenantOnboarding
{
    public function __construct(
        private readonly AuthorizationService $authorization = new AuthorizationService,
        private readonly OwnerPermissionCatalog $permissionCatalog = new OwnerPermissionCatalog,
        private readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
    ) {}

    /**
     * Reconcile the initial tenant aggregate for an already authorized owner.
     *
     * @param  array<string, mixed>  $unitData
     */
    public function handle(User $actor, TenantContext $context, Tenant $tenant, array $unitData = []): Tenant
    {
        $context = $context->revalidate();
        $this->authorization->assertTenantOwner($actor, $context, $tenant);

        return DB::transaction(function () use ($actor, $context, $tenant, $unitData): Tenant {
            $now = now();
            $lockedTenant = Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $membership = Membership::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('user_id', $actor->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->getKey() !== $context->membership->getKey()) {
                throw new AuthorizationException('The tenant owner context changed during reconciliation.');
            }

            $unitName = trim((string) ($unitData['name'] ?? $lockedTenant->name));
            $unitSlug = Str::slug((string) ($unitData['slug'] ?? $unitName));

            if ($unitName === '' || $unitSlug === '') {
                throw new \InvalidArgumentException('Initial unit name and slug are required.');
            }

            Unit::query()->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $lockedTenant->getKey(),
                'slug' => $unitSlug,
                'name' => $unitName,
                'status' => 'active',
                'timezone' => $unitData['timezone'] ?? null,
                'address' => $unitData['address'] ?? null,
                'lock_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $unit = Unit::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('slug', $unitSlug)
                ->lockForUpdate()
                ->firstOrFail();

            $ownerRole = Role::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('key', 'owner')
                ->where('is_system', true)
                ->lockForUpdate()
                ->first();

            if ($ownerRole === null) {
                throw new AuthorizationException('An existing tenant must have an active system owner role to resume onboarding.');
            }

            $ownerAssignment = MembershipRole::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('membership_id', $membership->getKey())
                ->where('role_id', $ownerRole->getKey())
                ->where('scope_kind', MembershipRoleScope::Tenant->value)
                ->whereNull('unit_id')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if ($ownerAssignment === null) {
                throw new AuthorizationException('An active owner assignment is required for onboarding reconciliation.');
            }

            $this->permissionCatalog->ensure($lockedTenant, $ownerRole, $now);

            $primaryMembershipUnit = MembershipUnit::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('membership_id', $membership->getKey())
                ->where('is_primary', true)
                ->orderBy('unit_id')
                ->lockForUpdate()
                ->first();

            MembershipUnit::query()->insertOrIgnore([
                'tenant_id' => $lockedTenant->getKey(),
                'membership_id' => $membership->getKey(),
                'unit_id' => $unit->getKey(),
                'is_primary' => $primaryMembershipUnit === null,
                'created_at' => $now,
            ]);

            $membershipUnit = MembershipUnit::query()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('membership_id', $membership->getKey())
                ->where('unit_id', $unit->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($primaryMembershipUnit === null && ! $membershipUnit->is_primary) {
                MembershipUnit::query()
                    ->where('tenant_id', $lockedTenant->getKey())
                    ->where('membership_id', $membership->getKey())
                    ->where('unit_id', $unit->getKey())
                    ->update(['is_primary' => true]);
            }

            MembershipRole::query()->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $lockedTenant->getKey(),
                'membership_id' => $membership->getKey(),
                'role_id' => $ownerRole->getKey(),
                'scope_kind' => MembershipRoleScope::Tenant->value,
                'assignment_scope' => MembershipRoleScope::Tenant->value,
                'unit_id' => null,
                'lock_version' => 0,
                'revoked_at' => null,
                'created_at' => $now,
            ]);

            $this->events->record($actor, $context, 'tenant.onboarding.resumed', $lockedTenant, [
                'unit_id' => $unit->getKey(),
            ]);

            return $lockedTenant->fresh();
        }, 5);
    }
}
