<?php

namespace App\Actions\Categories;

use App\Actions\Operational\OperationalAction;
use App\Models\Category;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateCategory extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Category $category, array $data, ?int $expectedVersion = null): Category
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);
        $unit = $this->unit($actor, $context, 'category.manage');

        if ($category->tenant_id !== $context->tenant->getKey() || $category->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The category belongs to another workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('The category lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $category, $data, $expectedVersion): Category {
            $locked = Category::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The category was modified concurrently.');
            }

            $locked->forceFill([...$data, 'lock_version' => $locked->lock_version + 1])->save();

            $this->events->record($actor, $context, 'category.updated', $locked, [
                'is_active' => $locked->is_active,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh();
        }, 5);
    }
}
