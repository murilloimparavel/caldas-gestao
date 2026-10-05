<?php

namespace App\Actions\Calendar;

use App\Actions\Operational\OperationalAction;
use App\Models\AvailabilityRule;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeleteAvailabilityRule extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, AvailabilityRule $rule, int $lockVersion): AvailabilityRule
    {
        $unit = $this->unit($actor, $context, 'calendar.configure');
        if ($rule->tenant_id !== $context->tenant->getKey() || $rule->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The availability rule belongs to another workspace.');
        }
        if (! $this->professionalScope->ownsAvailabilityRule($context, $rule)) {
            throw new AuthorizationException('A regra de disponibilidade não pertence ao profissional vinculado.');
        }

        return DB::transaction(function () use ($actor, $context, $rule, $lockVersion): AvailabilityRule {
            $locked = AvailabilityRule::query()->whereKey($rule->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $lockVersion) {
                throw new ConflictHttpException('The availability rule was modified concurrently.');
            }
            if ($locked->status === 'inactive') {
                return $locked;
            }
            $locked->forceFill(['status' => 'inactive', 'lock_version' => $locked->lock_version + 1])->save();
            $this->events->record($actor, $context, 'availability_rule.deactivated', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);

            return $locked->fresh('professional');
        }, 5);
    }
}
