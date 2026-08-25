<?php

namespace App\Policies;

use App\Models\Professional;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class ProfessionalPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'professional.view');
    }

    public function view(User $user, Professional $professional): bool
    {
        return $this->allows($user, 'professional.view', $professional);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'professional.manage');
    }

    public function update(User $user, Professional $professional): bool
    {
        return $this->allows($user, 'professional.manage', $professional);
    }

    public function delete(User $user, Professional $professional): bool
    {
        return $this->allows($user, 'professional.manage', $professional);
    }

    private function allows(User $user, string $permission, ?Professional $professional = null): bool
    {
        try {
            $context = $professional === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $professional->tenant_id, $professional->unit_id);

            return $context->user->is($user)
                && ($professional === null || $context->unit?->is($professional->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
