<?php

namespace App\Policies;

use App\Models\FinancialObligation;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class FinancialObligationPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'financial.view');
    }

    public function view(User $user, FinancialObligation $obligation): bool
    {
        return $this->allows($user, 'financial.view', $obligation);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'financial.manage');
    }

    public function update(User $user, FinancialObligation $obligation): bool
    {
        return $this->allows($user, 'financial.manage', $obligation);
    }

    public function cancel(User $user, FinancialObligation $obligation): bool
    {
        return $this->allows($user, 'financial.manage', $obligation);
    }

    public function settle(User $user, FinancialObligation $obligation): bool
    {
        return $this->allows($user, 'financial.settle', $obligation);
    }

    public function delete(User $user, FinancialObligation $obligation): bool
    {
        return $this->allows($user, 'financial.manage', $obligation);
    }

    private function allows(User $user, string $permission, ?FinancialObligation $obligation = null): bool
    {
        try {
            $context = $obligation === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $obligation->tenant_id, $obligation->unit_id);

            return $context->user->is($user)
                && ($obligation === null || $context->unit?->is($obligation->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
