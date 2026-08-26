<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $category)
            : Gate::allows('create', Category::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->isMethod('delete') || $this->routeIs('*.reactivate')) {
            return ['lock_version' => ['required', 'integer', 'min:0']];
        }

        return [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['service', 'product', 'general'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }
}
