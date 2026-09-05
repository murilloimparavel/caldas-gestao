<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Policies\CustomerRetentionPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCustomerCommunicationPreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && app(CustomerRetentionPolicy::class)->update($this->user(), $customer);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in(['email', 'sms', 'whatsapp', 'phone'])],
            'opted_in' => ['required', 'boolean'],
            'source' => ['sometimes', 'string', Rule::in(['staff', 'customer', 'import', 'system'])],
        ];
    }
}
