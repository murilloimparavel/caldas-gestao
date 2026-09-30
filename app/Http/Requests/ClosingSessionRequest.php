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
            'payment_method' => ['required_without:payment_allocations', 'nullable', 'string', 'in:pix,debit_card,credit_card,cash,permuta'],
            'payment_allocations' => ['required_without:payment_method', 'array', 'min:1'],
            'payment_allocations.*.method' => ['required', 'string', 'in:pix,debit_card,credit_card,cash,permuta'],
            'payment_allocations.*.amount_cents' => ['required', 'integer', 'min:1'],
            'payment_allocations.*.tendered_cents' => ['required_if:payment_allocations.*.method,cash', 'nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lock_versions' => ['nullable', 'array'],
            'lock_versions.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array{sale_ids: list<string>, expected_total_cents?: int|null, payment_method?: string|null, notes?: string|null, lock_versions?: array<string, int>|null}
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{sale_ids: list<string>, expected_total_cents?: int|null, payment_method?: string|null, notes?: string|null, lock_versions?: array<string, int>|null} */
        return parent::validated($key, $default);
    }
}
