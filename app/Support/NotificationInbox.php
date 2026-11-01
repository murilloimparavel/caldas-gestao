<?php

namespace App\Support;

use App\Models\AgendaNotification;
use Illuminate\Database\Eloquent\Builder;

final class NotificationInbox
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    /** @return Builder<AgendaNotification> */
    public function query(TenantContext $context): Builder
    {
        $unitIds = $context->membership->membershipUnits()->with('unit')->whereHas('unit', fn ($query) => $query->where('status', 'active'))->get()
            ->filter(fn ($membershipUnit): bool => $this->authorization->can($context->user, $context, 'calendar.view', $membershipUnit->unit))
            ->pluck('unit_id');

        return AgendaNotification::query()->where('tenant_id', $context->tenant->id)->where('user_id', $context->user->id)->whereIn('unit_id', $unitIds);
    }
}
