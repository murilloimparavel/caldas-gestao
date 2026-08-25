<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Support\Str;

final class AuditEventWriter
{
    public function __construct(private readonly PayloadGovernance $payload = new PayloadGovernance) {}

    /** @param array<string, mixed> $attributes */
    public function record(array $attributes): AuditEvent
    {
        $metadata = $this->payload->auditMetadata((array) ($attributes['metadata'] ?? []));

        return AuditEvent::query()->create([
            'event_id' => $attributes['event_id'] ?? (string) Str::uuid7(),
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'unit_id' => $attributes['unit_id'] ?? null,
            'actor_user_id' => $attributes['actor_user_id'] ?? null,
            'action' => (string) ($attributes['action'] ?? 'unknown'),
            'resource_type' => (string) ($attributes['resource_type'] ?? 'unknown'),
            'resource_id' => $attributes['resource_id'] ?? null,
            'request_id' => $attributes['request_id'] ?? null,
            'correlation_id' => $attributes['correlation_id'] ?? null,
            'reason' => $this->reason($attributes['reason'] ?? null),
            'metadata' => $metadata,
            'ip_address' => $attributes['ip_address'] ?? null,
            'user_agent_hash' => $attributes['user_agent_hash'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);
    }

    private function reason(mixed $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $reason = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string) $reason)) ?? '';

        return $reason === '' ? null : Str::limit($reason, 500, '');
    }
}
