<?php

namespace App\Support\Integrations;

final class IntegrationCapabilityCatalog
{
    /** @return list<array{id: string, description: string, access: string, confirmation_required: bool, endpoint: string|null}> */
    public function capabilities(): array
    {
        return [
            [
                'id' => 'context:read',
                'description' => 'Read whether the authenticated integration is bound to an administrative account, tenant, and unit.',
                'access' => 'read',
                'confirmation_required' => false,
                'endpoint' => '/api/v1/context',
            ],
            [
                'id' => 'operations:propose',
                'description' => 'Propose category, professional, service, and unit settings changes for an administrator to review and confirm.',
                'access' => 'write_proposal',
                'confirmation_required' => true,
                'endpoint' => '/api/v1/operations',
            ],
            [
                'id' => 'catalog:read',
                'description' => 'Read approved category, service, professional, and setup indicator fields for the bound unit.',
                'access' => 'read',
                'confirmation_required' => false,
                'endpoint' => '/api/v1/services',
            ],
            [
                'id' => 'setup:read',
                'description' => 'Read minimized setup and booking readiness indicators for the bound unit.',
                'access' => 'read',
                'confirmation_required' => false,
                'endpoint' => '/api/v1/setup/status',
            ],
        ];
    }

    /** @return list<array{operation: string, description: string, method: string, path: string, capability: string, input_schema: array<string, mixed>}> */
    public function operations(): array
    {
        return [
            [
                'operation' => 'category.create',
                'description' => 'Create a category after an administrator reviews and confirms the proposal.',
                'method' => 'POST',
                'path' => '/api/v1/operations',
                'capability' => 'operations:propose',
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name', 'type'],
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 160],
                        'type' => ['type' => 'string', 'enum' => ['service', 'product', 'general']],
                        'is_active' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
            ],
            [
                'operation' => 'category.update',
                'description' => 'Update supplied category fields after an administrator reviews and confirms the proposal. Identify the category by its exact name within the bound unit; omitted fields are preserved.',
                'method' => 'POST',
                'path' => '/api/v1/operations',
                'capability' => 'operations:propose',
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['category_name'],
                    'properties' => [
                        'category_name' => ['type' => 'string', 'maxLength' => 160],
                        'name' => ['type' => 'string', 'maxLength' => 160],
                        'type' => ['type' => 'string', 'enum' => ['service', 'product', 'general']],
                        'is_active' => ['type' => 'boolean'],
                    ],
                ],
            ],
            [
                'operation' => 'professional.create',
                'description' => 'Create a professional and optionally assign services after an administrator reviews and confirms the proposal.',
                'method' => 'POST',
                'path' => '/api/v1/operations',
                'capability' => 'operations:propose',
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name', 'status'],
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 160],
                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                        'service_names' => ['type' => 'array', 'maxItems' => 100, 'items' => ['type' => 'string', 'maxLength' => 160]],
                    ],
                ],
            ],
            [
                'operation' => 'professional.update',
                'description' => 'Update a professional identified by exact name within the bound unit and optionally replace service assignments by exact service names after an administrator reviews and confirms the proposal.',
                'method' => 'POST',
                'path' => '/api/v1/operations',
                'capability' => 'operations:propose',
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'minProperties' => 2,
                    'required' => ['professional_name'],
                    'properties' => [
                        'professional_name' => ['type' => 'string', 'maxLength' => 160, 'description' => 'Exact professional name in the bound unit; the server resolves the identifier and current version.'],
                        'name' => ['type' => 'string', 'maxLength' => 160, 'description' => 'Optional name update; omitted values are preserved.'],
                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Optional status update; omitted values are preserved.'],
                        'service_names' => ['type' => 'array', 'maxItems' => 100, 'description' => 'Optional relationship replacement by exact names; omitted values preserve current links.', 'items' => ['type' => 'string', 'maxLength' => 160]],
                    ],
                ],
            ],
            [
                'operation' => 'service.create',
                'description' => 'Create a service after an administrator reviews and confirms the proposal.',
                'method' => 'POST',
                'path' => '/api/v1/operations',
                'capability' => 'operations:propose',
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['name', 'duration_minutes', 'price_cents'],
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 160],
                        'duration_minutes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1440],
                        'price_cents' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 4294967295],
                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                        'professional_names' => ['type' => 'array', 'maxItems' => 100, 'items' => ['type' => 'string', 'maxLength' => 160]],
                        'category_name' => ['type' => ['string', 'null'], 'maxLength' => 160],
                    ],
                ],
            ],
            [
                'operation' => 'service.update',
                'description' => 'Update a service identified by exact name within the bound unit. Exact category and professional names can change relationships; omitted fields and relationships are preserved, an empty professional_names list clears assignments, and category_name null clears its category.',
                'method' => 'POST',
                'path' => '/api/v1/operations',
                'capability' => 'operations:propose',
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['service_name'],
                    'properties' => [
                        'service_name' => ['type' => 'string', 'maxLength' => 160],
                        'name' => ['type' => 'string', 'maxLength' => 160],
                        'duration_minutes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1440],
                        'price_cents' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 4294967295],
                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
                        'category_name' => ['type' => ['string', 'null'], 'maxLength' => 160],
                        'professional_names' => ['type' => 'array', 'maxItems' => 100, 'items' => ['type' => 'string', 'maxLength' => 160]],
                    ],
                ],
            ],
            [
                'operation' => 'unit.update',
                'description' => 'Patch selected editable settings for the credential-bound unit after an administrator reviews and confirms the proposal. The server supplies the current optimistic-lock version; omitted fields are preserved.',
                'method' => 'POST',
                'path' => '/api/v1/operations',
                'capability' => 'operations:propose',
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'minProperties' => 1,
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 160],
                        'timezone' => ['type' => ['string', 'null']],
                        'online_booking_enabled' => ['type' => 'boolean'],
                        'appointment_sales_automation_enabled' => ['type' => 'boolean'],
                    ],
                ],
            ],
        ];
    }
}
