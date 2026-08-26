<?php

namespace App\Http\Requests;

use App\Models\Professional;
use App\Models\Service;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ProfessionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $professional = $this->route('professional');

        return $professional instanceof Professional
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $professional)
            : Gate::allows('create', Professional::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->isMethod('delete') || $this->routeIs('*.reactivate')) {
            return ['lock_version' => ['required', 'integer', 'min:0']];
        }

        $serviceExists = Rule::exists(Service::class, 'id');
        $context = $this->attributes->get(TenantContext::class);
        if ($context instanceof TenantContext) {
            $serviceExists->where('tenant_id', $context->tenant->getKey());
            if ($context->unit !== null) {
                $serviceExists->where('unit_id', $context->unit->getKey());
            }
        }

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'service_ids' => ['sometimes', 'array', 'max:100'],
            'service_ids.*' => ['uuid', 'distinct', $serviceExists],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['service_ids.*.exists' => 'Cada serviço deve pertencer à unidade ativa.'];
    }
}
