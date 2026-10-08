<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\PackageTemplate;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SellCustomerPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('sell', CustomerPackage::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $context = $this->attributes->get(TenantContext::class);

        $customerExists = Rule::exists(Customer::class, 'id');
        $templateExists = Rule::exists(PackageTemplate::class, 'id');
        if ($context instanceof TenantContext) {
            $customerExists->where('tenant_id', $context->tenant->getKey());
            $templateExists->where('tenant_id', $context->tenant->getKey());

            if ($context->unit !== null) {
                $customerExists->where('unit_id', $context->unit->getKey());
                $templateExists->where('unit_id', $context->unit->getKey());
            }
        }

        return [
            'customer_id' => ['required', 'uuid', $customerExists],
            'package_template_id' => ['required', 'uuid', $templateExists],
            'start_sale' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.exists' => 'O cliente selecionado não pertence à unidade ativa.',
            'package_template_id.exists' => 'O modelo de pacote não pertence à unidade ativa.',
        ];
    }
}
