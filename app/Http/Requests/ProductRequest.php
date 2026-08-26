<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Product;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $product)
            : Gate::allows('create', Product::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->isMethod('delete') || $this->routeIs('*.reactivate')) {
            return ['lock_version' => ['required', 'integer', 'min:0']];
        }

        $categoryExists = Rule::exists(Category::class, 'id');
        $context = $this->attributes->get(TenantContext::class);
        if ($context instanceof TenantContext) {
            $categoryExists->where('tenant_id', $context->tenant->getKey());
            if ($context->unit !== null) {
                $categoryExists->where('unit_id', $context->unit->getKey());
            }
        }

        return [
            'category_id' => ['nullable', 'uuid', $categoryExists],
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'cost_price_cents' => ['required', 'integer', 'between:0,4294967295'],
            'sale_price_cents' => ['required', 'integer', 'between:0,4294967295'],
            'unit_of_measure' => ['required', 'string', 'max:16'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'current_stock' => ['required', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'A categoria selecionada deve pertencer à unidade ativa.',
        ];
    }
}
