<?php

namespace App\Actions\Appointments;

use App\Actions\Operational\OperationalAction;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\AppointmentSaleLink;
use App\Models\CashMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\TenantContext;

final class SynchronizeAppointmentSale extends OperationalAction
{
    public function __construct(
        private readonly AppointmentSaleAutomation $automation = new AppointmentSaleAutomation,
    ) {
        parent::__construct();
    }

    public function handle(User $actor, TenantContext $context, Appointment $appointment, AppointmentItem $appointmentItem): ?Sale
    {
        $unit = $context->unit;

        if ($unit === null) {
            return null;
        }

        /** @var AppointmentSaleLink|null $link */
        $link = AppointmentSaleLink::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->where('appointment_id', $appointment->getKey())
            ->lockForUpdate()
            ->first();

        if ($link === null) {
            return null;
        }

        /** @var Sale|null $sale */
        $sale = Sale::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->whereKey($link->sale_id)
            ->lockForUpdate()
            ->first();

        if ($sale === null || ! $this->automation->isAutomaticSale($sale) || ! $this->automation->isMutableSale($sale)) {
            return $sale;
        }

        if ($sale->closingSessions()->exists() || $this->hasCashMovement($sale)) {
            return $sale;
        }

        $sourceId = $this->automation->saleItemSourceId($context, $unit, $appointmentItem);

        /** @var SaleItem|null $saleItem */
        $saleItem = SaleItem::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->where('sale_id', $sale->getKey())
            ->where('source_id', $sourceId)
            ->lockForUpdate()
            ->first();

        if ($saleItem === null || ! $this->automation->isAutomaticItem($saleItem)) {
            return $sale;
        }

        $discountCents = min((int) $saleItem->discount_cents, (int) $appointmentItem->price_cents);
        $totalCents = max(0, (int) $appointmentItem->price_cents - $discountCents);
        $metadata = array_merge($saleItem->source_metadata ?? [], [
            'origin' => 'appointment_automation',
            'created_automatically' => true,
            'appointment_item_id' => $appointmentItem->getKey(),
        ]);

        $attributes = [
            'service_id' => $appointmentItem->service_id,
            'professional_id' => $appointmentItem->professional_id,
            'name_snapshot' => $appointmentItem->service_name_snapshot,
            'unit_price_cents' => $appointmentItem->price_cents,
            'quantity' => 1,
            'discount_cents' => $discountCents,
            'total_cents' => $totalCents,
            'source_metadata' => $metadata,
        ];

        if ($saleItem->isDirty(array_keys($attributes))) {
            $saleItem->forceFill($attributes)->save();

            $totalAmountCents = (int) $sale->items()->sum('total_cents');
            $sale->forceFill([
                'total_amount_cents' => $totalAmountCents,
                'final_amount_cents' => max(0, $totalAmountCents - (int) $sale->discount_amount_cents),
                'lock_version' => $sale->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'appointment_sale.item_synchronized', $sale, [
                'appointment_id' => $appointment->getKey(),
                'appointment_item_id' => $appointmentItem->getKey(),
                'sale_item_id' => $saleItem->getKey(),
                'lock_version' => $sale->lock_version,
            ]);
        }

        return $sale->fresh(['items', 'appointmentLink']);
    }

    private function hasCashMovement(Sale $sale): bool
    {
        return CashMovement::query()
            ->where('tenant_id', $sale->tenant_id)
            ->where('unit_id', $sale->unit_id)
            ->where('reference_id', $sale->getKey())
            ->where(function ($query): void {
                $query->where('reference_type', 'sale')
                    ->orWhere('reference_type', Sale::class)
                    ->orWhere('type', 'sale_inflow');
            })
            ->exists();
    }
}
