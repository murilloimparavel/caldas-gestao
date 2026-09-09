<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            ? Gate::allows('update', $appointment)
            : Gate::allows('create', Appointment::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reminder_enabled')) {
            $this->merge([
                'reminder_enabled' => $this->boolean('reminder_enabled'),
            ]);
        }

        if ($this->has('fit_in')) {
            $this->merge([
                'fit_in' => $this->boolean('fit_in'),
            ]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'service_id' => ['required', 'uuid'],
            'professional_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
            'timezone' => ['sometimes', 'timezone:all'],
            'status' => ['sometimes', Rule::in(['draft', 'scheduled', 'confirmed', 'checked_in', 'in_service', 'completed', 'no_show'])],
            'source' => ['sometimes', Rule::in(['internal', 'online', 'imported'])],
            'color' => ['nullable', 'string', 'max:32'],
            'reminder_enabled' => ['sometimes', 'boolean'],
            'fit_in' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
            'recurrence' => ['prohibited'],
        ];
    }
}
