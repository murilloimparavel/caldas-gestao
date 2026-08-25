<?php

namespace App\Policies;

use App\Models\InventoryMovement;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class InventoryPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'inventory.view');
    }

    public function view(User $user, ?InventoryMovement $movement = null): bool
    {
        return $this->allows($user, 'inventory.view', $movement);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'inventory.manage');
    }

    public function manage(User $user): bool
    {
        return $this->allows($user, 'inventory.manage');
    }

    private function allows(User $user, string $permission, ?InventoryMovement $movement = null): bool
    {
        try {
            $context = $movement === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $movement->tenant_id, $movement->unit_id);

            return $context->user->is($user)
                && ($movement === null || $context->unit?->is($movement->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
