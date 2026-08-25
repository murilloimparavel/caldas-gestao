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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateAppointment extends OperationalAction
{
    public function __construct(private readonly CalendarAvailability $availability, private readonly AppointmentStatusTransition $transitions = new AppointmentStatusTransition)
    {
        parent::__construct();
    }

    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Appointment
    {
        $unit = $this->unit($actor, $context, 'calendar.manage');
        $tenantId = (string) $context->tenant->getKey();
        $unitId = (string) $unit->getKey();
        $service = Service::query()->whereKey($data['service_id'])->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('status', 'active')->firstOrFail();
        Customer::query()->whereKey($data['customer_id'])->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('status', 'active')->firstOrFail();
        $professional = Professional::query()->whereKey($data['professional_id'])->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('status', 'active')->firstOrFail();
        if (! $professional->services()->whereKey($service->getKey())->wherePivot('tenant_id', $tenantId)->wherePivot('unit_id', $unitId)->exists()) {
            throw ValidationException::withMessages(['service_id' => 'The selected professional does not provide this service in the active unit.']);
        }

        $duration = (int) ($data['duration_minutes'] ?? $service->duration_minutes);
        $timezone = (string) ($unit->timezone ?? config('app.timezone'));
        $startsAt = CarbonImmutable::parse((string) $data['starts_at'], $timezone);
        $endsAt = $startsAt->addMinutes($duration);
        $status = (string) ($data['status'] ?? 'confirmed');
        $this->transitions->assertCanCreate($status);

        return DB::transaction(function () use ($actor, $context, $data, $service, $tenantId, $unitId, $startsAt, $endsAt, $timezone, $duration, $status): Appointment {
            $this->availability->assertAvailable($tenantId, $unitId, (string) $data['professional_id'], $startsAt, $endsAt);
            $appointment = Appointment::query()->create([
                'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'unit_id' => $unitId,
                'customer_id' => $data['customer_id'], 'professional_id' => $data['professional_id'],
                'starts_at' => $startsAt, 'ends_at' => $endsAt, 'timezone' => $timezone,
                'status' => $status, 'source' => $data['source'] ?? 'internal',
                'color' => $data['color'] ?? null, 'reminder_enabled' => $data['reminder_enabled'] ?? true,
                'fit_in' => $data['fit_in'] ?? false, 'notes' => $data['notes'] ?? null, 'lock_version' => 0,
            ]);
            $this->upsertItem($appointment, $service, $duration);
            $this->recordHistory($appointment, $actor, null, $appointment->status, 'created');
            $this->events->record($actor, $context, 'appointment.created', $appointment, ['status' => $appointment->status]);

            return $appointment->fresh(['items.service', 'customer', 'professional']);
        }, 5);
    }

    private function upsertItem(Appointment $appointment, Service $service, int $duration): void
    {
        AppointmentItem::query()->updateOrCreate(
            ['appointment_id' => $appointment->getKey(), 'position' => 1],
            ['id' => (string) Str::uuid7(), 'tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'service_id' => $service->getKey(), 'professional_id' => $appointment->professional_id, 'service_name_snapshot' => $service->name, 'duration_minutes' => $duration, 'price_cents' => $service->price_cents, 'currency' => 'BRL'],
        );
    }

    private function recordHistory(Appointment $appointment, User $actor, ?string $from, string $to, string $action): void
    {
        $appointment->statusHistories()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'actor_user_id' => $actor->getKey(), 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'occurred_at' => now()]);
    }
}
