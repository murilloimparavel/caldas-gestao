<?php

namespace App\Http\Requests;

use App\Models\SaleCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SaleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $saleCategory = $this->route('sale_category');

        return $saleCategory instanceof SaleCategory
            ? Gate::allows($this->isMethod('delete') ? 'delete' : 'update', $saleCategory)
            : Gate::allows('create', SaleCategory::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->isMethod('delete') || $this->routeIs('*.reactivate')) {
            return ['lock_version' => ['required', 'integer', 'min:0']];
        }

        return [
            'name' => ['required', 'string', 'max:160'],
            'key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'type' => ['required', Rule::in(['service', 'product', 'mixed'])],
            'uniqueness_scope' => ['required', Rule::in(['customer', 'appointment', 'reference', 'none'])],
            'is_active' => ['sometimes', 'boolean'],
            'is_default_for_appointments' => ['sometimes', 'boolean'],
            'appointment_automation_enabled' => ['sometimes', 'boolean'],
            'lock_version' => [$this->isMethod('post') ? 'sometimes' : 'required', 'integer', 'min:0'],
        ];
    }
}
