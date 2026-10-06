<?php

namespace App\Actions\Calendar;

use App\Actions\Operational\OperationalAction;
use App\Models\AvailabilityRule;
use App\Models\Professional;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateAvailabilityRule extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, AvailabilityRule $rule, array $data): AvailabilityRule
    {
        $unit = $this->unit($actor, $context, 'calendar.configure');
        if ($rule->tenant_id !== $context->tenant->getKey() || $rule->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The availability rule belongs to another workspace.');
        }
        if (! $this->professionalScope->ownsAvailabilityRule($context, $rule)) {
            throw new AuthorizationException('A regra de disponibilidade não pertence ao profissional vinculado.');
        }
        Professional::query()->whereKey($data['professional_id'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('status', 'active')->firstOrFail();
        $this->assertProfessional($context, (string) $data['professional_id']);

        return DB::transaction(function () use ($actor, $context, $rule, $data): AvailabilityRule {
            $locked = AvailabilityRule::query()->whereKey($rule->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== (int) $data['lock_version']) {
                throw new ConflictHttpException('The availability rule was modified concurrently.');
            }
            if ($locked->status === 'inactive') {
                throw ValidationException::withMessages(['availability_rule' => 'Inactive availability rules cannot be edited.']);
            }
            $locked->forceFill([
                'professional_id' => $data['professional_id'], 'weekday' => $data['weekday'],
                'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
                'timezone' => $data['timezone'], 'status' => $data['status'] ?? $locked->status,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->events->record($actor, $context, 'availability_rule.updated', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);

            return $locked->fresh('professional');
        }, 5);
    }
}
