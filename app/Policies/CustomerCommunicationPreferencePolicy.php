<?php

namespace App\Policies;

use App\Models\CustomerCommunicationPreference;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class CustomerCommunicationPreferencePolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function update(User $user, CustomerCommunicationPreference $preference): bool
    {
        try {
            $context = TenantContext::forUser($user, $preference->tenant_id, $preference->unit_id);

            return $context->user->is($user)
                && $context->unit?->is($preference->unit)
                && $this->authorization->can($user, $context, 'retention.manage', $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
