<?php

namespace App\Actions\Operational;

use App\Models\Appointment;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\ProfessionalScope;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

abstract class OperationalAction
{
    protected readonly ProfessionalScope $professionalScope;

    public function __construct(
        protected readonly AuthorizationService $authorization = new AuthorizationService,
        protected readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        ?ProfessionalScope $professionalScope = null,
    ) {
        $this->professionalScope = $professionalScope ?? new ProfessionalScope;
    }

    protected function unit(User $actor, TenantContext $context, string $permission): Unit
    {
        $unit = $context->unit;

        if ($unit === null || ! $context->user->is($actor) || ! $this->authorization->can($actor, $context, $permission, $unit)) {
            throw new AuthorizationException('The actor is not allowed to manage this operational resource.');
        }

        return $unit;
    }

    protected function tenant(TenantContext $context): Tenant
    {
        return $context->tenant;
    }

    protected function assertProfessional(TenantContext $context, ?string $professionalId = null): ?string
    {
        return $this->professionalScope->assertProfessional($context, $professionalId);
    }

    protected function assertOwnAppointment(TenantContext $context, Appointment $appointment): void
    {
        if (! $this->professionalScope->ownsAppointment($context, $appointment)) {
            throw new AuthorizationException('O agendamento não pertence ao profissional vinculado.');
        }
    }

    protected function assertOwnSale(TenantContext $context, Sale $sale): void
    {
        if (! $this->professionalScope->canMutateSale($context, $sale)) {
            throw new AuthorizationException('A comanda não pertence ao profissional vinculado.');
        }
    }
}
