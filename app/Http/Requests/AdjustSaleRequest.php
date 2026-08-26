<?php

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class AdjustSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale && Gate::allows('adjust', $sale);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
