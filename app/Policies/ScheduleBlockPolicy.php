<?php

namespace App\Policies;

use App\Models\ScheduleBlock;
use App\Models\User;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

final class ScheduleBlockPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'calendar.view');
    }

    public function view(User $user, ScheduleBlock $scheduleBlock): bool
    {
        return $this->allows($user, 'calendar.view', $scheduleBlock);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'calendar.manage');
    }

    public function update(User $user, ScheduleBlock $scheduleBlock): bool
    {
        return $this->allows($user, 'calendar.manage', $scheduleBlock);
    }

    public function delete(User $user, ScheduleBlock $scheduleBlock): bool
    {
        return $this->allows($user, 'calendar.manage', $scheduleBlock);
    }

    private function allows(User $user, string $permission, ?ScheduleBlock $scheduleBlock = null): bool
    {
        try {
            $context = $scheduleBlock === null
                ? app(TenantContext::class)
                : TenantContext::forUser($user, $scheduleBlock->tenant_id, $scheduleBlock->unit_id);

            return $context->user->is($user)
                && ($scheduleBlock === null || $context->unit?->is($scheduleBlock->unit))
                && $this->authorization->can($user, $context, $permission, $context->unit);
        } catch (AuthorizationException|\LogicException) {
            return false;
        }
    }
}
