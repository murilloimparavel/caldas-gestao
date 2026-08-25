<?php

namespace App\Http\Requests;

use App\Models\CashShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class CloseCashShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cashShift = $this->route('cashShift');

        return $cashShift instanceof CashShift && Gate::allows('close', $cashShift);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'final_amount_cents' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lock_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
