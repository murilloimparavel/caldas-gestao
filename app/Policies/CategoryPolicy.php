<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class CategoryPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'category.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $this->allows($user, 'category.view', $category);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'category.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $this->allows($user, 'category.manage', $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->allows($user, 'category.manage', $category);
    }

    private function allows(User $user, string $permission, ?Category $category = null): bool
    {
        try {
            $context = $category === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $category->tenant_id, $category->unit_id);

            return $context->user->is($user)
                && ($category === null || $context->unit?->is($category->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
