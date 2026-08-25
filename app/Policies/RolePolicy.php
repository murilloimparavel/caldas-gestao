<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class RolePolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function view(User $user, Role $role): bool
    {
        try {
            return $this->authorization->can($user, TenantContext::forUser($user, $role->tenant_id), 'role.view');
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function update(User $user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        try {
            return $this->authorization->can($user, TenantContext::forUser($user, $role->tenant_id), 'role.update');
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        try {
            return $this->authorization->can($user, TenantContext::forUser($user, $role->tenant_id), 'role.delete');
        } catch (AuthorizationException) {
            return false;
        }
    }
}
