<?php

namespace App\Actions\SaleCategories;

use App\Actions\Operational\OperationalAction;
use App\Models\SaleCategory;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeactivateSaleCategory extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, SaleCategory $saleCategory, ?int $expectedVersion = null): SaleCategory
    {
        $unit = $this->unit($actor, $context, 'sale_category.manage');

        if ($saleCategory->tenant_id !== $context->tenant->getKey() || $saleCategory->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The sale category belongs to another workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('The sale category lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $saleCategory, $expectedVersion): SaleCategory {
            $locked = SaleCategory::query()->whereKey($saleCategory->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The sale category was modified concurrently.');
            }

            if (! $locked->is_active) {
                return $locked;
            }

            $locked->forceFill([
                'is_active' => false,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'sale_category.deactivated', $locked, [
                'type' => $locked->type,
                'uniqueness_scope' => $locked->uniqueness_scope,
                'is_active' => $locked->is_active,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh();
        }, 5);
    }
}
