<?php

namespace App\Http\Requests;

use App\Models\CustomerPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ArchiveCustomerPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $package = $this->route('customer_package') ?? $this->route('customerPackage');
        if (is_string($package)) {
            $package = CustomerPackage::query()->find($package);
        }

        return $package instanceof CustomerPackage && Gate::allows('archive', $package);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [];
    }
}
