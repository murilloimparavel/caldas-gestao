<?php

namespace App\Actions\Operational;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

abstract class OperationalAction
{
    public function __construct(
        protected readonly AuthorizationService $authorization = new AuthorizationService,
        protected readonly IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
    ) {}

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
}
