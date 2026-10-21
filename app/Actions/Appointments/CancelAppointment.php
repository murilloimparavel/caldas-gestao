<?php

namespace App\Actions\Appointments;

use App\Actions\Operational\OperationalAction;
use App\Jobs\SyncGoogleCalendarAppointment;
use App\Models\Appointment;
use App\Models\User;
use App\Support\AppointmentStatusTransition;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CancelAppointment extends OperationalAction
{
    public function __construct(
        private readonly AppointmentStatusTransition $transitions = new AppointmentStatusTransition,
        private readonly CancelAppointmentSale $cancelAppointmentSale = new CancelAppointmentSale,
    ) {
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
        $tenantId = (string) $context->tenant->getKey();
        $unitId = (string) $unit->getKey();
        $expectedVersion = (int) ($data['lock_version'] ?? -1);

        return DB::transaction(function () use ($actor, $context, $appointment, $expectedVersion, $data, $tenantId, $unitId): Appointment {
            $locked = Appointment::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The appointment was modified concurrently.');
            }
            if ($locked->status === 'cancelled') {
                return $locked;
            }
            $this->transitions->assertCanTransition($locked->status, 'cancelled');
            $fromStatus = $locked->status;
            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $data['cancel_reason'] ?? null, 'lock_version' => $locked->lock_version + 1])->save();
            $locked->statusHistories()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $locked->tenant_id, 'unit_id' => $locked->unit_id, 'actor_user_id' => $actor->getKey(), 'action' => 'cancelled', 'from_status' => $fromStatus, 'to_status' => 'cancelled', 'reason' => $data['cancel_reason'] ?? null, 'occurred_at' => now()]);
            $this->events->record($actor, $context, 'appointment.cancelled', $locked, ['status' => 'cancelled', 'lock_version' => $locked->lock_version]);
            $this->cancelAppointmentSale->handle($actor, $context, $locked);
            SyncGoogleCalendarAppointment::dispatch((string) $locked->getKey())->afterCommit();

            return $locked->fresh(['items.service', 'customer', 'professional']);
        }, 5);
    }
}
