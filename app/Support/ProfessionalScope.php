<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\ClosingSession;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\ScheduleBlock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies the data boundary created when a tenant membership is linked to a professional.
 */
final class ProfessionalScope
{
    public function professional(TenantContext $context): ?Professional
    {
        $professionalId = $context->membership->professional_id;

        if ($professionalId === null) {
            return null;
        }

        return Professional::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->when($context->unit !== null, fn (Builder $query) => $query->where('unit_id', $context->unit->getKey()))
            ->whereKey($professionalId)
            ->where('status', 'active')
            ->first();
    }

    public function assertProfessional(TenantContext $context, ?string $professionalId = null): ?string
    {
        $linkedProfessionalId = $context->membership->professional_id;

        if ($linkedProfessionalId === null) {
            return $professionalId;
        }

        if ($this->professional($context) === null) {
            throw new AuthorizationException('O profissional vinculado não está ativo nesta unidade.');
        }

        if ($professionalId !== null && $professionalId !== $linkedProfessionalId) {
            throw new AuthorizationException('O colaborador só pode operar como o profissional vinculado.');
        }

        return $linkedProfessionalId;
    }

    public function ownsAppointment(TenantContext $context, Appointment $appointment): bool
    {
        $professional = $this->professional($context);

        return $context->membership->professional_id === null
            || ($professional !== null && $appointment->professional_id === $professional->getKey());
    }

    public function ownsAvailabilityRule(TenantContext $context, AvailabilityRule $rule): bool
    {
        $professional = $this->professional($context);

        return $context->membership->professional_id === null
            || ($professional !== null && $rule->professional_id === $professional->getKey());
    }

    public function ownsScheduleBlock(TenantContext $context, ScheduleBlock $block): bool
    {
        $professional = $this->professional($context);

        return $context->membership->professional_id === null
            || ($professional !== null && $block->professional_id === $professional->getKey());
    }

    public function ownsSale(TenantContext $context, Sale $sale): bool
    {
        $professional = $this->professional($context);

        if ($context->membership->professional_id === null) {
            return true;
        }

        if ($professional === null) {
            return false;
        }

        $professionalId = $professional->getKey();

        if ($sale->professional_id !== null && $sale->professional_id !== $professionalId) {
            return false;
        }

        if ($sale->appointmentLink()
            ->whereHas('appointment', fn (Builder $query) => $query->where('professional_id', '!=', $professionalId))
            ->exists()) {
            return false;
        }

        if ($sale->professional_id === $professionalId) {
            return true;
        }

        if ($sale->items()
            ->where(function (Builder $query) use ($professionalId): void {
                $query->where('professional_id', $professionalId)
                    ->orWhere('seller_professional_id', $professionalId);
            })
            ->exists()) {
            return true;
        }

        return $sale->appointmentLink()
            ->whereHas('appointment', fn (Builder $query) => $query->where('professional_id', $professionalId))
            ->exists();
    }

    public function canViewSale(TenantContext $context, Sale $sale): bool
    {
        if (! $this->ownsSale($context, $sale)) {
            return false;
        }

        $professional = $this->professional($context);

        if ($context->membership->professional_id === null || $professional === null) {
            return $context->membership->professional_id === null;
        }

        return ! $this->hasForeignSaleItems($sale, $professional->getKey());
    }

    public function canMutateSale(TenantContext $context, Sale $sale): bool
    {
        return $this->canViewSale($context, $sale);
    }

    /**
     * @param  Builder<AvailabilityRule>  $query
     * @return Builder<AvailabilityRule>
     */
    public function constrainAvailabilityRules(Builder $query, TenantContext $context): Builder
    {
        $professional = $this->professional($context);

        if ($context->membership->professional_id === null) {
            return $query;
        }

        return $professional === null
            ? $query->whereRaw('1 = 0')
            : $query->where('professional_id', $professional->getKey());
    }

    /**
     * @param  Builder<ScheduleBlock>  $query
     * @return Builder<ScheduleBlock>
     */
    public function constrainScheduleBlocks(Builder $query, TenantContext $context): Builder
    {
        $professional = $this->professional($context);

        if ($context->membership->professional_id === null) {
            return $query;
        }

        return $professional === null
            ? $query->whereRaw('1 = 0')
            : $query->where('professional_id', $professional->getKey());
    }

    public function ownsClosingSession(TenantContext $context, ClosingSession $closingSession): bool
    {
        if ($context->membership->professional_id === null) {
            return true;
        }

        if ($this->professional($context) === null) {
            return false;
        }

        $saleIds = $closingSession->sales()->pluck('sales.id');

        if ($saleIds->isEmpty()) {
            return false;
        }

        $ownedSales = Sale::query()
            ->whereIn('id', $saleIds)
            ->get()
            ->filter(fn (Sale $sale): bool => $this->canViewSale($context, $sale));

        return $ownedSales->count() === $saleIds->count();
    }

    /**
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function constrainAppointments(Builder $query, TenantContext $context): Builder
    {
        $professional = $this->professional($context);

        if ($context->membership->professional_id === null) {
            return $query;
        }

        return $professional === null
            ? $query->whereRaw('1 = 0')
            : $query->where('professional_id', $professional->getKey());
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function constrainCustomers(Builder $query, TenantContext $context): Builder
    {
        $professional = $this->professional($context);

        if ($context->membership->professional_id === null) {
            return $query;
        }

        if ($professional === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('appointments', function (Builder $appointmentQuery) use ($context, $professional): void {
            $appointmentQuery
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->where('professional_id', $professional->getKey());
        });
    }

    /**
     * @param  Builder<Professional>  $query
     * @return Builder<Professional>
     */
    public function constrainProfessionals(Builder $query, TenantContext $context): Builder
    {
        $professional = $this->professional($context);

        if ($context->membership->professional_id === null) {
            return $query;
        }

        return $professional === null
            ? $query->whereRaw('1 = 0')
            : $query->whereKey($professional->getKey());
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public function constrainSales(Builder $query, TenantContext $context): Builder
    {
        $professional = $this->professional($context);

        if ($context->membership->professional_id === null) {
            return $query;
        }

        if ($professional === null) {
            return $query->whereRaw('1 = 0');
        }

        $professionalId = $professional->getKey();

        return $query
            ->whereDoesntHave('items', function (Builder $itemsQuery) use ($professionalId): void {
                $itemsQuery->where(function (Builder $query) use ($professionalId): void {
                    $query->where(function (Builder $professionalQuery) use ($professionalId): void {
                        $professionalQuery->whereNotNull('professional_id')
                            ->where('professional_id', '!=', $professionalId);
                    })->orWhere(function (Builder $sellerQuery) use ($professionalId): void {
                        $sellerQuery->whereNotNull('seller_professional_id')
                            ->where('seller_professional_id', '!=', $professionalId);
                    });
                });
            })
            ->where(function (Builder $saleQuery) use ($professionalId): void {
                $saleQuery
                    ->where('professional_id', $professionalId)
                    ->orWhere(function (Builder $legacyQuery) use ($professionalId): void {
                        $legacyQuery->whereNull('professional_id')
                            ->where(function (Builder $ownedQuery) use ($professionalId): void {
                                $ownedQuery->whereHas('items', function (Builder $itemsQuery) use ($professionalId): void {
                                    $itemsQuery->where(function (Builder $query) use ($professionalId): void {
                                        $query->where('professional_id', $professionalId)
                                            ->orWhere('seller_professional_id', $professionalId);
                                    });
                                })->orWhereHas('appointmentLink.appointment', fn (Builder $appointmentQuery) => $appointmentQuery->where('professional_id', $professionalId));
                            });
                    });
            });
    }

    private function hasForeignSaleItems(Sale $sale, string $professionalId): bool
    {
        return $sale->items()
            ->where(function (Builder $query) use ($professionalId): void {
                $query->where(function (Builder $professionalQuery) use ($professionalId): void {
                    $professionalQuery->whereNotNull('professional_id')
                        ->where('professional_id', '!=', $professionalId);
                })->orWhere(function (Builder $sellerQuery) use ($professionalId): void {
                    $sellerQuery->whereNotNull('seller_professional_id')
                        ->where('seller_professional_id', '!=', $professionalId);
                });
            })
            ->exists();
    }
}
