<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\ProfessionalScope;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class SalePolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly ProfessionalScope $professionalScope,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'sale.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->allows($user, 'sale.view', $sale);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'sale.manage');
    }

    public function update(User $user, Sale $sale): bool
    {
        return $this->allows($user, 'sale.manage', $sale);
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $this->allows($user, 'sale.manage', $sale);
    }

    public function discount(User $user, Sale $sale): bool
    {
        return $this->allows($user, 'sale.discount', $sale);
    }

    public function transition(User $user, Sale $sale): bool
    {
        return $this->allows($user, 'sale.manage', $sale);
    }

    public function adjust(User $user, Sale $sale): bool
    {
        return $this->allows($user, 'sale.adjust', $sale) || $this->allows($user, 'sale.manage', $sale);
    }

    private function allows(User $user, string $permission, ?Sale $sale = null): bool
    {
        try {
            $context = $sale === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $sale->tenant_id, $sale->unit_id);

            return $context->user->is($user)
                && ($sale === null || $context->unit?->is($sale->unit))
                && ($sale === null || $this->professionalScope->canViewSale($context, $sale))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
