<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class ServicePolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'service.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $this->allows($user, 'service.view', $service);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'service.manage');
    }

    public function update(User $user, Service $service): bool
    {
        return $this->allows($user, 'service.manage', $service);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->allows($user, 'service.manage', $service);
    }

    private function allows(User $user, string $permission, ?Service $service = null): bool
    {
        try {
            $context = $service === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $service->tenant_id, $service->unit_id);

            return $context->user->is($user)
                && ($service === null || $context->unit?->is($service->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
