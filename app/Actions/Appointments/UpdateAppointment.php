<?php

namespace App\Actions\Appointments;

use App\Actions\Marketing\Retention\RecordCustomerActivity;
use App\Actions\Operational\OperationalAction;
use App\Jobs\SyncGoogleCalendarAppointment;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\AppointmentSaleLink;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Support\AppointmentStatusTransition;
use App\Support\CalendarAvailability;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateAppointment extends OperationalAction
{
    public function __construct(private readonly CalendarAvailability $availability, private readonly AppointmentStatusTransition $transitions = new AppointmentStatusTransition, private readonly RecordCustomerActivity $recordCustomerActivity = new RecordCustomerActivity)
    {
        parent::__construct();
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Appointment $appointment, array $data): Appointment
    {
        $unit = $this->unit($actor, $context, 'calendar.manage');
        if ($appointment->tenant_id !== $context->tenant->getKey() || $appointment->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The appointment belongs to another workspace.');
        }
        $this->assertOwnAppointment($context, $appointment);
        $expectedVersion = (int) ($data['lock_version'] ?? -1);
        $service = Service::query()->whereKey($data['service_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        Customer::query()->whereKey($data['customer_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        $professional = Professional::query()->whereKey($data['professional_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        $this->assertProfessional($context, (string) $professional->getKey());
        if (! $professional->services()->whereKey($service->getKey())->wherePivot('tenant_id', $context->tenant->getKey())->wherePivot('unit_id', $unit->getKey())->exists()) {
            throw ValidationException::withMessages(['service_id' => 'The selected professional does not provide this service in the active unit.']);
        }
        $duration = (int) ($data['duration_minutes'] ?? $service->duration_minutes);
        $timezone = (string) ($unit->timezone ?? config('app.timezone'));
        $startsAt = CarbonImmutable::parse((string) $data['starts_at'], $timezone);
        $endsAt = $startsAt->addMinutes($duration);

        return DB::transaction(function () use ($actor, $context, $appointment, $data, $service, $expectedVersion, $startsAt, $endsAt, $timezone, $duration, $unit): Appointment {
            $locked = Appointment::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The appointment was modified concurrently.');
            }
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['appointment' => 'Cancelled appointments cannot be edited.']);
            }
            $this->availability->assertAvailable((string) $locked->tenant_id, (string) $locked->unit_id, (string) $data['professional_id'], $startsAt, $endsAt, (string) $locked->getKey());
            $fromStatus = $locked->status;
            $nextStatus = (string) ($data['status'] ?? $locked->status);
            $this->transitions->assertCanTransition($fromStatus, $nextStatus);
            $locked->forceFill(['customer_id' => $data['customer_id'], 'professional_id' => $data['professional_id'], 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'timezone' => $timezone, 'status' => $nextStatus, 'source' => $data['source'] ?? $locked->source, 'color' => $data['color'] ?? $locked->color, 'reminder_enabled' => $data['reminder_enabled'] ?? $locked->reminder_enabled, 'fit_in' => $data['fit_in'] ?? $locked->fit_in, 'notes' => $data['notes'] ?? $locked->notes, 'lock_version' => $locked->lock_version + 1])->save();
            $appointmentItem = AppointmentItem::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('unit_id', $locked->unit_id)
                ->where('appointment_id', $locked->getKey())
                ->where('position', 1)
                ->lockForUpdate()
                ->first();
            $appointmentItemAttributes = [
                'tenant_id' => $locked->tenant_id,
                'unit_id' => $locked->unit_id,
                'service_id' => $service->getKey(),
                'professional_id' => $locked->professional_id,
                'service_name_snapshot' => $service->name,
                'duration_minutes' => $duration,
                'price_cents' => $service->price_cents,
                'currency' => $appointmentItem === null ? 'BRL' : ($appointmentItem->currency ?? 'BRL'),
            ];

            if ($appointmentItem === null) {
                $appointmentItem = AppointmentItem::query()->create([
                    'id' => (string) Str::uuid7(),
                    'appointment_id' => $locked->getKey(),
                    ...$appointmentItemAttributes,
                ]);
            } else {
                $appointmentItem->forceFill($appointmentItemAttributes)->save();
            }
            $this->syncAutomaticSaleItem($actor, $context, $locked, $appointmentItem, $service, $unit->getKey());
            if ($fromStatus !== $locked->status) {
                $locked->statusHistories()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $locked->tenant_id, 'unit_id' => $locked->unit_id, 'actor_user_id' => $actor->getKey(), 'action' => 'status_changed', 'from_status' => $fromStatus, 'to_status' => $locked->status, 'occurred_at' => now()]);
            }
            $this->events->record($actor, $context, 'appointment.updated', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);
            SyncGoogleCalendarAppointment::dispatch((string) $locked->getKey())->afterCommit();

            if ($fromStatus !== 'completed' && $locked->status === 'completed') {
                $this->recordCustomerActivity->handle($actor, $context, (string) $locked->customer_id, 'appointment.completed');
            }

            return $locked->fresh(['items.service', 'customer', 'professional']);
        }, 5);
    }

    private function syncAutomaticSaleItem(User $actor, TenantContext $context, Appointment $appointment, AppointmentItem $appointmentItem, Service $service, string $unitId): void
    {
        $link = AppointmentSaleLink::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unitId)
            ->where('appointment_id', $appointment->getKey())
            ->lockForUpdate()
            ->first();

        if ($link === null) {
            return;
        }

        $sale = Sale::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unitId)
            ->whereKey($link->sale_id)
            ->lockForUpdate()
            ->first();

        if ($sale === null || $sale->status !== 'open' || ! $this->isAutomaticSale($sale)) {
            return;
        }

        $sourceId = $this->saleItemSourceId($context, $unitId, $appointmentItem);
        $saleItem = SaleItem::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unitId)
            ->where('sale_id', $sale->getKey())
            ->where(function ($query) use ($sourceId, $appointmentItem): void {
                $query->where('source_id', $sourceId)
                    ->orWhereJsonContains('source_metadata->appointment_item_id', (string) $appointmentItem->getKey());
            })
            ->lockForUpdate()
            ->get()
            ->first(fn (SaleItem $item): bool => $this->isAutomaticSaleItemFor($item, $appointmentItem, $sourceId));

        if ($saleItem === null) {
            return;
        }

        $attributes = [
            'service_id' => $service->getKey(),
            'professional_id' => $appointment->professional_id,
            'name_snapshot' => $service->name,
            'unit_price_cents' => $service->price_cents,
            'total_cents' => $service->price_cents,
        ];

        $saleItem->forceFill($attributes);

        if ($saleItem->isDirty(array_keys($attributes))) {
            $saleItem->save();
            $totalAmountCents = (int) $sale->items()->sum('total_cents');
            $sale->forceFill([
                'total_amount_cents' => $totalAmountCents,
                'final_amount_cents' => max(0, $totalAmountCents - (int) $sale->discount_amount_cents),
                'lock_version' => $sale->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'sale.appointment_item_synced', $sale, [
                'appointment_id' => $appointment->getKey(),
                'sale_id' => $sale->getKey(),
                'sale_item_id' => $saleItem->getKey(),
                'service_id' => $service->getKey(),
                'professional_id' => $appointment->professional_id,
                'unit_price_cents' => $saleItem->unit_price_cents,
                'quantity' => $saleItem->quantity,
                'total_cents' => $saleItem->total_cents,
                'lock_version' => $sale->lock_version,
            ]);
        }
    }

    private function isAutomaticSale(Sale $sale): bool
    {
        $metadata = $sale->source_metadata;

        return Str::startsWith((string) $sale->source_id, 'appointment-automation:')
            || (is_array($metadata) && (($metadata['origin'] ?? null) === 'appointment_automation' || ($metadata['created_automatically'] ?? false) === true));
    }

    private function isAutomaticSaleItemFor(SaleItem $saleItem, AppointmentItem $appointmentItem, string $sourceId): bool
    {
        $metadata = $saleItem->source_metadata;
        $hasAutomationMarker = Str::startsWith((string) $saleItem->source_id, 'appointment-automation-item:')
            || (is_array($metadata) && (($metadata['origin'] ?? null) === 'appointment_automation' || ($metadata['created_automatically'] ?? false) === true));
        $hasAppointmentItemIdentity = $saleItem->source_id === $sourceId
            || (is_array($metadata) && (string) ($metadata['appointment_item_id'] ?? '') === (string) $appointmentItem->getKey());

        return $saleItem->item_type === 'service' && $hasAutomationMarker && $hasAppointmentItemIdentity;
    }

    private function saleItemSourceId(TenantContext $context, string $unitId, AppointmentItem $appointmentItem): string
    {
        return sprintf('appointment-automation-item:%s:%s:%s', $context->tenant->getKey(), $unitId, $appointmentItem->getKey());
    }
}
