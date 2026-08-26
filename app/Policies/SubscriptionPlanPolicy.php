<?php

namespace App\Policies;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class SubscriptionPlanPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'subscription.view');
    }

    public function view(User $user, SubscriptionPlan $plan): bool
    {
        return $this->allows($user, 'subscription.view', $plan);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'subscription.manage');
    }

    public function update(User $user, SubscriptionPlan $plan): bool
    {
        return $this->allows($user, 'subscription.manage', $plan);
    }

    public function delete(User $user, SubscriptionPlan $plan): bool
    {
        return $this->allows($user, 'subscription.manage', $plan);
    }

    public function reactivate(User $user, SubscriptionPlan $plan): bool
    {
        return $this->allows($user, 'subscription.manage', $plan);
    }

    private function allows(User $user, string $permission, ?SubscriptionPlan $plan = null): bool
    {
        try {
            $context = $plan === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $plan->tenant_id, $plan->unit_id);

            return $context->user->is($user)
                && ($plan === null || $context->unit?->is($plan->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
