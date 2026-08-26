<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Customer;
use App\Models\FinancialObligation;
use App\Models\Supplier;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class FinancialObligationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $obligation = $this->route('financialObligation');

        return $obligation instanceof FinancialObligation
            ? Gate::allows('update', $obligation)
            : Gate::allows('create', FinancialObligation::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $context = $this->attributes->get(TenantContext::class);
        $tenantId = $context instanceof TenantContext ? $context->tenant->getKey() : null;
        $unitId = $context instanceof TenantContext ? $context->unit?->getKey() : null;

        $categoryExists = Rule::exists(Category::class, 'id');
        $supplierExists = Rule::exists(Supplier::class, 'id');
        $customerExists = Rule::exists(Customer::class, 'id');

        if ($tenantId !== null) {
            $supplierExists->where('tenant_id', $tenantId);
            if ($unitId !== null) {
                $categoryExists->where('tenant_id', $tenantId)->where('unit_id', $unitId);
                $customerExists->where('tenant_id', $tenantId)->where('unit_id', $unitId);
            }
        }

        return [
            'type' => ['required', 'string', 'in:payable,receivable'],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'due_date' => ['required', 'date'],
            'category_id' => ['nullable', 'uuid', $categoryExists],
            'supplier_id' => ['nullable', 'uuid', $supplierExists],
            'customer_id' => ['nullable', 'uuid', $customerExists],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lock_version' => [$this->isMethod('post') ? 'nullable' : 'required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.required' => 'O tipo de lançamento é obrigatório.',
            'type.in' => 'O tipo deve ser "payable" (a pagar) ou "receivable" (a receber).',
            'description.required' => 'A descrição do lançamento é obrigatória.',
            'amount_cents.required' => 'O valor é obrigatório.',
            'amount_cents.min' => 'O valor deve ser maior que zero.',
            'due_date.required' => 'A data de vencimento é obrigatória.',
            'category_id.exists' => 'A categoria selecionada não pertence a esta unidade.',
            'supplier_id.exists' => 'O fornecedor selecionado não pertence a este estabelecimento.',
            'customer_id.exists' => 'O cliente selecionado não pertence a esta unidade.',
        ];
    }
}
