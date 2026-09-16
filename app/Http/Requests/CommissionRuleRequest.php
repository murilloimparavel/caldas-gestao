<?php

namespace App\Http\Requests;

use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Service;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class CommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('rule');

        return $rule instanceof CommissionRule
            ? Gate::allows('update', $rule)
            : Gate::allows('create', CommissionRule::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $context = $this->attributes->get(TenantContext::class);
        $tenantId = $context instanceof TenantContext ? $context->tenant->getKey() : null;
        $unitId = $context instanceof TenantContext ? $context->unit?->getKey() : null;
        $scopedExists = static function (string $model) use ($tenantId, $unitId): Exists {
            $rule = Rule::exists($model, 'id');
            if ($tenantId !== null) {
                $rule->where('tenant_id', $tenantId);
            }
            if ($unitId !== null) {
                $rule->where('unit_id', $unitId);
            }

            return $rule;
        };
        $hasServiceBatch = $this->filled('service_ids');
        $hasProductBatch = $this->filled('product_ids');
        $isUpdate = $this->route('rule') instanceof CommissionRule;

        return [
            'professional_id' => ['nullable', 'uuid', $scopedExists(Professional::class)],
            'service_id' => ['nullable', 'uuid', Rule::prohibitedIf($hasServiceBatch || $hasProductBatch), $scopedExists(Service::class)],
            'service_ids' => [$isUpdate || $hasProductBatch ? 'prohibited' : 'sometimes', 'array', 'min:1', 'max:100'],
            'service_ids.*' => ['required', 'uuid', 'distinct', $scopedExists(Service::class)],
            'product_id' => ['nullable', 'uuid', Rule::prohibitedIf($hasServiceBatch || $hasProductBatch), $scopedExists(Product::class)],
            'product_ids' => [$isUpdate || $hasServiceBatch ? 'prohibited' : 'sometimes', 'array', 'min:1', 'max:100'],
            'product_ids.*' => ['required', 'uuid', 'distinct', $scopedExists(Product::class)],
            'type' => ['required', 'string', 'in:percentage,fixed'],
            'value_rate' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array{
     *     professional_id?: string|null,
     *     service_id?: string|null,
     *     product_id?: string|null,
     *     type?: string,
     *     value_rate: int,
     *     is_active?: bool,
     *     lock_version?: int
     * }
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{professional_id?: string|null, service_id?: string|null, product_id?: string|null, type?: string, value_rate: int, is_active?: bool, lock_version?: int} */
        return parent::validated($key, $default);
    }
}
