<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\ProfessionalScope;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class AppointmentPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly ProfessionalScope $professionalScope,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'calendar.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->allows($user, 'calendar.view', $appointment);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'calendar.manage');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->allows($user, 'calendar.manage', $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->allows($user, 'calendar.manage', $appointment);
    }

    private function allows(User $user, string $permission, ?Appointment $appointment = null): bool
    {
        try {
            $context = $appointment === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $appointment->tenant_id, $appointment->unit_id);

            return $context->user->is($user)
                && ($appointment === null || $context->unit?->is($appointment->unit))
                && ($appointment === null || $this->professionalScope->ownsAppointment($context, $appointment))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
