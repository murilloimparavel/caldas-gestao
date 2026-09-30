<?php

namespace App\Http\Requests\Admin;

use App\Rules\UniqueNormalizedEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateTenantRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_name' => $this->input('owner_name', $this->input('name')),
            'owner_email' => $this->input('owner_email', $this->input('email')),
            'owner_password' => $this->input('owner_password'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /** @return array<string, array<int, string|\Stringable|ValidationRule>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('tenants', 'slug')],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email:rfc', 'max:320', new UniqueNormalizedEmail],
            'owner_password' => ['required', 'string', 'min:12'],
            'plan_id' => ['nullable', 'uuid', Rule::exists('platform_plans', 'id')->where('is_active', true)],
        ];
    }
}
