<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class PublicBookingAppointmentRequest extends FormRequest
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
            'starts_at' => ['required', 'date'],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array{service_id: string, professional_id: string, starts_at: string, name: string, phone: string}
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{service_id: string, professional_id: string, starts_at: string, name: string, phone: string} */
        return parent::validated($key, $default);
    }
}
