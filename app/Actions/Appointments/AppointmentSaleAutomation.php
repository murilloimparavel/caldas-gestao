<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Unit;
use App\Support\TenantContext;

final class AppointmentSaleAutomation
{
    public function saleSourceId(TenantContext $context, Unit $unit, Appointment $appointment): string
    {
        return sprintf(
            'appointment-automation:%s:%s:%s',
            $context->tenant->getKey(),
            $unit->getKey(),
            $appointment->getKey(),
        );
    }

    public function saleItemSourceId(TenantContext $context, Unit $unit, AppointmentItem $appointmentItem): string
    {
        return sprintf(
            'appointment-automation-item:%s:%s:%s',
            $context->tenant->getKey(),
            $unit->getKey(),
            $appointmentItem->getKey(),
        );
    }

    public function isAutomaticSale(Sale $sale): bool
    {
        $metadata = $sale->source_metadata ?? [];

        return ($metadata['origin'] ?? null) === 'appointment_automation'
            && ($metadata['created_automatically'] ?? false) === true;
    }

    public function isAutomaticItem(SaleItem $saleItem): bool
    {
        $metadata = $saleItem->source_metadata ?? [];

        return $saleItem->item_type === 'service'
            && ($metadata['origin'] ?? null) === 'appointment_automation'
            && ($metadata['created_automatically'] ?? false) === true;
    }

    public function isMutableSale(Sale $sale): bool
    {
        return in_array($sale->status, ['draft', 'open'], true);
    }
}
