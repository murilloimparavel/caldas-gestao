<?php

namespace App\Http\Requests;

use App\Models\CashShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cashShift = $this->route('cashShift');

        return $cashShift instanceof CashShift && Gate::allows('move', $cashShift);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:supply,bleed,sale_inflow,commission_outflow,expense_outflow'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:64'],
            'reference_id' => ['nullable', 'string', 'max:64'],
            'lock_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
