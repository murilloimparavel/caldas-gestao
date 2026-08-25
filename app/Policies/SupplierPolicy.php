<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class SupplierPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'supplier.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->allows($user, 'supplier.view', $supplier);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'supplier.manage');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->allows($user, 'supplier.manage', $supplier);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->allows($user, 'supplier.manage', $supplier);
    }

    private function allows(User $user, string $permission, ?Supplier $supplier = null): bool
    {
        try {
            $context = $supplier === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $supplier->tenant_id, $supplier->unit_id);

            return $context->user->is($user)
                && ($supplier === null || $supplier->unit_id === null || $context->unit?->is($supplier->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
