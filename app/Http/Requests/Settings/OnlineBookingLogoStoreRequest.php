<?php

namespace App\Http\Requests\Settings;

use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class OnlineBookingLogoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = $this->attributes->get(TenantContext::class);

        return $context instanceof TenantContext
            && $context->unit instanceof Unit
            && Gate::allows('update', $context->unit);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
