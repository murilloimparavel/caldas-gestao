<?php

namespace App\Http\Requests;

use App\Models\PackageTemplate;
use App\Models\Service;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PackageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('package_template') ?? $this->route('packageTemplate');

        return $template instanceof PackageTemplate
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $template)
            : Gate::allows('create', PackageTemplate::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->isMethod('delete') || $this->routeIs('*.reactivate')) {
            return ['lock_version' => ['sometimes', 'integer', 'min:0']];
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
            'description' => ['nullable', 'string', 'max:5000'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'total_sessions' => ['required', 'integer', 'min:1', 'max:1000'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['uuid', 'distinct', $serviceExists],
            'service_quantities' => ['sometimes', 'array'],
            'service_quantities.*' => ['integer', 'min:1', 'max:1000'],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('service_quantities')) {
            return;
        }

        $quantities = collect($this->input('service_quantities', []))
            ->mapWithKeys(static fn (mixed $quantity, mixed $serviceId): array => [(string) $serviceId => (int) $quantity])
            ->all();

        $this->merge(['service_quantities' => $quantities]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'service_ids.*.exists' => 'Cada serviço deve pertencer à unidade ativa.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $quantities = $this->input('service_quantities', []);
            if (! is_array($quantities) || $quantities === []) {
                return;
            }

            $total = (int) $this->input('total_sessions', 0);
            $configured = array_sum(array_map('intval', $quantities));
            if ($configured !== $total) {
                $validator->errors()->add('total_sessions', "A soma das quantidades por serviço deve ser {$total} sessões.");
            }
        }];
    }
}
