<?php

namespace App\Policies;

use App\Models\CustomerSubscription;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class CustomerSubscriptionPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'subscription.view');
    }

    public function view(User $user, CustomerSubscription $subscription): bool
    {
        return $this->allows($user, 'subscription.view', $subscription);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'subscription.subscribe');
    }

    public function cancel(User $user, CustomerSubscription $subscription): bool
    {
        return $this->allows($user, 'subscription.cancel', $subscription);
    }

    public function pause(User $user, CustomerSubscription $subscription): bool
    {
        return $this->allows($user, 'subscription.manage', $subscription);
    }

    public function resume(User $user, CustomerSubscription $subscription): bool
    {
        return $this->allows($user, 'subscription.manage', $subscription);
    }

    private function allows(User $user, string $permission, ?CustomerSubscription $subscription = null): bool
    {
        try {
            $context = $subscription === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $subscription->tenant_id, $subscription->unit_id);

            return $context->user->is($user)
                && ($subscription === null || $context->unit?->is($subscription->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
