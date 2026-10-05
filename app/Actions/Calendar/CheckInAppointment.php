<?php

namespace App\Actions\Calendar;

use App\Actions\Operational\OperationalAction;
use App\Models\Appointment;
use App\Models\User;
use App\Support\AppointmentStatusTransition;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CheckInAppointment extends OperationalAction
{
    public function __construct(private readonly AppointmentStatusTransition $transitions = new AppointmentStatusTransition)
    {
        parent::__construct();
    }

    public function handle(User $actor, TenantContext $context, Appointment $appointment, ?int $lockVersion = null): Appointment
    {
        $unit = $this->unit($actor, $context, 'calendar.manage');
        if ($appointment->tenant_id !== $context->tenant->getKey() || $appointment->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The appointment belongs to another workspace.');
        }
        $this->assertOwnAppointment($context, $appointment);

        return DB::transaction(function () use ($actor, $context, $appointment, $lockVersion): Appointment {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            if ($lockVersion !== null && $locked->lock_version !== $lockVersion) {
                throw new ConflictHttpException('The appointment was modified concurrently.');
            }
            $this->transitions->assertCanTransition($locked->status, 'checked_in');
            $fromStatus = $locked->status;
            $locked->forceFill(['status' => 'checked_in', 'lock_version' => $locked->lock_version + 1])->save();
            $locked->statusHistories()->create([
                'id' => (string) Str::uuid7(), 'tenant_id' => $locked->tenant_id, 'unit_id' => $locked->unit_id,
                'actor_user_id' => $actor->getKey(), 'action' => 'checked_in', 'from_status' => $fromStatus,
                'to_status' => 'checked_in', 'occurred_at' => now(),
            ]);
            $this->events->record($actor, $context, 'appointment.checked_in', $locked, ['status' => 'checked_in', 'lock_version' => $locked->lock_version]);

            return $locked->fresh(['items.service', 'customer', 'professional']);
        }, 5);
    }
}
