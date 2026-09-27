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
            'service_id' => ['nullable', 'uuid', 'required_without:service_ids'],
            'service_ids' => ['nullable', 'array', 'min:1', 'max:8', 'required_without:service_id'],
            'service_ids.*' => ['required', 'uuid', 'distinct'],
            'professional_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'date'],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array{service_id?: string|null, service_ids?: list<string>, professional_id: string, starts_at: string, name: string, phone: string, email?: string|null, notes?: string|null}
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{service_id?: string|null, service_ids?: list<string>, professional_id: string, starts_at: string, name: string, phone: string, email?: string|null, notes?: string|null} */
        return parent::validated($key, $default);
    }
}
