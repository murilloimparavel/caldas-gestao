<?php

namespace App\Policies;

use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

final class IntegrationAdminPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function manageCurrentTenant(User $user, Request $request): bool
    {
        try {
            $context = $request->attributes->get(TenantContext::class);
            $context = $context instanceof TenantContext ? $context : TenantContext::fromRequest($request);

            return $this->allows($user, $context);
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function allows(User $user, TenantContext $context): bool
    {
        if (! $context->user->is($user)) {
            return false;
        }

        try {
            $this->authorization->assertTenantOwner($user, $context, $context->tenant);

            return TenantSubscription::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->latest()
                ->first()?->grantsAccess() === true;
        } catch (AuthorizationException) {
            return false;
        }
    }
}
