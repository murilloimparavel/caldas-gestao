<?php

namespace App\Actions\Suppliers;

use App\Actions\Operational\OperationalAction;
use App\Models\Supplier;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeactivateSupplier extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, Supplier $supplier, ?int $expectedVersion = null): Supplier
    {
        $expectedVersion ??= isset($supplier->lock_version) ? (int) $supplier->lock_version : null;
        $unit = $this->unit($actor, $context, 'supplier.manage');

        if ($supplier->tenant_id !== $context->tenant->getKey() || ($supplier->unit_id !== null && $supplier->unit_id !== $unit->getKey())) {
            throw new AuthorizationException('The supplier belongs to another workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('The supplier lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $supplier, $expectedVersion): Supplier {
            $locked = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The supplier was modified concurrently.');
            }

            if (! $locked->is_active) {
                return $locked;
            }

            $locked->forceFill([
                'is_active' => false,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'supplier.deactivated', $locked, [
                'is_active' => $locked->is_active,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh();
        }, 5);
    }
}
