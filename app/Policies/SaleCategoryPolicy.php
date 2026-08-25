<?php

namespace App\Policies;

use App\Models\SaleCategory;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class SaleCategoryPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'sale_category.view');
    }

    public function view(User $user, SaleCategory $saleCategory): bool
    {
        return $this->allows($user, 'sale_category.view', $saleCategory);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'sale_category.manage');
    }

    public function update(User $user, SaleCategory $saleCategory): bool
    {
        return $this->allows($user, 'sale_category.manage', $saleCategory);
    }

    public function delete(User $user, SaleCategory $saleCategory): bool
    {
        return $this->allows($user, 'sale_category.manage', $saleCategory);
    }

    private function allows(User $user, string $permission, ?SaleCategory $saleCategory = null): bool
    {
        try {
            $context = $saleCategory === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $saleCategory->tenant_id, $saleCategory->unit_id);

            return $context->user->is($user)
                && ($saleCategory === null || $context->unit?->is($saleCategory->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
