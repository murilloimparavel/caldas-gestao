<?php

namespace App\Http\Requests\Settings;

use App\Models\Professional;
use App\Models\Service;
use App\Models\TenantDomain;
use App\Models\Unit;
use App\Rules\AvailableOnlineBookingHandle;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class OnlineBookingSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $publicSlug = $this->input('public_slug');

        if (is_string($publicSlug) && $publicSlug !== '') {
            $publicSlug = Str::slug($publicSlug);
        } else {
            $publicSlug = $this->attributes->get(TenantContext::class)?->unit?->slug;
        }

        $publicHours = $this->input('public_hours');
        if (is_array($publicHours)) {
            foreach ($publicHours as &$day) {
                if (is_array($day) && array_key_exists('enabled', $day)) {
                    $day['enabled'] = filter_var($day['enabled'], FILTER_VALIDATE_BOOLEAN);
                }
            }
            unset($day);
            $this->merge(['public_hours' => $publicHours]);
        }

        $this->merge([
            'service_ids' => is_array($this->input('service_ids')) ? $this->input('service_ids') : [],
            'professional_ids' => is_array($this->input('professional_ids')) ? $this->input('professional_ids') : [],
            'public_slug' => $publicSlug,
            'template_key' => $this->input('template_key') ?: 'essential',
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
            'draft_revision' => ['sometimes', 'integer', 'min:0'],
            'public_slug' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', new AvailableOnlineBookingHandle($tenantId, $unitId)],
            'public_domain_id' => ['nullable', 'uuid', Rule::exists(TenantDomain::class, 'id')->where('tenant_id', $tenantId)->where('kind', 'public')->where('status', 'active')],
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
            'public_hours.*' => ['array'],
            'public_hours.*.enabled' => ['sometimes', 'boolean'],
            'public_hours.*.starts_at' => ['nullable', 'date_format:H:i'],
            'public_hours.*.ends_at' => ['nullable', 'date_format:H:i'],
            'public_hours.*.display_periods' => ['sometimes', 'array', 'max:4'],
            'public_hours.*.display_periods.*.starts_at' => ['nullable', 'date_format:H:i'],
            'public_hours.*.display_periods.*.ends_at' => ['nullable', 'date_format:H:i'],
            'template_key' => ['required', Rule::in(['essential', 'atelier-barber'])],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('public_hours', []) as $key => $day) {
                if (! is_array($day) || ($day['enabled'] ?? true) === false) {
                    continue;
                }
                $periods = array_key_exists('display_periods', $day) ? $day['display_periods'] : [$day];
                $valid = is_array($periods) && $periods !== [];
                $ranges = [];
                foreach (is_array($periods) ? $periods : [] as $period) {
                    $start = is_array($period) ? ($period['starts_at'] ?? null) : null;
                    $end = is_array($period) ? ($period['ends_at'] ?? null) : null;
                    if (! is_string($start) || ! is_string($end) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) || $start >= $end) {
                        $valid = false;

                        continue;
                    }
                    $ranges[] = [$start, $end];
                }
                usort($ranges, fn (array $left, array $right): int => strcmp($left[0], $right[0]));
                foreach ($ranges as $index => $range) {
                    if ($index > 0 && $range[0] < $ranges[$index - 1][1]) {
                        $valid = false;
                    }
                }
                if (! $valid) {
                    $validator->errors()->add('public_hours.'.$key.'.display_periods', 'Informe períodos completos, com início antes do fim e sem sobreposição.');
                }
            }
        }];
    }

    /**
     * @param  string|null  $key
     * @param  mixed  $default
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        foreach (['lock_version', 'draft_revision', 'minimum_notice_minutes'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }
        if (array_key_exists('online_booking_enabled', $data)) {
            $data['online_booking_enabled'] = filter_var($data['online_booking_enabled'], FILTER_VALIDATE_BOOLEAN);
        }

        return $data;
    }
}
