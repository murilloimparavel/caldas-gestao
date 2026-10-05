<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\PackageTemplate;
use App\Models\Sale;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $saleExists = Rule::exists(Sale::class, 'id');

        if ($context instanceof TenantContext) {
            $customerExists->where('tenant_id', $context->tenant->getKey());
            $templateExists->where('tenant_id', $context->tenant->getKey());
            $saleExists->where('tenant_id', $context->tenant->getKey());

            if ($context->unit !== null) {
                $customerExists->where('unit_id', $context->unit->getKey());
                $templateExists->where('unit_id', $context->unit->getKey());
                $saleExists->where('unit_id', $context->unit->getKey());
            }
        }

        return [
            'customer_id' => ['required', 'uuid', $customerExists],
            'package_template_id' => ['required', 'uuid', $templateExists],
            'sale_id' => ['nullable', 'uuid', $saleExists],
            'total_sessions' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'expires_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(['pix', 'dinheiro', 'cartao_credito', 'cartao_debito', 'boleto', 'transferencia', 'outros'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.exists' => 'O cliente selecionado não pertence à unidade ativa.',
            'package_template_id.exists' => 'O modelo de pacote não pertence à unidade ativa.',
            'sale_id.exists' => 'A comanda selecionada não pertence à unidade ativa.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('package_template_id')) {
                $templateQuery = PackageTemplate::query()
                    ->whereKey($this->string('package_template_id')->toString());

                $context = $this->attributes->get(TenantContext::class);
                if ($context instanceof TenantContext) {
                    $templateQuery->where('tenant_id', $context->tenant->getKey());

                    if ($context->unit !== null) {
                        $templateQuery->where('unit_id', $context->unit->getKey());
                    }
                }

                $template = $templateQuery->first();
                if ($template !== null && $template->price_cents > 0 && ! $this->filled('payment_method')) {
                    $validator->errors()->add('payment_method', 'Informe a forma de pagamento para pacotes com valor.');
                }
            }

            if (! $this->filled('sale_id') || ! $this->filled('customer_id')) {
                return;
            }

            $query = Sale::query()
                ->whereKey($this->string('sale_id')->toString())
                ->where('customer_id', $this->string('customer_id')->toString());

            $context = $this->attributes->get(TenantContext::class);
            if ($context instanceof TenantContext) {
                $query->where('tenant_id', $context->tenant->getKey());

                if ($context->unit !== null) {
                    $query->where('unit_id', $context->unit->getKey());
                }
            }

            $belongsToCustomer = $query->exists();

            if (! $belongsToCustomer) {
                $validator->errors()->add('sale_id', 'A comanda deve pertencer ao cliente selecionado.');
            }
        }];
    }
}
