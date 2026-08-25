<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CalendarIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'view' => ['sometimes', Rule::in(['day', 'week', 'month'])],
            'professional_ids' => ['sometimes', 'array'],
            'professional_ids.*' => ['uuid'],
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::in(['draft', 'scheduled', 'confirmed', 'checked_in', 'in_service', 'completed', 'no_show', 'cancelled'])],
        ];
    }
}
