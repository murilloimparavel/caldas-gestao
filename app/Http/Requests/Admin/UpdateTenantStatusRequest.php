<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantStatusRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isSuperAdmin() === true; }

    public function rules(): array { return ['status' => ['required', Rule::in(['active', 'suspended', 'closed'])]]; }
}
