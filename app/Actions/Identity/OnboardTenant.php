<?php

namespace App\Actions\Identity;

use App\Enums\MembershipRoleScope;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\OwnerPermissionCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OnboardTenant
{
    public function __construct(
        private readonly OwnerPermissionCatalog $permissionCatalog = new OwnerPermissionCatalog,
        private readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
    ) {}

    /**
     * Create a new tenant, its first unit, owner role and owner membership.
     *
     * Both argument orders are accepted to keep the action convenient for
     * console/bootstrap callers: handle($owner, $tenantData, $unitData) and
     * handle($tenantData, $owner, $unitData).
     *
     * @param  User|array<string, mixed>  $ownerOrTenant
     * @param  User|array<string, mixed>  $tenantOrOwner
     * @param  array<string, mixed>  $unitData
     */
    public function handle(User|array $ownerOrTenant, User|array $tenantOrOwner, array $unitData = []): Tenant
    {
        if ($ownerOrTenant instanceof User) {
            $owner = $ownerOrTenant;
            $tenantData = $tenantOrOwner;
        } else {
            $tenantData = $ownerOrTenant;
            $owner = $tenantOrOwner;
        }

        if (! is_array($tenantData) || ! $owner instanceof User) {
            throw new \InvalidArgumentException('Onboarding requires an owner and tenant data.');
        }

        $name = trim((string) ($tenantData['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('Tenant name is required.');
        }

        $slug = Str::slug((string) ($tenantData['slug'] ?? $name));

        if ($slug === '') {
            throw new \InvalidArgumentException('Tenant slug is required.');
        }

        if (Tenant::query()->where('slug', $slug)->exists()) {
            throw new \LogicException('The tenant slug already exists; use ResumeTenantOnboarding for reconciliation.');
        }

        try {
            $tenant = DB::transaction(function () use ($owner, $tenantData, $unitData, $name, $slug): Tenant {
                $now = now();
                $tenantId = (string) Str::uuid7();

                Tenant::query()->insert([
                    'id' => $tenantId,
                    'slug' => $slug,
                    'name' => $name,
                    'legal_name' => $tenantData['legal_name'] ?? null,
                    'status' => 'active',
                    'timezone' => $tenantData['timezone'] ?? 'UTC',
                    'default_currency' => $tenantData['default_currency'] ?? 'BRL',
                    'lock_version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $tenant = Tenant::query()->where('slug', $slug)->lockForUpdate()->firstOrFail();

                $membershipId = (string) Str::uuid7();

                Membership::query()->insertOrIgnore([
                    'id' => $membershipId,
                    'tenant_id' => $tenant->getKey(),
                    'user_id' => $owner->getKey(),
                    'status' => MembershipStatus::Invited->value,
                    'joined_at' => null,
                    'revoked_at' => null,
                    'lock_version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $membership = Membership::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->where('user_id', $owner->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $unitName = trim((string) ($unitData['name'] ?? $name));
                $unitSlug = Str::slug((string) ($unitData['slug'] ?? $unitName));

                if ($unitName === '' || $unitSlug === '') {
                    throw new \InvalidArgumentException('Initial unit name and slug are required.');
                }

                $unitId = (string) Str::uuid7();
                Unit::query()->insertOrIgnore([
                    'id' => $unitId,
                    'tenant_id' => $tenant->getKey(),
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
                    ->where('tenant_id', $tenant->getKey())
                    ->where('slug', $unitSlug)
                    ->lockForUpdate()
                    ->firstOrFail();

                $ownerRoleId = (string) Str::uuid7();
                Role::query()->insertOrIgnore([
                    'id' => $ownerRoleId,
                    'tenant_id' => $tenant->getKey(),
                    'key' => 'owner',
                    'name' => 'Owner',
                    'description' => 'Tenant owner',
                    'is_system' => true,
                    'lock_version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $ownerRole = Role::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->where('key', 'owner')
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $ownerRole->is_system) {
                    throw new \LogicException('The owner role is reserved for the system catalog.');
                }

                $this->permissionCatalog->ensure($tenant, $ownerRole, $now);

                MembershipUnit::query()->insertOrIgnore([
                    'tenant_id' => $tenant->getKey(),
                    'membership_id' => $membership->getKey(),
                    'unit_id' => $unit->getKey(),
                    'is_primary' => true,
                    'created_at' => $now,
                ]);
                MembershipUnit::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->where('membership_id', $membership->getKey())
                    ->where('unit_id', $unit->getKey())
                    ->update(['is_primary' => true]);

                MembershipRole::query()->insertOrIgnore([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenant->getKey(),
                    'membership_id' => $membership->getKey(),
                    'role_id' => $ownerRole->getKey(),
                    'scope_kind' => MembershipRoleScope::Tenant->value,
                    'assignment_scope' => MembershipRoleScope::Tenant->value,
                    'unit_id' => null,
                    'lock_version' => 0,
                    'revoked_at' => null,
                    'created_at' => $now,
                ]);

                if (
                    $owner->email_verified_at !== null
                    && $membership->status !== MembershipStatus::Active
                    && $membership->status !== MembershipStatus::Revoked
                ) {
                    $membership->forceFill([
                        'status' => MembershipStatus::Active,
                        'joined_at' => $membership->joined_at ?? now(),
                        'revoked_at' => null,
                        'lock_version' => $membership->lock_version + 1,
                    ])->save();
                }

                $this->events->recordForTenant($owner, $tenant, 'tenant.created', $tenant, [
                    'unit_id' => $unit->getKey(),
                ], $unit->getKey());

                return $tenant->fresh();
            }, 5);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['19', '23000', '23505'], true)) {
                throw new \LogicException('The tenant slug was claimed concurrently; retry with a new slug.', 0, $exception);
            }

            throw $exception;
        }

        return $tenant;
    }
}
