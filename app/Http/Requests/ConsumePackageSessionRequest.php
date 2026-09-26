<?php

namespace App\Http\Requests;

use App\Models\CustomerPackage;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ConsumePackageSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customerPackage = $this->route('customer_package') ?? $this->route('customerPackage');

        return $customerPackage instanceof CustomerPackage
            ? Gate::allows('consume', $customerPackage)
            : Gate::allows('consume', CustomerPackage::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $context = $this->attributes->get(TenantContext::class);

        $saleExists = Rule::exists(Sale::class, 'id');
        $saleItemExists = Rule::exists(SaleItem::class, 'id');

        if ($context instanceof TenantContext) {
            $saleExists->where('tenant_id', $context->tenant->getKey());
            $saleItemExists->where('tenant_id', $context->tenant->getKey());

            if ($context->unit !== null) {
                $saleExists->where('unit_id', $context->unit->getKey());
                $saleItemExists->where('unit_id', $context->unit->getKey());
            }
        }

        return [
            'sessions_consumed' => ['nullable', 'integer', 'min:1', 'max:100'],
            'service_id' => ['nullable', 'uuid'],
            'sale_id' => ['nullable', 'uuid', $saleExists],
            'sale_item_id' => ['nullable', 'uuid', $saleItemExists],
            'lock_version' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sale_id.exists' => 'A comanda selecionada não pertence à unidade ativa.',
            'sale_item_id.exists' => 'O item de comanda selecionado não pertence à unidade ativa.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('sale_item_id') && ! $this->filled('sale_id')) {
                $validator->errors()->add('sale_item_id', 'O item de comanda exige uma comanda associada.');
            }
        }];
    }
}
