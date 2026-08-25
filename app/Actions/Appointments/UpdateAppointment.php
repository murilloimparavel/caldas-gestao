<?php

namespace App\Actions\Appointments;

use App\Actions\Operational\OperationalAction;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Customer;
use App\Models\Professional;
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
    public function __construct(private readonly CalendarAvailability $availability, private readonly AppointmentStatusTransition $transitions = new AppointmentStatusTransition)
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
        $service = Service::query()->whereKey($data['service_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        Customer::query()->whereKey($data['customer_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        $professional = Professional::query()->whereKey($data['professional_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        if (! $professional->services()->whereKey($service->getKey())->wherePivot('tenant_id', $context->tenant->getKey())->wherePivot('unit_id', $unit->getKey())->exists()) {
            throw ValidationException::withMessages(['service_id' => 'The selected professional does not provide this service in the active unit.']);
        }
        $duration = (int) ($data['duration_minutes'] ?? $service->duration_minutes);
        $timezone = (string) ($unit->timezone ?? config('app.timezone'));
        $startsAt = CarbonImmutable::parse((string) $data['starts_at'], $timezone);
        $endsAt = $startsAt->addMinutes($duration);

        return DB::transaction(function () use ($actor, $context, $appointment, $data, $service, $expectedVersion, $startsAt, $endsAt, $timezone, $duration): Appointment {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
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
            AppointmentItem::query()->updateOrCreate(['appointment_id' => $locked->getKey(), 'position' => 1], ['id' => (string) Str::uuid7(), 'tenant_id' => $locked->tenant_id, 'unit_id' => $locked->unit_id, 'service_id' => $service->getKey(), 'professional_id' => $locked->professional_id, 'service_name_snapshot' => $service->name, 'duration_minutes' => $duration, 'price_cents' => $service->price_cents, 'currency' => 'BRL']);
            if ($fromStatus !== $locked->status) {
                $locked->statusHistories()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $locked->tenant_id, 'unit_id' => $locked->unit_id, 'actor_user_id' => $actor->getKey(), 'action' => 'status_changed', 'from_status' => $fromStatus, 'to_status' => $locked->status, 'occurred_at' => now()]);
            }
            $this->events->record($actor, $context, 'appointment.updated', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);

            return $locked->fresh(['items.service', 'customer', 'professional']);
        }, 5);
    }
}
