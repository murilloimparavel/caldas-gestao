<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('subscription_plan') ?? $this->route('subscriptionPlan');

        return $plan instanceof SubscriptionPlan
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $plan)
            : Gate::allows('create', SubscriptionPlan::class);
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
            'billing_cycle' => ['required', 'string', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'is_active' => ['sometimes', 'boolean'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['uuid', 'distinct', $serviceExists],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'billing_cycle.in' => 'O ciclo de cobrança deve ser mensal, trimestral ou anual.',
            'service_ids.*.exists' => 'Cada serviço deve pertencer à unidade ativa.',
        ];
    }
}
