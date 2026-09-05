<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\LegalHold;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class LegalHoldPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->allows($user, null);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LegalHold $legalHold): bool
    {
        return $this->allows($user, $legalHold);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Customer $customer = null): bool
    {
        return $this->allows($user, $customer);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LegalHold $legalHold): bool
    {
        return $this->allows($user, $legalHold);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LegalHold $legalHold): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LegalHold $legalHold): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LegalHold $legalHold): bool
    {
        return false;
    }

    public function release(User $user, LegalHold $legalHold): bool
    {
        return $this->allows($user, $legalHold);
    }

    private function allows(User $user, Customer|LegalHold|null $resource): bool
    {
        try {
            $context = match (true) {
                $resource instanceof Customer => TenantContext::forUser($user, $resource->tenant_id, $resource->unit_id),
                $resource instanceof LegalHold => TenantContext::forUser($user, $resource->tenant_id, $resource->unit_id),
                default => app(TenantContext::class),
            };

            return $context->user->is($user)
                && $context->unit !== null
                && $this->authorization->can($user, $context, 'retention.legal_hold', $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
