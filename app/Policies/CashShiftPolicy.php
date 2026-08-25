<?php

namespace App\Policies;

use App\Models\CashShift;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class CashShiftPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'cash_shift.view');
    }

    public function view(User $user, CashShift $cashShift): bool
    {
        return $this->allows($user, 'cash_shift.view', $cashShift);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'cash_shift.open');
    }

    public function move(User $user, CashShift $cashShift): bool
    {
        return $this->allows($user, 'cash_shift.move', $cashShift);
    }

    public function close(User $user, CashShift $cashShift): bool
    {
        return $this->allows($user, 'cash_shift.close', $cashShift);
    }

    private function allows(User $user, string $permission, ?CashShift $cashShift = null): bool
    {
        try {
            $context = $cashShift === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $cashShift->tenant_id, $cashShift->unit_id);

            return $context->user->is($user)
                && ($cashShift === null || $context->unit?->is($cashShift->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
