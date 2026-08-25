<?php

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SaleStatusTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale && Gate::allows('transition', $sale);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'ready_to_bill', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:500'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
