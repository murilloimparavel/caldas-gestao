<?php

namespace App\Http\Requests\Integrations;

use App\Models\Category;
use App\Models\Professional;
use App\Models\Service;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ProposeServiceOperationRequest extends FormRequest
{
    private const EXTERNAL_OPERATIONS = [
        'category.create',
        'category.update',
        'professional.create',
        'professional.update',
        'service.create',
        'service.update',
        'unit.update',
    ];

    public static function supportsExternalOperation(string $operation): bool
    {
        return in_array($operation, self::EXTERNAL_OPERATIONS, true);
    }

    public function authorize(): bool
    {
        return match ($this->input('operation')) {
            'category.create' => Gate::allows('create', Category::class),
            'category.update' => $this->mayUpdateCategory(),
            'professional.create' => Gate::allows('create', Professional::class),
            'professional.update' => $this->mayManageIntegrations(),
            'service.create' => Gate::allows('create', Service::class),
            'service.update' => $this->mayManageIntegrations(),
            'unit.update' => $this->mayManageIntegrations(),
            default => false,
        };
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $operation = $this->input('operation');
        if (! is_string($operation) || ! self::supportsExternalOperation($operation)) {
            return [
                'operation' => ['required', Rule::in(self::EXTERNAL_OPERATIONS)],
                'input' => ['prohibited'],
            ];
        }

        if ($operation === 'category.create') {
            return [
                'operation' => ['required', Rule::in(['category.create'])],
                'input' => ['required', 'array:name,type,is_active'],
                'input.name' => ['required', 'string', 'max:160'],
                'input.type' => ['required', Rule::in(['service', 'product', 'general'])],
                'input.is_active' => ['sometimes', 'boolean'],
            ];
        }

        if ($operation === 'category.update') {
            return [
                'operation' => ['required', Rule::in(['category.update'])],
                'input' => ['required', 'array:category_name,name,type,is_active', 'min:2'],
                'input.category_name' => ['required', 'string', 'max:160'],
                'input.name' => ['sometimes', 'string', 'max:160'],
                'input.type' => ['sometimes', Rule::in(['service', 'product', 'general'])],
                'input.is_active' => ['sometimes', 'boolean'],
            ];
        }

        if ($operation === 'professional.create') {
            return [
                'operation' => ['required', Rule::in(['professional.create'])],
                'input' => ['required', 'array:name,status,service_names'],
                'input.name' => ['required', 'string', 'max:160'],
                'input.status' => ['required', Rule::in(['active', 'inactive'])],
                'input.service_names' => ['sometimes', 'array', 'max:100'],
                'input.service_names.*' => ['required', 'string', 'max:160', 'distinct'],
            ];
        }

        if ($operation === 'professional.update') {
            return [
                'operation' => ['required', Rule::in(['professional.update'])],
                'input' => ['required', 'array:professional_name,name,status,service_names', 'min:2'],
                'input.professional_name' => ['required', 'string', 'max:160'],
                'input.name' => ['sometimes', 'string', 'max:160'],
                'input.status' => ['sometimes', Rule::in(['active', 'inactive'])],
                'input.service_names' => ['sometimes', 'array', 'max:100'],
                'input.service_names.*' => ['required', 'string', 'max:160', 'distinct'],
            ];
        }

        if ($operation === 'service.update') {
            return [
                'operation' => ['required', Rule::in(['service.update'])],
                'input' => ['required', 'array:service_name,name,duration_minutes,price_cents,status,category_name,professional_names', 'min:2'],
                'input.service_name' => ['required', 'string', 'max:160'],
                'input.name' => ['sometimes', 'string', 'max:160'],
                'input.duration_minutes' => ['sometimes', 'integer', 'between:1,1440'],
                'input.price_cents' => ['sometimes', 'integer', 'between:0,4294967295'],
                'input.status' => ['sometimes', Rule::in(['active', 'inactive'])],
                'input.category_name' => ['sometimes', 'nullable', 'string', 'max:160'],
                'input.professional_names' => ['sometimes', 'array', 'max:100'],
                'input.professional_names.*' => ['required', 'string', 'max:160', 'distinct'],
                'input.image' => ['prohibited'],
            ];
        }

        if ($operation === 'unit.update') {
            return [
                'operation' => ['required', Rule::in(['unit.update'])],
                'input' => ['required', 'array:name,timezone,online_booking_enabled,appointment_sales_automation_enabled', 'min:1'],
                'input.name' => ['sometimes', 'string', 'max:160'],
                'input.timezone' => ['sometimes', 'nullable', 'timezone'],
                'input.online_booking_enabled' => ['sometimes', 'boolean'],
                'input.appointment_sales_automation_enabled' => ['sometimes', 'boolean'],
            ];
        }

        return [
            'operation' => ['required', Rule::in(['service.create'])],
            'input' => ['required', 'array:name,duration_minutes,price_cents,status,professional_names,category_name'],
            'input.name' => ['required', 'string', 'max:160'],
            'input.duration_minutes' => ['required', 'integer', 'between:1,1440'],
            'input.price_cents' => ['required', 'integer', 'between:0,4294967295'],
            'input.status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'input.professional_names' => ['sometimes', 'array', 'max:100'],
            'input.professional_names.*' => ['required', 'string', 'max:160', 'distinct'],
            'input.category_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'input.image' => ['prohibited'],
        ];
    }

    private function mayUpdateCategory(): bool
    {
        $context = $this->attributes->get(TenantContext::class);

        return $context instanceof TenantContext
            && $context->unit !== null
            && Gate::allows('manage-integrations');
    }

    private function mayManageIntegrations(): bool
    {
        $context = $this->attributes->get(TenantContext::class);

        return $context instanceof TenantContext
            && $context->unit !== null
            && Gate::allows('manage-integrations');
    }
}
