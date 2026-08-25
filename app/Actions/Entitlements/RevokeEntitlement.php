<?php

namespace App\Actions\Entitlements;

use App\Enums\EntitlementStatus;
use App\Models\Entitlement;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RevokeEntitlement
{
    public function __construct(
        private readonly AuthorizationService $authorization = new AuthorizationService,
        private readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
    ) {}

    public function handle(User $actor, TenantContext $context, Entitlement $entitlement, ?int $expectedVersion = null): Entitlement
    {
        if ($entitlement->tenant_id !== $context->tenant->getKey()) {
            throw new AuthorizationException('The entitlement belongs to another tenant.');
        }
        $this->authorization->assertTenantManager($actor, $context, $context->tenant);

        if (! $this->authorization->can($actor, $context, 'entitlement.manage')) {
            throw new AuthorizationException('The actor is not allowed to manage entitlements.');
        }

        return DB::transaction(function () use ($actor, $context, $entitlement, $expectedVersion): Entitlement {
            Tenant::query()->whereKey($context->tenant->getKey())->lockForUpdate()->firstOrFail();
            $locked = Entitlement::query()->whereKey($entitlement->getKey())->lockForUpdate()->firstOrFail();
            if ($expectedVersion !== null && $locked->lock_version !== $expectedVersion) {
                throw new \LogicException('The entitlement was modified concurrently.');
            }
            if ($locked->status === EntitlementStatus::Expired) {
                throw new \LogicException('Expired entitlements are immutable.');
            }
            if ($locked->status === EntitlementStatus::Revoked) {
                return $locked;
            }

            $locked->forceFill([
                'status' => EntitlementStatus::Revoked,
                'ends_at' => $locked->ends_at ?? now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->events->recordForTenant($actor, $context->tenant, 'entitlement.revoked', $locked, [
                'resource_id' => $locked->getKey(),
                'status' => EntitlementStatus::Revoked->value,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh();
        }, 5);
    }
}
