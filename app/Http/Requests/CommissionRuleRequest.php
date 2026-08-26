<?php

namespace App\Http\Requests;

use App\Models\CommissionRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class CommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('rule');

        return $rule instanceof CommissionRule
            ? Gate::allows('update', $rule)
            : Gate::allows('create', CommissionRule::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'professional_id' => ['nullable', 'uuid', 'exists:professionals,id'],
            'service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'type' => ['required', 'string', 'in:percentage,fixed'],
            'value_rate' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array{
     *     professional_id?: string|null,
     *     service_id?: string|null,
     *     product_id?: string|null,
     *     type?: string,
     *     value_rate: int,
     *     is_active?: bool,
     *     lock_version?: int
     * }
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{professional_id?: string|null, service_id?: string|null, product_id?: string|null, type?: string, value_rate: int, is_active?: bool, lock_version?: int} */
        return parent::validated($key, $default);
    }
}
