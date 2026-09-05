<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\LegalHold;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateLegalHoldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && Gate::allows('create', [LegalHold::class, $customer]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:160'],
        ];
    }
}
