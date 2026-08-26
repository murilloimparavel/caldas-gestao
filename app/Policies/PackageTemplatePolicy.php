<?php

namespace App\Policies;

use App\Models\PackageTemplate;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class PackageTemplatePolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'package.view');
    }

    public function view(User $user, PackageTemplate $packageTemplate): bool
    {
        return $this->allows($user, 'package.view', $packageTemplate);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'package.manage');
    }

    public function update(User $user, PackageTemplate $packageTemplate): bool
    {
        return $this->allows($user, 'package.manage', $packageTemplate);
    }

    public function delete(User $user, PackageTemplate $packageTemplate): bool
    {
        return $this->allows($user, 'package.manage', $packageTemplate);
    }

    public function reactivate(User $user, PackageTemplate $packageTemplate): bool
    {
        return $this->allows($user, 'package.manage', $packageTemplate);
    }

    private function allows(User $user, string $permission, ?PackageTemplate $packageTemplate = null): bool
    {
        try {
            $context = $packageTemplate === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $packageTemplate->tenant_id, $packageTemplate->unit_id);

            return $context->user->is($user)
                && ($packageTemplate === null || $context->unit?->is($packageTemplate->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
