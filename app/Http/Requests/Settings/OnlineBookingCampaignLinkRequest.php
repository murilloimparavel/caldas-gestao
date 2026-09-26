<?php

namespace App\Http\Requests\Settings;

use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class OnlineBookingCampaignLinkRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'utm_source' => ['required', 'string', 'alpha_dash', 'max:100'],
            'utm_medium' => ['required', 'string', 'alpha_dash', 'max:100'],
            'utm_campaign' => ['required', 'string', 'alpha_dash', 'max:150'],
            'utm_term' => ['nullable', 'string', 'alpha_dash', 'max:150'],
            'utm_content' => ['nullable', 'string', 'alpha_dash', 'max:150'],
        ];
    }
}
