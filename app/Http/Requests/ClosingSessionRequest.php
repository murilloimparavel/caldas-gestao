<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClosingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['required', 'uuid'],
            'expected_total_cents' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lock_versions' => ['nullable', 'array'],
            'lock_versions.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array{sale_ids: list<string>, expected_total_cents?: int|null, notes?: string|null, lock_versions?: array<string, int>|null}
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{sale_ids: list<string>, expected_total_cents?: int|null, notes?: string|null, lock_versions?: array<string, int>|null} */
        return parent::validated($key, $default);
    }
}
