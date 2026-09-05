<?php

namespace App\Http\Requests;

use App\Models\RetentionCampaign;
use App\Policies\RetentionCampaignPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRetentionCampaignStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $campaign = $this->route('retention_campaign');

        return $campaign instanceof RetentionCampaign && app(RetentionCampaignPolicy::class)->update($this->user(), $campaign);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'paused', 'completed'])],
        ];
    }
}
