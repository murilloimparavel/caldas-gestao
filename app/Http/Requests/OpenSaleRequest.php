<?php

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class OpenSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Sale::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sale_category_id' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'appointment_id' => ['nullable', 'uuid'],
            'reference_label' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
