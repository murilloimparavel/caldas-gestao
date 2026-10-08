<?php

namespace App\Http\Requests;

use App\Models\Professional;
use App\Models\Sale;
use App\Support\TenantContext;
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
        $context = $this->attributes->get(TenantContext::class);
        $professionalExists = Rule::exists(Professional::class, 'id');

        if ($context instanceof TenantContext) {
            $professionalExists->where('tenant_id', $context->tenant->getKey());

            if ($context->unit !== null) {
                $professionalExists->where('unit_id', $context->unit->getKey());
            }

            $professionalExists->where('status', 'active');
        }

        return [
            'item_type' => ['required', Rule::in(['service', 'product', 'package', 'custom'])],
            'service_id' => ['nullable', 'required_if:item_type,service', 'uuid'],
            'customer_package_id' => ['nullable', 'uuid', Rule::prohibitedIf(! in_array($this->input('item_type'), ['service', 'package'], true))],
            'product_id' => ['nullable', 'required_if:item_type,product', 'uuid'],
            'package_template_id' => ['nullable', 'required_if:item_type,package', 'uuid', Rule::prohibitedIf($this->input('item_type') !== 'package')],
            'professional_id' => ['nullable', 'required_if:item_type,package', 'uuid', $professionalExists],
            'seller_professional_id' => ['nullable', 'uuid', Rule::prohibitedIf($this->input('item_type') === 'package')],
            'name_snapshot' => ['nullable', 'required_if:item_type,custom', 'string', 'max:160'],
            'unit_price_cents' => ['nullable', 'required_if:item_type,custom', 'integer', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'discount_cents' => ['nullable', 'integer', 'min:0'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
