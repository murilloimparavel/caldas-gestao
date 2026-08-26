<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $customer)
            : Gate::allows('create', Customer::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->isMethod('delete') || $this->routeIs('*.reactivate')) {
            return ['lock_version' => ['required', 'integer', 'min:0']];
        }

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date', 'before:tomorrow'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }
}
