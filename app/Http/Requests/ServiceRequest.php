<?php

namespace App\Http\Requests;

use App\Models\Professional;
use App\Models\Service;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $service)
            : Gate::allows('create', Service::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->isMethod('delete') || $this->routeIs('*.reactivate')) {
            return ['lock_version' => ['required', 'integer', 'min:0']];
        }

        $professionalExists = Rule::exists(Professional::class, 'id');
        $context = $this->attributes->get(TenantContext::class);
        if ($context instanceof TenantContext) {
            $professionalExists->where('tenant_id', $context->tenant->getKey());
            if ($context->unit !== null) {
                $professionalExists->where('unit_id', $context->unit->getKey());
            }
        }

        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['required', 'integer', 'between:1,1440'],
            'price_cents' => ['required', 'integer', 'between:0,4294967295'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'professional_ids' => ['sometimes', 'array', 'max:100'],
            'professional_ids.*' => ['uuid', 'distinct', $professionalExists],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['professional_ids.*.exists' => 'Cada profissional deve pertencer à unidade ativa.'];
    }
}
