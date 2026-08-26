<?php

namespace App\Http\Requests;

use App\Models\FinancialObligation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SettleObligationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $obligation = $this->route('financialObligation');

        return $obligation instanceof FinancialObligation
            ? Gate::allows('settle', $obligation)
            : false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'paid_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:50'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'paid_date.required' => 'A data de liquidação/pagamento é obrigatória.',
            'payment_method.required' => 'A forma de pagamento é obrigatória.',
            'lock_version.required' => 'O controle de versão de concorrência é obrigatório.',
        ];
    }
}
