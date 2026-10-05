<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\Integrations\ProposedOperation;
use App\Support\Integrations\ProposalConfirmationUrl;

final class ProposalMetadata
{
    /** @return array{id: string, operation: string, status: string, expires_at: string, confirmation_required: true, confirmation_url: string, changed_fields: list<string>} */
    public static function from(ProposedOperation $proposal): array
    {
        return [
            'id' => (string) $proposal->getKey(),
            'operation' => $proposal->operation_key,
            'status' => $proposal->status,
            'expires_at' => $proposal->expires_at->toISOString(),
            'confirmation_required' => true,
            'confirmation_url' => ProposalConfirmationUrl::for($proposal),
            'changed_fields' => self::changedFields($proposal),
        ];
    }

    /** @return list<string> */
    private static function changedFields(ProposedOperation $proposal): array
    {
        $allowedFields = match ($proposal->operation_key) {
            'category.create', 'category.update' => ['name', 'type', 'is_active'],
            'professional.create', 'professional.update' => ['name', 'status', 'service_ids'],
            'service.create', 'service.update' => ['name', 'duration_minutes', 'price_cents', 'status', 'category_id', 'professional_ids'],
            'unit.update' => ['name', 'timezone', 'online_booking_enabled', 'appointment_sales_automation_enabled'],
            default => [],
        };

        $candidateFields = in_array($proposal->operation_key, ['professional.update', 'service.update', 'category.update', 'unit.update'], true)
            ? ($proposal->input['_provided_fields'] ?? array_keys($proposal->input))
            : array_keys($proposal->input);
        $fields = array_values(array_filter(
            $candidateFields,
            fn (string $field): bool => in_array($field, $allowedFields, true),
        ));

        return array_map(static fn (string $field): string => match ($field) {
            'service_ids' => 'service_names',
            'professional_ids' => 'professional_names',
            'category_id' => 'category_name',
            default => $field,
        }, $fields);
    }
}
