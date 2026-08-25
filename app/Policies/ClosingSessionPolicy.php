<?php

namespace App\Policies;

use App\Models\ClosingSession;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class ClosingSessionPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'sale.view');
    }

    public function view(User $user, ClosingSession $closingSession): bool
    {
        return $this->allows($user, 'sale.view', $closingSession);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'sale.close') || $this->allows($user, 'sale.manage');
    }

    private function allows(User $user, string $permission, ?ClosingSession $session = null): bool
    {
        try {
            $context = $session === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $session->tenant_id, $session->unit_id);

            return $context->user->is($user)
                && ($session === null || $context->unit?->is($session->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
