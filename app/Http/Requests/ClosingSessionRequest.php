<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ClosingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['required', 'uuid'],
            'expected_total_cents' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:pix,debit_card,credit_card,cash,permuta'],
            'payment_allocations' => ['nullable', 'array', 'min:1'],
            'payment_allocations.*.method' => ['required', 'string', 'in:pix,debit_card,credit_card,cash,permuta'],
            'payment_allocations.*.amount_cents' => ['required', 'integer', 'min:1'],
            'payment_allocations.*.tendered_cents' => ['required_if:payment_allocations.*.method,cash', 'nullable', 'integer', 'min:1'],
            'cash_received_cents' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn (): bool => $this->input('payment_method') === 'cash'),
                Rule::prohibitedIf(fn (): bool => $this->input('payment_method') !== 'cash'),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lock_versions' => ['nullable', 'array'],
            'lock_versions.*' => ['integer', 'min:1'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $totalIsZero = (int) $this->input('expected_total_cents', -1) === 0;

            if (! $totalIsZero && ! $this->filled('payment_method') && ! $this->filled('payment_allocations')) {
                $validator->errors()->add('payment_method', 'Informe como a comanda foi paga.');
            }
        }];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array{sale_ids: list<string>, expected_total_cents?: int|null, payment_method?: string|null, payment_allocations?: list<array{method: string, amount_cents: int, tendered_cents?: int|null}>, cash_received_cents?: int|null, notes?: string|null, lock_versions?: array<string, int>|null}
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{sale_ids: list<string>, expected_total_cents?: int|null, payment_method?: string|null, payment_allocations?: list<array{method: string, amount_cents: int, tendered_cents?: int|null}>, cash_received_cents?: int|null, notes?: string|null, lock_versions?: array<string, int>|null} */
        return parent::validated($key, $default);
    }
}
