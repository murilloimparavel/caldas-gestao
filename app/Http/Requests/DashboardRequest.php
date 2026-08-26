<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', 'in:today,7d,30d,this_month,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'required_if:preset,custom'],
        ];
    }
}
