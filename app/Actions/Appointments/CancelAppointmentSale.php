<?php

namespace App\Actions\Appointments;

use App\Actions\Operational\OperationalAction;
use App\Models\Appointment;
use App\Models\AppointmentSaleLink;
use App\Models\CashMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleStatusHistory;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class CancelAppointmentSale extends OperationalAction
{
    public function __construct(
        private readonly AppointmentSaleAutomation $automation = new AppointmentSaleAutomation,
    ) {
        parent::__construct();
    }

    public function handle(User $actor, TenantContext $context, Appointment $appointment): ?Sale
    {
        $unit = $this->unit($actor, $context, 'calendar.manage');

        if ($appointment->tenant_id !== $context->tenant->getKey() || $appointment->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The appointment belongs to another workspace.');
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

        if ($sale === null || ! $this->automation->isAutomaticSale($sale)) {
            return $sale;
        }

        if ($sale->status === 'cancelled') {
            return $sale->fresh(['items', 'appointmentLink']);
        }

        $reviewReasons = [];

        if (! $this->automation->isMutableSale($sale)) {
            $reviewReasons[] = 'sale_status:'.$sale->status;
        }

        if ($sale->closingSessions()
            ->where('closing_sessions.tenant_id', $sale->tenant_id)
            ->where('closing_sessions.unit_id', $sale->unit_id)
            ->exists()) {
            $reviewReasons[] = 'closing_session';
        }

        if ($this->hasCashMovement($sale)) {
            $reviewReasons[] = 'cash_movement';
        }

        if (SaleItem::query()
            ->where('tenant_id', $sale->tenant_id)
            ->where('unit_id', $sale->unit_id)
            ->where('sale_id', $sale->getKey())
            ->get()
            ->contains(fn (SaleItem $saleItem): bool => ! $this->automation->isAutomaticItem($saleItem))) {
            $reviewReasons[] = 'manual_items';
        }

        if ($reviewReasons !== []) {
            $this->events->record($actor, $context, 'appointment_sale.review_required', $sale, [
                'appointment_id' => $appointment->getKey(),
                'sale_id' => $sale->getKey(),
                'reason_code' => implode(',', $reviewReasons),
                'status' => $sale->status,
            ]);

            $this->events->record($actor, $context, 'appointment.sale_review_required', $appointment, [
                'sale_id' => $sale->getKey(),
                'reason_code' => implode(',', $reviewReasons),
            ]);

            return $sale;
        }

        if ($sale->status !== 'cancelled') {
            SaleStatusHistory::query()->create([
                'id' => (string) Str::uuid7(),
                'sale_id' => $sale->getKey(),
                'from_status' => $sale->status,
                'to_status' => 'cancelled',
                'user_id' => $actor->getKey(),
                'reason' => 'Agendamento cancelado automaticamente.',
            ]);

            $sale->forceFill([
                'status' => 'cancelled',
                'lock_version' => $sale->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'sale.cancelled', $sale, [
                'appointment_id' => $appointment->getKey(),
                'reason' => 'appointment_cancelled',
                'status' => 'cancelled',
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
            ->where(function (Builder $query): void {
                $query->where('reference_type', 'sale')
                    ->orWhere('reference_type', Sale::class)
                    ->orWhere('type', 'sale_inflow');
            })
            ->exists();
    }
}
