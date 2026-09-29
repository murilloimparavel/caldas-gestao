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
            'content.schema_version' => ['sometimes', 'integer', 'in:1'],
            'content.theme' => ['sometimes', 'array'],
            'content.seo' => ['sometimes', 'array'],
            'content.identity' => ['sometimes', 'array'],
            'content.identity.cover_image_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content.identity.logo_image_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'content.sections' => ['sometimes', 'array', 'max:20'],
            'content.gallery' => ['sometimes', 'array', 'max:50'],
            'content.gallery.*.path' => ['required_with:content.gallery', 'string', 'max:255'],
            'content.gallery.*.thumbnail_path' => ['nullable', 'string', 'max:255'],
            'content.gallery.*.alt_text' => ['nullable', 'string', 'max:255'],
            'content.service_ids' => ['sometimes', 'array', 'max:100'],
            'content.professional_ids' => ['sometimes', 'array', 'max:100'],
            'content.public_hours' => ['sometimes', 'array'],
            'content.booking_policy' => ['sometimes', 'array'],
            'content.appearance' => ['sometimes', 'array'],
            'content.appearance.brand_name' => ['sometimes', 'string', 'max:80'],
            'content.appearance.headline' => ['sometimes', 'string', 'max:120'],
            'content.appearance.subheadline' => ['sometimes', 'string', 'max:240'],
            'content.appearance.primary_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'content.appearance.background_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'content.appearance.cta_label' => ['sometimes', 'string', 'max:40'],
            'content.appearance.font_style' => ['sometimes', 'string', 'in:editorial,montserrat,modern'],
        ];
    }
}
