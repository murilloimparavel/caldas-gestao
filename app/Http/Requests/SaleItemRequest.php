<?php

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SaleItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale && Gate::allows('update', $sale);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'item_type' => ['required', Rule::in(['service', 'product', 'custom'])],
            'service_id' => ['nullable', 'required_if:item_type,service', 'uuid'],
            'customer_package_id' => ['nullable', 'uuid', Rule::prohibitedIf($this->input('item_type') !== 'service')],
            'product_id' => ['nullable', 'required_if:item_type,product', 'uuid'],
            'professional_id' => ['nullable', 'uuid'],
            'seller_professional_id' => ['nullable', 'uuid'],
            'name_snapshot' => ['nullable', 'required_if:item_type,custom', 'string', 'max:160'],
            'unit_price_cents' => ['nullable', 'required_if:item_type,custom', 'integer', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'discount_cents' => ['nullable', 'integer', 'min:0'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
