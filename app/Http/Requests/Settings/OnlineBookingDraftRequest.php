<?php

namespace App\Http\Requests\Settings;

use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class OnlineBookingDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $context = $this->attributes->get(TenantContext::class);

        return $context instanceof TenantContext && $context->unit instanceof Unit && Gate::allows('update', $context->unit);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'],
            'content' => ['required', 'array'],
            'content.schema_version' => ['required', 'integer', 'in:1'],
            'content.theme' => ['sometimes', 'array'],
            'content.seo' => ['sometimes', 'array'],
            'content.sections' => ['sometimes', 'array', 'max:20'],
            'content.service_ids' => ['sometimes', 'array', 'max:100'],
            'content.professional_ids' => ['sometimes', 'array', 'max:100'],
            'content.public_hours' => ['sometimes', 'array'],
            'content.booking_policy' => ['sometimes', 'array'],
        ];
    }
}
