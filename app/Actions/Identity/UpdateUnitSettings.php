<?php

namespace App\Actions\Identity;

use App\Actions\Operational\OperationalAction;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateUnitSettings extends OperationalAction
{
    /** @param array{name: string, timezone: string|null, address: array<string, string|null>|null, online_booking_enabled: bool, appointment_sales_automation_enabled: bool} $data */
    public function handle(User $actor, TenantContext $context, array $data, int $expectedVersion): Unit
    {
        $unit = $this->unit($actor, $context, 'unit.update');

        if ($unit->tenant_id !== $context->tenant->getKey()) {
            throw new ConflictHttpException('The unit does not belong to this tenant.');
        }

        return DB::transaction(function () use ($actor, $context, $unit, $data, $expectedVersion): Unit {
            $locked = Unit::query()
                ->whereKey($unit->getKey())
                ->where('tenant_id', $context->tenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The unit settings were modified concurrently.');
            }

            $locked->forceFill([
                ...$data,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'unit.settings_updated', $locked, [
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh();
        }, 5);
    }
}
