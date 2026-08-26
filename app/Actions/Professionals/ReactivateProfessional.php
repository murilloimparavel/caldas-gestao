<?php

namespace App\Actions\Professionals;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReactivateProfessional extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, Professional $professional, ?int $expectedVersion = null): Professional
    {
        $unit = $this->unit($actor, $context, 'professional.manage');
        if ($professional->tenant_id !== $context->tenant->getKey() || $professional->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The professional belongs to another workspace.');
        }
        if ($expectedVersion === null) {
            throw new ConflictHttpException('The professional lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $professional, $expectedVersion): Professional {
            $locked = Professional::query()->whereKey($professional->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The professional was modified concurrently.');
            }
            if ($locked->status === 'active') {
                return $locked;
            }
            $locked->forceFill(['status' => 'active', 'lock_version' => $locked->lock_version + 1])->save();
            $this->events->record($actor, $context, 'professional.reactivated', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);

            return $locked->fresh();
        }, 5);
    }
}
