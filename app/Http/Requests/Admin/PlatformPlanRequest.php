<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlatformPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /** @return array<string, array<int, string|\Stringable|ValidationRule>> */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('platform_plans', 'key')->ignore($this->route('platform_plan'))],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'features' => ['nullable', 'array'],
            'limits' => ['nullable', 'array'],
        ];
    }
}
