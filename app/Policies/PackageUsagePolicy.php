<?php

namespace App\Policies;

use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class PackageUsagePolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function reverse(User $user, PackageUsage $packageUsage): bool
    {
        try {
            $package = CustomerPackage::query()->find($packageUsage->customer_package_id);

            if ($package === null) {
                return false;
            }

            $context = TenantContext::forUser($user, $package->tenant_id, $package->unit_id);

            return $context->user->is($user)
                && ($this->authorization->can($user, $context, 'package.consume', $context->unit)
                    || $this->authorization->can($user, $context, 'package.manage', $context->unit));
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
