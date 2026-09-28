<?php

namespace App\Http\Requests\Admin;

use App\Rules\UniqueNormalizedEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlatformUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        logger()->debug('platform user authorization', ['user_id' => $this->user()?->getKey(), 'is_super_admin' => $this->user()?->isSuperAdmin()]);

        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:320', new UniqueNormalizedEmail],
            'role' => ['required', Rule::in(['owner', 'staff'])],
        ];
    }
}
