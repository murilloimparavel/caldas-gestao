<?php

namespace App\Http\Requests;

use App\Models\CustomerPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class CancelCustomerPackageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $package = $this->route('customer_package') ?? $this->route('customerPackage');

        return $package instanceof CustomerPackage && Gate::allows('sell', $package);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
