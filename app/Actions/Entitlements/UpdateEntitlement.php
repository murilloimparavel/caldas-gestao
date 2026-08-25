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

final class UpdateEntitlement
{
    public function __construct(
        private readonly AuthorizationService $authorization = new AuthorizationService,
        private readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        private readonly PayloadGovernance $payload = new PayloadGovernance,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Entitlement $entitlement, array $data, ?int $expectedVersion = null): Entitlement
    {
        if ($entitlement->tenant_id !== $context->tenant->getKey()) {
            throw new AuthorizationException('The entitlement belongs to another tenant.');
        }
        $this->authorization->assertTenantManager($actor, $context, $context->tenant);

        if (! $this->authorization->can($actor, $context, 'entitlement.manage')) {
            throw new AuthorizationException('The actor is not allowed to manage entitlements.');
        }

        return DB::transaction(function () use ($actor, $context, $entitlement, $data, $expectedVersion): Entitlement {
            Tenant::query()->whereKey($context->tenant->getKey())->lockForUpdate()->firstOrFail();
            $locked = Entitlement::query()->whereKey($entitlement->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->tenant_id !== $context->tenant->getKey()) {
                throw new AuthorizationException('The entitlement belongs to another tenant.');
            }
            if ($expectedVersion !== null && $locked->lock_version !== $expectedVersion) {
                throw new \LogicException('The entitlement was modified concurrently.');
            }
            if (in_array($locked->status, [EntitlementStatus::Expired, EntitlementStatus::Revoked], true)) {
                throw new \LogicException('Expired and revoked entitlements are immutable.');
            }

            $nextStatus = array_key_exists('status', $data) ? EntitlementStatus::from((string) $data['status']) : $locked->status;
            if (! $this->canTransition($locked->status, $nextStatus)) {
                throw new \LogicException('The entitlement status transition is not allowed.');
            }

            $nextStarts = $data['starts_at'] ?? $locked->starts_at;
            $nextEnds = array_key_exists('ends_at', $data) ? $data['ends_at'] : $locked->ends_at;
            if (! $nextStarts instanceof \DateTimeInterface || ($nextEnds !== null && ! $nextEnds instanceof \DateTimeInterface) || ($nextEnds !== null && $nextEnds <= $nextStarts)) {
                throw new \InvalidArgumentException('Entitlement dates are invalid.');
            }

            $locked->forceFill([
                'status' => $nextStatus,
                'quantity' => $data['quantity'] ?? $locked->quantity,
                'starts_at' => $nextStarts,
                'ends_at' => $nextEnds,
                'source' => array_key_exists('source', $data) ? EntitlementSource::from((string) $data['source']) : $locked->source,
                'config' => array_key_exists('config', $data) ? $this->payload->entitlementConfig((array) $data['config']) : $locked->config,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->events->recordForTenant($actor, $context->tenant, 'entitlement.updated', $locked, [
                'resource_id' => $locked->getKey(),
                'status' => $nextStatus->value,
                'quantity' => $locked->quantity,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh();
        }, 5);
    }

    private function canTransition(EntitlementStatus $current, EntitlementStatus $next): bool
    {
        if ($current === $next) {
            return true;
        }

        return match ($current) {
            EntitlementStatus::Trial => in_array($next, [EntitlementStatus::Active, EntitlementStatus::Revoked], true),
            EntitlementStatus::Active => in_array($next, [EntitlementStatus::Grace, EntitlementStatus::Revoked], true),
            EntitlementStatus::Grace => in_array($next, [EntitlementStatus::Suspended, EntitlementStatus::Revoked], true),
            EntitlementStatus::Suspended => in_array($next, [EntitlementStatus::Expired, EntitlementStatus::Revoked], true),
            EntitlementStatus::Expired, EntitlementStatus::Revoked => false,
        };
    }
}
