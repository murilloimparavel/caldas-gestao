<?php

namespace App\Policies;

use App\Models\AvailabilityRule;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class AvailabilityRulePolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'calendar.view');
    }

    public function view(User $user, AvailabilityRule $availabilityRule): bool
    {
        return $this->allows($user, 'calendar.view', $availabilityRule);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'calendar.configure');
    }

    public function update(User $user, AvailabilityRule $availabilityRule): bool
    {
        return $this->allows($user, 'calendar.configure', $availabilityRule);
    }

    public function delete(User $user, AvailabilityRule $availabilityRule): bool
    {
        return $this->allows($user, 'calendar.configure', $availabilityRule);
    }

    private function allows(User $user, string $permission, ?AvailabilityRule $availabilityRule = null): bool
    {
        try {
            $context = $availabilityRule === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $availabilityRule->tenant_id, $availabilityRule->unit_id);

            return $context->user->is($user)
                && ($availabilityRule === null || $context->unit?->is($availabilityRule->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
