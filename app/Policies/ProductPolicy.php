<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class ProductPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'product.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->allows($user, 'product.view', $product);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'product.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->allows($user, 'product.manage', $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->allows($user, 'product.manage', $product);
    }

    private function allows(User $user, string $permission, ?Product $product = null): bool
    {
        try {
            $context = $product === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $product->tenant_id, $product->unit_id);

            return $context->user->is($user)
                && ($product === null || $context->unit?->is($product->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
