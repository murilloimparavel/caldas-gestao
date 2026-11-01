<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TelemetryIncidentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'workflow_status' => ['required', Rule::in(['new', 'triaged', 'in_analysis', 'proposal_ready', 'in_validation', 'resolved', 'ignored'])],
            'owner_user_id' => ['present', 'nullable', 'uuid', function (string $attribute, mixed $value, Closure $fail): void {
                $owner = is_string($value) ? User::query()->whereKey($value)->first() : null;
                if ($value !== null && $owner?->isSuperAdmin() !== true) {
                    $fail('Selecione um administrador da plataforma.');
                }
            }],
            'lock_version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', Rule::in(array_keys((array) config('telemetry.workflow_reasons', [])))],
        ];
    }
}
