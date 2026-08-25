<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class MembershipPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function view(User $user, Membership $membership): bool
    {
        try {
            return $this->authorization->can($user, TenantContext::forUser($user, $membership->tenant_id), 'membership.view');
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function revoke(User $user, Membership $membership): bool
    {
        try {
            return $this->authorization->can($user, TenantContext::forUser($user, $membership->tenant_id), 'membership.revoke');
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function assignRole(User $user, Membership $membership): bool
    {
        try {
            return $this->authorization->can($user, TenantContext::forUser($user, $membership->tenant_id), 'membership.assign_role');
        } catch (AuthorizationException) {
            return false;
        }
    }
}
