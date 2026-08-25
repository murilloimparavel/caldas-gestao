<?php

namespace App\Actions\Professionals;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateProfessional extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Professional $professional, array $data, ?int $expectedVersion = null): Professional
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);
        $unit = $this->unit($actor, $context, 'professional.manage');
        if ($professional->tenant_id !== $context->tenant->getKey() || $professional->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The professional belongs to another workspace.');
        }
        if ($expectedVersion === null) {
            throw new ConflictHttpException('The professional lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $professional, $data, $expectedVersion): Professional {
            $serviceIds = $data['service_ids'] ?? null;
            unset($data['service_ids']);
            $locked = Professional::query()->whereKey($professional->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The professional was modified concurrently.');
            }
            $locked->forceFill([...$data, 'lock_version' => $locked->lock_version + 1])->save();
            if ($serviceIds !== null) {
                $serviceIds = array_values(array_unique($serviceIds));
                if (count($serviceIds) !== Service::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->whereIn('id', $serviceIds)->count()) {
                    throw new \InvalidArgumentException('Each service must belong to the active unit.');
                }
                $locked->services()->syncWithPivotValues($serviceIds, ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $context->unit?->getKey()]);
            }
            $this->events->record($actor, $context, 'professional.updated', $locked, [
                'status' => $locked->status,
                'lock_version' => $locked->lock_version,
                ...($serviceIds === null ? [] : ['service_ids' => $serviceIds]),
            ]);

            return $locked->fresh()->load('services');
        }, 5);
    }
}
