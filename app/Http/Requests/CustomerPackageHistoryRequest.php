<?php

namespace App\Http\Requests;

use App\Models\CustomerPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class CustomerPackageHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customerPackage = $this->route('customer_package');

        return $customerPackage instanceof CustomerPackage
            && Gate::allows('view', $customerPackage);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'service_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(['all', 'consumed', 'reversed'])],
        ];
    }
}
