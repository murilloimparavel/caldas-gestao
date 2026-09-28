<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlatformSubscriptionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('status') === 'past_due') {
            $this->merge(['status' => 'grace']);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', Rule::exists('platform_plans', 'id')->where('is_active', true)],
            'status' => ['required', Rule::in(['trial', 'active', 'grace', 'suspended', 'expired', 'cancelled'])],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'next_billing_at' => ['nullable', 'date'],
            'grace_ends_at' => ['nullable', 'date', 'after_or_equal:ends_at'],
        ];
    }
}
