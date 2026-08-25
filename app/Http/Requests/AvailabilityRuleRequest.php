<?php

namespace App\Http\Requests;

use App\Models\AvailabilityRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AvailabilityRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('availabilityRule');

        return $rule instanceof AvailabilityRule
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $rule)
            : Gate::allows('create', AvailabilityRule::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->isMethod('delete')) {
            return ['lock_version' => ['required', 'integer', 'min:0']];
        }

        return [
            'professional_id' => ['required', 'uuid'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'starts_at' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'ends_at' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'timezone' => ['required', 'timezone:all'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $startsAt = $this->input('starts_at');
            $endsAt = $this->input('ends_at');

            if (is_string($startsAt) && is_string($endsAt) && $endsAt <= $startsAt) {
                $validator->errors()->add('ends_at', 'The end time must be after the start time.');
            }
        }];
    }
}
