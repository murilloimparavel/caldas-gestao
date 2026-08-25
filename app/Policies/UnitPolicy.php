<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class UnitPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function view(User $user, Unit $unit): bool
    {
        try {
            $context = TenantContext::forUser($user, $unit->tenant_id);

            return $this->authorization->can($user, $context, 'unit.view', $unit);
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function update(User $user, Unit $unit): bool
    {
        try {
            $context = TenantContext::forUser($user, $unit->tenant_id);

            return $this->authorization->can($user, $context, 'unit.update', $unit);
        } catch (AuthorizationException) {
            return false;
        }
    }
}
