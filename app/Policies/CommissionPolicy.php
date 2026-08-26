<?php

namespace App\Policies;

use App\Models\CommissionRule;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class CommissionPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'commission.view');
    }

    public function view(User $user, ?CommissionRule $rule = null): bool
    {
        return $this->allows($user, 'commission.view', $rule);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'commission.manage');
    }

    public function update(User $user, ?CommissionRule $rule = null): bool
    {
        return $this->allows($user, 'commission.manage', $rule);
    }

    public function delete(User $user, ?CommissionRule $rule = null): bool
    {
        return $this->allows($user, 'commission.manage', $rule);
    }

    public function settle(User $user): bool
    {
        return $this->allows($user, 'commission.settle');
    }

    private function allows(User $user, string $permission, ?CommissionRule $rule = null): bool
    {
        try {
            $context = $rule === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $rule->tenant_id, $rule->unit_id);

            return $context->user->is($user)
                && ($rule === null || $context->unit?->is($rule->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
