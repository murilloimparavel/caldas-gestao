<?php

namespace App\Http\Requests;

use App\Models\RetentionCampaign;
use App\Policies\RetentionCampaignPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class RetentionCampaignOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('retention_campaign');

        return $campaign instanceof RetentionCampaign
            && app(RetentionCampaignPolicy::class)->update($this->user(), $campaign);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dry_run' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
