<?php

namespace App\Actions\Appointments;

use App\Actions\Operational\OperationalAction;
use App\Actions\Sales\AddSaleItem;
use App\Actions\Sales\OpenSale;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\AppointmentSaleLink;
use App\Models\Membership;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAppointmentSale extends OperationalAction
{
    public function __construct(
        private readonly OpenSale $openSale = new OpenSale,
        private readonly AddSaleItem $addSaleItem = new AddSaleItem,
    ) {
        parent::__construct();
    }

    public function handle(User $actor, TenantContext $context, Appointment $appointment): ?Sale
    {
        $unit = $this->unit($actor, $context, 'calendar.manage');

        if ($appointment->tenant_id !== $context->tenant->getKey() || $appointment->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The appointment belongs to another workspace.');
        }

        if (! $unit->appointment_sales_automation_enabled || $unit->appointment_default_sale_category_id === null) {
            return null;
        }

        $saleSourceId = $this->saleSourceId($context, $unit, $appointment);

        return DB::transaction(function () use ($actor, $context, $appointment, $unit, $saleSourceId): ?Sale {
            $lockedAppointment = Appointment::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var AppointmentSaleLink|null $existingLink */
            $existingLink = AppointmentSaleLink::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('appointment_id', $lockedAppointment->getKey())
                ->with(['sale' => fn ($query) => $query->withTrashed()])
                ->lockForUpdate()
                ->first();

            if ($existingLink !== null) {
                $existingSale = $existingLink->sale;

                if ($existingSale === null) {
                    throw ValidationException::withMessages([
                        'appointment' => 'O vínculo do agendamento aponta para uma comanda inexistente.',
                    ]);
                }

                if ($existingSale->source_id === null) {
                    $existingSale->forceFill(['source_id' => $saleSourceId])->save();
                }

                if ($existingSale->trashed()) {
                    $existingSale->restore();
                }

                return $existingSale->fresh(['items', 'appointmentLink']);
            }

            /** @var SaleCategory|null $category */
            $category = SaleCategory::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($unit->appointment_default_sale_category_id)
                ->first();

            if ($category === null) {
                throw ValidationException::withMessages([
                    'appointment_default_sale_category_id' => 'A categoria padrão da automação não pertence a esta unidade.',
                ]);
            }

            if (! $category->is_active) {
                throw ValidationException::withMessages([
                    'appointment_default_sale_category_id' => 'A categoria padrão da automação está inativa.',
                ]);
            }

            if (! in_array($category->type, ['service', 'mixed'], true)) {
                throw ValidationException::withMessages([
                    'appointment_default_sale_category_id' => 'A categoria padrão da automação deve aceitar serviços.',
                ]);
            }

            $appointmentItems = $lockedAppointment->items()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->orderBy('position')
                ->get();

            if ($appointmentItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'appointment' => 'O agendamento não possui serviços para gerar uma comanda.',
                ]);
            }

            $sale = $this->openSale->handle($actor, $context, [
                'sale_category_id' => $category->getKey(),
                'source_id' => $saleSourceId,
                'customer_id' => $lockedAppointment->customer_id,
                'appointment_id' => $lockedAppointment->getKey(),
                'reference_label' => 'Agendamento '.substr((string) $lockedAppointment->getKey(), 0, 8),
                'source_metadata' => [
                    'origin' => 'appointment_automation',
                    'created_automatically' => true,
                    'appointment_id' => $lockedAppointment->getKey(),
                ],
            ], 'calendar.manage');

            foreach ($appointmentItems as $appointmentItem) {
                $this->addSaleItem->handle($actor, $context, $sale, [
                    'item_type' => 'service',
                    'source_id' => $this->saleItemSourceId($context, $unit, $appointmentItem),
                    'service_id' => $appointmentItem->service_id,
                    'professional_id' => $appointmentItem->professional_id,
                    'name_snapshot' => $appointmentItem->service_name_snapshot,
                    'unit_price_cents' => $appointmentItem->price_cents,
                    'quantity' => 1,
                    'source_metadata' => [
                        'origin' => 'appointment_automation',
                        'created_automatically' => true,
                        'appointment_item_id' => $appointmentItem->getKey(),
                    ],
                ], 'calendar.manage');
            }

            return $sale->fresh(['items', 'appointmentLink']);
        }, 5);
    }

    public function handlePublic(Tenant $tenant, Unit $unit, Appointment $appointment): ?Sale
    {
        $membership = Membership::query()
            ->with('user')
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->whereHas('membershipUnits', fn ($query) => $query
                ->where('unit_id', $unit->getKey())
                ->where('tenant_id', $tenant->getKey()))
            ->first();

        if ($membership?->user === null) {
            return null;
        }

        return $this->handle(
            $membership->user,
            TenantContext::forUser($membership->user, $tenant->getKey(), $unit->getKey()),
            $appointment,
        );
    }

    private function saleSourceId(TenantContext $context, Unit $unit, Appointment $appointment): string
    {
        return sprintf(
            'appointment-automation:%s:%s:%s',
            $context->tenant->getKey(),
            $unit->getKey(),
            $appointment->getKey(),
        );
    }

    private function saleItemSourceId(TenantContext $context, Unit $unit, AppointmentItem $appointmentItem): string
    {
        return sprintf(
            'appointment-automation-item:%s:%s:%s',
            $context->tenant->getKey(),
            $unit->getKey(),
            $appointmentItem->getKey(),
        );
    }
}
