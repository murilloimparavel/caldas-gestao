<?php

namespace App\Actions\Entitlements;

use App\Enums\EntitlementSource;
use App\Enums\EntitlementStatus;
use App\Models\Entitlement;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\PayloadGovernance;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GrantEntitlement
{
    public function __construct(
        private readonly AuthorizationService $authorization = new AuthorizationService,
        private readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        private readonly PayloadGovernance $payload = new PayloadGovernance,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Entitlement
    {
        $tenant = $context->tenant;
        $this->authorization->assertTenantManager($actor, $context, $tenant);

        if (! $this->authorization->can($actor, $context, 'entitlement.manage')) {
            throw new AuthorizationException('The actor is not allowed to manage entitlements.');
        }

        $key = trim((string) ($data['key'] ?? ''));
        $startsAt = $data['starts_at'] ?? now();
        $endsAt = $data['ends_at'] ?? null;

        if ($key === '' || Str::length($key) > 100) {
            throw new \InvalidArgumentException('An entitlement key is required and must be at most 100 characters.');
        }

        if (! $startsAt instanceof \DateTimeInterface || ($endsAt !== null && ! $endsAt instanceof \DateTimeInterface)) {
            throw new \InvalidArgumentException('Entitlement dates must be DateTime values.');
        }

        if ($endsAt !== null && $endsAt <= $startsAt) {
            throw new \InvalidArgumentException('Entitlement end must be after its start.');
        }

        $status = EntitlementStatus::from((string) ($data['status'] ?? EntitlementStatus::Active->value));
        if (! $status->grantsAccess()) {
            throw new \InvalidArgumentException('A new entitlement must grant an access state.');
        }

        $source = EntitlementSource::from((string) ($data['source'] ?? EntitlementSource::Manual->value));

        return DB::transaction(function () use ($actor, $tenant, $key, $startsAt, $endsAt, $status, $source, $data): Entitlement {
            Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $entitlement = Entitlement::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenant->getKey(),
                'key' => $key,
                'status' => $status,
                'quantity' => $data['quantity'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'source' => $source,
                'config' => $this->payload->entitlementConfig((array) ($data['config'] ?? [])),
                'lock_version' => 0,
            ]);
            $this->events->recordForTenant($actor, $tenant, 'entitlement.granted', $entitlement, [
                'resource_id' => $entitlement->getKey(),
                'status' => $status->value,
                'quantity' => $entitlement->quantity,
            ]);

            return $entitlement;
        }, 5);
    }
}
