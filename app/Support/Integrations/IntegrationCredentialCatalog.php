<?php

namespace App\Support\Integrations;

use App\Models\Integrations\IntegrationCredential;
use App\Support\TenantContext;

class IntegrationCredentialCatalog
{
    /** @return array<string, list<string>> */
    public function capabilitySets(): array
    {
        return [
            'context:read' => ['context:read'],
            'operations:propose' => ['operations:propose'],
            'catalog:read' => ['catalog:read'],
            'setup:read' => ['setup:read'],
            'catalog:read+setup:read' => ['catalog:read', 'setup:read'],
            'context:read+catalog:read+setup:read' => ['context:read', 'catalog:read', 'setup:read'],
        ];
    }

    /** @return list<array{id: string, label: string, capabilities: list<string>, created_at: string, expires_at: string, last_used_at: string|null, revoked_at: string|null, status: string}> */
    public function forContext(TenantContext $context): array
    {
        return array_values(IntegrationCredential::query()
            ->with('passportToken')
            ->where('user_id', $context->user->getKey())
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->latest()
            ->get()
            ->map(fn (IntegrationCredential $credential): array => $this->metadata($credential))
            ->values()
            ->all());
    }

    /** @return array{id: string, label: string, capabilities: list<string>, created_at: string, expires_at: string, last_used_at: string|null, revoked_at: string|null, status: string} */
    public function metadata(IntegrationCredential $credential): array
    {
        return [
            'id' => (string) $credential->getKey(),
            'label' => $credential->label,
            'capabilities' => $credential->capabilities,
            'created_at' => $credential->created_at?->toISOString() ?? '',
            'expires_at' => $credential->expires_at->toISOString(),
            'last_used_at' => $credential->last_used_at?->toISOString(),
            'revoked_at' => $credential->revoked_at?->toISOString(),
            'status' => $credential->revoked_at !== null || $credential->passportToken?->revoked
                ? 'revoked'
                : ($credential->expires_at->isPast() ? 'expired' : 'active'),
        ];
    }
}
