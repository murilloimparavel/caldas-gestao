<?php

namespace App\Http\Requests;

use App\Models\Professional;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ProfessionalNotificationMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $professional = $this->route('professional');
        $context = app(TenantContext::class);

        return $professional instanceof Professional
            && Gate::allows('update', $professional)
            && app(AuthorizationService::class)->can($this->user(), $context, 'tenant.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['membership_id' => ['present', 'nullable', 'uuid']];
    }
}
