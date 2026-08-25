<?php

namespace App\Support;

use App\Enums\MembershipStatus;
use App\Enums\UnitStatus;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

/**
 * Immutable request/job authorization context.
 */
final readonly class TenantContext
{
    public function __construct(
        public User $user,
        public Tenant $tenant,
        public Membership $membership,
        public ?Unit $unit = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Authentication is required before resolving tenant context.');
        }

        $tenantId = self::stringIdentifier(
            $request->session()->get('tenant_id')
                ?? $request->header('X-Tenant-Id')
                ?? $request->route('tenant'),
        );
        $unitId = self::stringIdentifier(
            $request->session()->get('unit_id')
                ?? $request->header('X-Unit-Id')
                ?? $request->route('unit'),
        );

        return self::forUser($user, $tenantId, $unitId);
    }

    public static function forUser(User $user, ?string $tenantId = null, ?string $unitId = null): self
    {
        $memberships = Membership::query()
            ->with(['tenant', 'membershipUnits.unit'])
            ->where('user_id', $user->getKey())
            ->where('status', MembershipStatus::Active)
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->get();

        if ($tenantId === null) {
            if ($memberships->count() !== 1) {
                throw new AuthorizationException('An explicit active tenant context is required.');
            }

            $membership = $memberships->firstOrFail();
        } else {
            $membership = $memberships->firstWhere('tenant_id', $tenantId);

            if ($membership === null) {
                throw new AuthorizationException('The user has no active membership in this tenant.');
            }
        }

        $tenant = $membership->tenant;
        $activeUnits = $membership->membershipUnits
            ->filter(fn ($membershipUnit): bool => $membershipUnit->unit?->status === UnitStatus::Active);

        $unit = null;

        if ($unitId !== null) {
            $unit = $activeUnits->firstWhere('unit_id', $unitId)?->unit;

            if ($unit === null) {
                throw new AuthorizationException('The selected unit is not active for this membership.');
            }
        } else {
            $primary = $activeUnits->first(fn (MembershipUnit $membershipUnit): bool => $membershipUnit->is_primary);
            $unit = $primary instanceof MembershipUnit ? $primary->unit : null;

            if ($unit === null && $activeUnits->count() === 1) {
                $only = $activeUnits->first();
                $unit = $only instanceof MembershipUnit ? $only->unit : null;
            }
        }

        return new self($user, $tenant, $membership, $unit);
    }

    public static function forMembership(User $user, string $membershipId, ?string $unitId = null): self
    {
        $membership = Membership::query()
            ->whereKey($membershipId)
            ->where('user_id', $user->getKey())
            ->where('status', MembershipStatus::Active)
            ->first();

        if ($membership === null) {
            throw new AuthorizationException('The membership is no longer active for this user.');
        }

        return self::forUser($user, $membership->tenant_id, $unitId);
    }

    public function revalidate(): self
    {
        return self::forUser($this->user, $this->tenant->getKey(), $this->unit?->getKey());
    }

    private static function stringIdentifier(mixed $value): ?string
    {
        if (is_object($value) && method_exists($value, 'getKey')) {
            $value = $value->getKey();
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
