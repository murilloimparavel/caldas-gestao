<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Service;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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
        $hasCategoryBatch = $this->filled('category_ids');
        $scope = $this->input('scope');
        $isUpdate = $this->route('rule') instanceof CommissionRule;

        return [
            'professional_id' => ['nullable', 'uuid', $scopedExists(Professional::class)],
            'scope' => ['nullable', 'string', Rule::in(['all', 'service', 'product', 'service_category', 'product_category'])],
            'item_type' => ['sometimes', 'string', Rule::in(['service', 'product', 'both'])],
            'scope_mode' => ['sometimes', 'string', Rule::in(['all', 'category', 'specific'])],
            'category_id' => [Rule::requiredIf(in_array($scope, ['service_category', 'product_category'], true) && ! $hasCategoryBatch), 'nullable', 'uuid', Rule::prohibitedIf($hasServiceBatch || $hasProductBatch || $hasCategoryBatch || ! in_array($scope, ['service_category', 'product_category'], true)), $scopedExists(Category::class)],
            'category_ids' => [$isUpdate || $hasServiceBatch || $hasProductBatch ? 'prohibited' : 'sometimes', 'array', 'min:1', 'max:100'],
            'category_ids.*' => ['required', 'uuid', 'distinct', $scopedExists(Category::class)],
            'service_id' => ['nullable', 'uuid', Rule::prohibitedIf($hasServiceBatch || $hasProductBatch || in_array($scope, ['all', 'product', 'service_category', 'product_category'], true)), $scopedExists(Service::class)],
            'service_ids' => [$isUpdate || $hasProductBatch ? 'prohibited' : 'sometimes', 'array', 'min:1', 'max:100'],
            'service_ids.*' => ['required', 'uuid', 'distinct', $scopedExists(Service::class)],
            'product_id' => ['nullable', 'uuid', Rule::prohibitedIf($hasServiceBatch || $hasProductBatch || in_array($scope, ['all', 'service', 'service_category', 'product_category'], true)), $scopedExists(Product::class)],
            'product_ids' => [$isUpdate || $hasServiceBatch ? 'prohibited' : 'sometimes', 'array', 'min:1', 'max:100'],
            'product_ids.*' => ['required', 'uuid', 'distinct', $scopedExists(Product::class)],
            'type' => ['required', 'string', 'in:percentage,fixed'],
            'value_rate' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $itemType = $this->input('item_type');
        $scopeMode = $this->input('scope_mode');

        if ($this->missing('scope') && $scopeMode !== null && in_array($itemType, ['service', 'product'], true)) {
            $this->merge(['scope' => $scopeMode === 'category' ? $itemType.'_category' : $itemType]);
        } elseif ($this->filled('category_id') && $this->missing('scope')) {
            $this->merge(['scope' => 'service_category']);
        } elseif ($this->filled('category_ids') && $this->missing('scope')) {
            $this->merge(['scope' => $itemType === 'product' ? 'product_category' : 'service_category']);
        }
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $scope = $this->input('scope');
            $categoryId = $this->input('category_id');

            if ($this->filled('service_id') && $this->filled('product_id')) {
                $validator->errors()->add('scope', 'Uma regra não pode combinar serviço e produto.');
            }

            if ($categoryId === null || ! in_array($scope, ['service_category', 'product_category'], true)) {
                $categoryIds = $this->input('category_ids', []);
                if (! is_array($categoryIds) || $categoryIds === [] || ! in_array($scope, ['service_category', 'product_category'], true)) {
                    return;
                }

                $categoryId = null;
            }

            $categoryIds = $categoryId !== null ? [$categoryId] : $this->input('category_ids', []);
            if (! is_array($categoryIds)) {
                return;
            }

            $expectedType = $scope === 'service_category' ? 'service' : 'product';
            $categoryTypes = Category::query()->whereIn('id', $categoryIds)->pluck('type', 'id');

            if ($categoryTypes->contains(fn (string $type): bool => $type !== $expectedType && $type !== 'general')) {
                $validator->errors()->add('category_id', 'A categoria selecionada não pertence ao tipo da regra.');
            }
        }];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array{
     *     professional_id?: string|null,
     *     scope?: string,
     *     service_id?: string|null,
     *     service_ids?: list<string>,
     *     product_id?: string|null,
     *     product_ids?: list<string>,
     *     category_id?: string|null,
     *     category_ids?: list<string>,
     *     type?: string,
     *     value_rate: int,
     *     is_active?: bool,
     *     lock_version?: int
     * }
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{professional_id?: string|null, scope?: string, item_type?: string, scope_mode?: string, service_id?: string|null, service_ids?: list<string>, product_id?: string|null, product_ids?: list<string>, category_id?: string|null, category_ids?: list<string>, type?: string, value_rate: int, is_active?: bool, lock_version?: int} */
        return parent::validated($key, $default);
    }
}
