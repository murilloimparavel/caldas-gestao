<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SelectorOptionsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $values = ['resource' => $this->route('resource')];

        if ($this->has('search')) {
            $search = $this->input('search');
            $values['search'] = is_string($search) ? trim($search) : $search;
        }

        $this->merge($values);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'resource' => [
                'required',
                'string',
                Rule::in([
                    'customers',
                    'professionals',
                    'services',
                    'products',
                    'inventory-products',
                    'suppliers',
                    'categories',
                    'sale-categories',
                    'packages',
                    'subscription-plans',
                ]),
            ],
            'search' => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
