<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'uuid', Rule::exists('platform_plans', 'id')->where('is_active', true)],
            'status' => ['required', Rule::in(['trial', 'active', 'grace', 'suspended', 'expired', 'cancelled'])],
        ];
    }
}
