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
        $expectedVersion = (int) ($data['lock_version'] ?? -1);
        $serviceIds = array_values(array_unique($data['service_ids'] ?? array_filter([$data['service_id'] ?? null], 'is_string')));
        if (count($serviceIds) > 8) {
            throw ValidationException::withMessages(['service_ids' => 'Selecione no máximo oito serviços por agendamento.']);
        }
        $servicesById = Service::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->whereIn('id', $serviceIds)->get()->keyBy(fn (Service $service): string => (string) $service->getKey());
        $services = collect($serviceIds)->map(fn (string $serviceId): ?Service => $servicesById->get($serviceId));
        if ($serviceIds === [] || $services->contains(null)) {
            throw ValidationException::withMessages(['service_ids' => 'Selecione serviços ativos desta unidade.']);
        }
        $services = $services->filter()->values();
        Customer::query()->whereKey($data['customer_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        $professional = Professional::query()->whereKey($data['professional_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        $professionalServiceIds = $professional->services()->wherePivot('tenant_id', $context->tenant->getKey())->wherePivot('unit_id', $unit->getKey())->pluck('services.id');
        if ($services->contains(fn (Service $service): bool => ! $professionalServiceIds->contains($service->getKey()))) {
            throw ValidationException::withMessages(['service_ids' => 'O profissional selecionado não atende todos os serviços escolhidos.']);
        }
        $duration = $services->count() === 1
            ? (int) ($data['duration_minutes'] ?? $services->sum('duration_minutes'))
            : (int) $services->sum('duration_minutes');
        if ($duration < 5 || $duration > 1440) {
            throw ValidationException::withMessages(['duration_minutes' => 'A duração deve ficar entre 5 minutos e 24 horas.']);
        }
        $timezone = (string) ($unit->timezone ?? config('app.timezone'));
        $startsAt = CarbonImmutable::parse((string) $data['starts_at'], $timezone);
        $endsAt = $startsAt->addMinutes($duration);

        return DB::transaction(function () use ($actor, $context, $appointment, $data, $services, $expectedVersion, $startsAt, $endsAt, $timezone, $unit, $duration): Appointment {
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
            $locked->forceFill(['customer_id' => $data['customer_id'], 'professional_id' => $data['professional_id'], 'starts_at' => $startsAt->utc(), 'ends_at' => $endsAt->utc(), 'timezone' => $timezone, 'status' => $nextStatus, 'source' => $data['source'] ?? $locked->source, 'color' => $data['color'] ?? $locked->color, 'reminder_enabled' => $data['reminder_enabled'] ?? $locked->reminder_enabled, 'fit_in' => $data['fit_in'] ?? $locked->fit_in, 'notes' => $data['notes'] ?? $locked->notes, 'lock_version' => $locked->lock_version + 1])->save();
            $existingItems = AppointmentItem::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('unit_id', $locked->unit_id)
                ->where('appointment_id', $locked->getKey())
                ->lockForUpdate()
                ->orderBy('position')
                ->get()
                ->keyBy('position');
            $syncedItemIds = [];

            foreach ($services as $index => $service) {
                $position = $index + 1;
                $appointmentItem = $existingItems->get($position);
                $attributes = [
                    'tenant_id' => $locked->tenant_id,
                    'unit_id' => $locked->unit_id,
                    'service_id' => $service->getKey(),
                    'professional_id' => $locked->professional_id,
                    'service_name_snapshot' => $service->name,
                    'duration_minutes' => $services->count() === 1 ? $duration : $service->duration_minutes,
                    'price_cents' => $service->price_cents,
                    'currency' => $appointmentItem->currency ?? 'BRL',
                    'position' => $position,
                ];

                if ($appointmentItem === null) {
                    $appointmentItem = AppointmentItem::query()->create([
                        'id' => (string) Str::uuid7(),
                        'appointment_id' => $locked->getKey(),
                        ...$attributes,
                    ]);
                } else {
                    $appointmentItem->forceFill($attributes)->save();
                }

                $syncedItemIds[] = $appointmentItem->getKey();
                $this->syncAutomaticSaleItem($actor, $context, $locked, $appointmentItem, $service, $unit->getKey());
            }

            AppointmentItem::query()->where('appointment_id', $locked->getKey())->whereNotIn('id', $syncedItemIds)->delete();
            $this->removeDeletedAutomaticSaleItems($actor, $context, $locked, $syncedItemIds, $unit->getKey());
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

    /** @param list<string> $retainedItemIds */
    private function removeDeletedAutomaticSaleItems(User $actor, TenantContext $context, Appointment $appointment, array $retainedItemIds, string $unitId): void
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

        $sale = Sale::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unitId)->whereKey($link->sale_id)->lockForUpdate()->first();
        if ($sale === null || $sale->status !== 'open' || ! $this->isAutomaticSale($sale)) {
            return;
        }

        $retainedSourceIds = AppointmentItem::query()->whereIn('id', $retainedItemIds)->get()->map(fn (AppointmentItem $item): string => $this->saleItemSourceId($context, $unitId, $item))->all();
        $automaticItems = $sale->items()->where('item_type', 'service')->get()->filter(function (SaleItem $item) use ($retainedSourceIds): bool {
            $metadata = $item->source_metadata;
            $isAutomatic = Str::startsWith((string) $item->source_id, 'appointment-automation-item:')
                || (is_array($metadata) && (($metadata['origin'] ?? null) === 'appointment_automation' || ($metadata['created_automatically'] ?? false) === true));

            return $isAutomatic && ! in_array($item->source_id, $retainedSourceIds, true);
        });

        foreach ($automaticItems as $saleItem) {
            $saleItem->delete();
            $this->events->record($actor, $context, 'sale.appointment_item_removed', $sale, [
                'appointment_id' => $appointment->getKey(),
                'sale_id' => $sale->getKey(),
                'sale_item_id' => $saleItem->getKey(),
                'lock_version' => $sale->lock_version + 1,
            ]);
        }

        if ($automaticItems->isNotEmpty()) {
            $totalAmountCents = (int) $sale->items()->sum('total_cents');
            $sale->forceFill([
                'total_amount_cents' => $totalAmountCents,
                'final_amount_cents' => max(0, $totalAmountCents - (int) $sale->discount_amount_cents),
                'lock_version' => $sale->lock_version + 1,
            ])->save();
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
