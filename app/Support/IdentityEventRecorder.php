<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IdentityEventRecorder
{
    public function __construct(
        private readonly AuditEventWriter $audit,
        private readonly OutboxEventStore $outbox,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function record(?User $actor, TenantContext $context, string $action, Model $resource, array $metadata = []): void
    {
        $this->recordForTenant($actor, $context->tenant, $action, $resource, $metadata, $context->unit?->getKey());
    }

    /** @param array<string, mixed> $metadata */
    public function recordForTenant(?User $actor, Tenant $tenant, string $action, Model $resource, array $metadata = [], ?string $unitId = null): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Identity audit and outbox events require an active transaction.');
        }

        $resourceType = Str::snake(class_basename($resource));
        $resourceId = $resource->getKey();
        $eventId = (string) Str::uuid7();
        $requestId = $this->contextIdentifier('request_id') ?? $eventId;
        $correlationId = $this->contextIdentifier('correlation_id') ?? $requestId;
        $causationId = $this->contextIdentifier('causation_id');

        $this->audit->record([
            'event_id' => $eventId,
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unitId,
            'actor_user_id' => $actor?->getKey(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => is_string($resourceId) ? $resourceId : null,
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'metadata' => $metadata,
        ]);

        $this->outbox->enqueue([
            'event_id' => $eventId,
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unitId,
            'actor_user_id' => $actor?->getKey(),
            'aggregate_type' => $resourceType,
            'aggregate_id' => (string) $resourceId,
            'aggregate_version' => max(1, (int) ($resource->getAttribute('lock_version') ?? 1)),
            'event_type' => $action,
            'correlation_id' => $correlationId,
            'causation_id' => $causationId,
            'payload' => [
                'resource_type' => $resourceType,
                'resource_id' => (string) $resourceId,
                ...array_intersect_key($metadata, array_flip(['service_ids', 'professional_ids'])),
            ],
        ]);
    }

    private function contextIdentifier(string $key): ?string
    {
        $value = Context::get($key);

        return is_string($value) && (Str::isUuid($value) || Str::isUlid($value)) ? $value : null;
    }
}
