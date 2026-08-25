<?php

namespace App\Support;

use App\Enums\EntitlementStatus;
use App\Models\Entitlement;
use App\Models\Tenant;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;

final class EntitlementService
{
    public function activeAt(Tenant|string $tenant, string $key, ?DateTimeInterface $asOf = null): ?Entitlement
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $asOf ??= now();

        return Entitlement::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->whereIn('status', array_map(static fn (EntitlementStatus $status): string => $status->value, [
                EntitlementStatus::Trial,
                EntitlementStatus::Active,
                EntitlementStatus::Grace,
                EntitlementStatus::Suspended,
            ]))
            ->where('starts_at', '<=', $asOf)
            ->where(static function ($query) use ($asOf): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $asOf);
            })
            ->orderByDesc('starts_at')
            ->first();
    }

    public function can(Tenant|string $tenant, string $key, ?DateTimeInterface $asOf = null): bool
    {
        return $this->activeAt($tenant, $key, $asOf) !== null;
    }

    /** @return Collection<int, Entitlement> */
    public function activeFor(Tenant|string $tenant, ?DateTimeInterface $asOf = null): Collection
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
        $asOf ??= now();

        return Entitlement::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['trial', 'active', 'grace', 'suspended'])
            ->where('starts_at', '<=', $asOf)
            ->where(static function ($query) use ($asOf): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $asOf);
            })
            ->orderBy('key')
            ->get();
    }
}
