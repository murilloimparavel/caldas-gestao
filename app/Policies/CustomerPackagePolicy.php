<?php

namespace App\Policies;

use App\Models\CustomerPackage;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class CustomerPackagePolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'package.view');
    }

    public function manageAny(User $user): bool
    {
        return $this->allows($user, 'package.manage');
    }

    public function view(User $user, CustomerPackage $customerPackage): bool
    {
        return $this->allows($user, 'package.view', $customerPackage);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'package.sell') || $this->allows($user, 'package.manage');
    }

    public function sell(User $user, ?CustomerPackage $customerPackage = null): bool
    {
        return $this->allows($user, 'package.sell', $customerPackage) || $this->allows($user, 'package.manage', $customerPackage);
    }

    public function consume(User $user, CustomerPackage $customerPackage): bool
    {
        return $this->allows($user, 'package.consume', $customerPackage) || $this->allows($user, 'package.manage', $customerPackage);
    }

    public function archive(User $user, CustomerPackage $customerPackage): bool
    {
        return $this->allows($user, 'package.manage', $customerPackage);
    }

    public function restore(User $user, CustomerPackage $customerPackage): bool
    {
        return $this->allows($user, 'package.manage', $customerPackage);
    }

    private function allows(User $user, string $permission, ?CustomerPackage $customerPackage = null): bool
    {
        try {
            $context = $customerPackage === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $customerPackage->tenant_id, $customerPackage->unit_id);

            return $context->user->is($user)
                && ($customerPackage === null || $context->unit?->is($customerPackage->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
