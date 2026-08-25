<?php

namespace App\Actions\Products;

use App\Actions\Operational\OperationalAction;
use App\Models\Product;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeactivateProduct extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, Product $product, ?int $expectedVersion = null): Product
    {
        $unit = $this->unit($actor, $context, 'product.manage');

        if ($product->tenant_id !== $context->tenant->getKey() || $product->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The product belongs to another workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('The product lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $product, $expectedVersion): Product {
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The product was modified concurrently.');
            }

            if (! $locked->is_active) {
                return $locked;
            }

            $locked->forceFill([
                'is_active' => false,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'product.deactivated', $locked, [
                'is_active' => $locked->is_active,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh()->load('category');
        }, 5);
    }
}
