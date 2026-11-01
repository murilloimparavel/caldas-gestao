<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'new_appointments' => ['required', 'boolean'],
            'changes' => ['required', 'boolean'],
            'cancellations' => ['required', 'boolean'],
            'reminders' => ['required', 'boolean'],
            'reminder_minutes' => ['required', 'integer', Rule::in([5, 10, 15, 30, 60])],
            'quiet_start' => ['nullable', 'required_with:quiet_end', 'date_format:H:i'],
            'quiet_end' => ['nullable', 'required_with:quiet_start', 'date_format:H:i', 'different:quiet_start'],
        ];
    }
}
