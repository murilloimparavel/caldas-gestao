<?php

namespace App\Actions\Services;

use App\Actions\Operational\OperationalAction;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeactivateService extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, Service $service, ?int $expectedVersion = null): Service
    {
        $unit = $this->unit($actor, $context, 'service.manage');
        if ($service->tenant_id !== $context->tenant->getKey() || $service->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The service belongs to another workspace.');
        }
        if ($expectedVersion === null) {
            throw new ConflictHttpException('The service lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $service, $expectedVersion): Service {
            $locked = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The service was modified concurrently.');
            }
            if ($locked->status === 'inactive') {
                return $locked;
            }
            $locked->forceFill(['status' => 'inactive', 'lock_version' => $locked->lock_version + 1])->save();
            $this->events->record($actor, $context, 'service.deactivated', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);

            return $locked->fresh();
        }, 5);
    }
}
