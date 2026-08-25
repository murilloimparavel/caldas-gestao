<?php

namespace App\Http\Requests;

use App\Models\CashShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class OpenCashShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', CashShift::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'initial_amount_cents' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
