<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Policies\CustomerRetentionPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class RetentionCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? app(CustomerRetentionPolicy::class)->update($this->user(), $customer)
            : app(CustomerRetentionPolicy::class)->viewAny($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
