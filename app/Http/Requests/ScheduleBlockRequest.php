<?php

namespace App\Http\Requests;

use App\Models\ScheduleBlock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ScheduleBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $block = $this->route('scheduleBlock');

        return $block instanceof ScheduleBlock
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $block)
            : Gate::allows('create', ScheduleBlock::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->isMethod('delete')) {
            return ['lock_version' => ['required', 'integer', 'min:0']];
        }

        return [
            'professional_id' => ['nullable', 'uuid'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'timezone:all'],
            'reason' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'cancelled'])],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }
}
