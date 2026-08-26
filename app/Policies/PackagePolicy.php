<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class PackagePolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'package.view');
    }

    public function manage(User $user): bool
    {
        return $this->allows($user, 'package.manage');
    }

    public function sell(User $user): bool
    {
        return $this->allows($user, 'package.sell') || $this->allows($user, 'package.manage');
    }

    public function consume(User $user): bool
    {
        return $this->allows($user, 'package.consume') || $this->allows($user, 'package.manage');
    }

    private function allows(User $user, string $permission): bool
    {
        try {
            $context = app(TenantContext::class);

            return $context->user->is($user)
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
