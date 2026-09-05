<?php

namespace App\Http\Requests;

use App\Policies\RetentionCampaignPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateRetentionCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(RetentionCampaignPolicy::class)->create($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'channel' => ['required', 'string', Rule::in(['email', 'sms', 'whatsapp', 'phone'])],
            'purpose' => ['sometimes', 'string', 'max:64'],
            'segment_definition' => ['required', 'array'],
            'segment_definition.inactive_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'segment_definition.retention_status' => ['sometimes', Rule::in(['none', 'at_risk', 'reactivated'])],
            'subject' => ['nullable', 'string', 'max:240'],
            'message' => ['required', 'string', 'max:10000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
