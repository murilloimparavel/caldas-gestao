<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class PublicBookingAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'uuid'],
            'professional_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before:'.now()->addDays(31)->toDateString()],
            'from' => ['sometimes', 'date_format:H:i'],
            'to' => ['sometimes', 'date_format:H:i'],
        ];
    }
}
