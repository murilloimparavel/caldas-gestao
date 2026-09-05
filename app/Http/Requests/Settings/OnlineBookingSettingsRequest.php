<?php

namespace App\Http\Requests\Settings;

use App\Models\OnlineBookingSetting;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class OnlineBookingSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'service_ids' => is_array($this->input('service_ids')) ? $this->input('service_ids') : [],
            'professional_ids' => is_array($this->input('professional_ids')) ? $this->input('professional_ids') : [],
            'public_slug' => $this->input('public_slug') ?: ($this->attributes->get(TenantContext::class)?->unit?->slug),
        ]);
    }

    public function authorize(): bool
    {
        $context = $this->attributes->get(TenantContext::class);

        return $context instanceof TenantContext
            && $context->unit instanceof Unit
            && Gate::allows('update', $context->unit);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $context = $this->attributes->get(TenantContext::class);
        $tenantId = $context instanceof TenantContext ? $context->tenant->getKey() : null;
        $unitId = $context instanceof TenantContext ? $context->unit?->getKey() : null;

        return [
            'online_booking_enabled' => ['required', 'boolean'],
            'service_ids' => ['present', 'array', 'max:100'],
            'service_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists(Service::class, 'id')->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('status', 'active'),
            ],
            'professional_ids' => ['present', 'array', 'max:100'],
            'professional_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists(Professional::class, 'id')->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('status', 'active'),
            ],
            'lock_version' => ['required', 'integer', 'min:0'],
            'public_slug' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(OnlineBookingSetting::class, 'public_slug')->ignore($context?->unit?->onlineBookingSetting?->getKey())],
            'description' => ['nullable', 'string', 'max:5000'],
            'whatsapp_phone' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'booking_flow' => ['nullable', Rule::in(['service_first', 'professional_first'])],
            'minimum_notice_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'public_hours' => ['nullable', 'array'],
        ];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array{online_booking_enabled: bool, service_ids: list<string>, professional_ids: list<string>, lock_version: int} */
        return parent::validated($key, $default);
    }
}
