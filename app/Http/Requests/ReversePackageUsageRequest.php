<?php

namespace App\Http\Requests;

use App\Models\PackageUsage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ReversePackageUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $packageUsage = $this->route('package_usage') ?? $this->route('packageUsage');

        return $packageUsage instanceof PackageUsage && Gate::allows('reverse', $packageUsage);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
