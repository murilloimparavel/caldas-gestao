<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TelemetryIncidentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'classification' => ['nullable', Rule::in(array_keys((array) config('telemetry.classifications', [])))],
            'module' => ['nullable', Rule::in(array_keys((array) config('telemetry.modules', [])))],
            'severity' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low'])],
            'workflow_status' => ['nullable', Rule::in(['new', 'triaged', 'in_analysis', 'proposal_ready', 'in_validation', 'resolved', 'ignored'])],
            'period' => ['nullable', Rule::in(['1', '7', '30', '90'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
