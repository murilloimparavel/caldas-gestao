<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class TenantPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function view(User $user, Tenant $tenant): bool
    {
        try {
            return $this->authorization->can($user, TenantContext::forUser($user, $tenant->getKey()), 'tenant.view');
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function update(User $user, Tenant $tenant): bool
    {
        try {
            return $this->authorization->can($user, TenantContext::forUser($user, $tenant->getKey()), 'tenant.update');
        } catch (AuthorizationException) {
            return false;
        }
    }
}
