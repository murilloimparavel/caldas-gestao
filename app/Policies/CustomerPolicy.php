<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class CustomerPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'customer.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->allows($user, 'customer.view', $customer);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'customer.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->allows($user, 'customer.manage', $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->allows($user, 'customer.manage', $customer);
    }

    private function allows(User $user, string $permission, ?Customer $customer = null): bool
    {
        try {
            $context = $customer === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $customer->tenant_id, $customer->unit_id);

            return $context->user->is($user)
                && ($customer === null || $context->unit?->is($customer->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
