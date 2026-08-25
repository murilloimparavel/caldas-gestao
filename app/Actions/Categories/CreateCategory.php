<?php

namespace App\Actions\Categories;

use App\Actions\Operational\OperationalAction;
use App\Models\Category;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateCategory extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Category
    {
        $unit = $this->unit($actor, $context, 'category.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): Category {
            $category = Category::query()->create([
                ...$data,
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'lock_version' => 1,
            ]);

            $this->events->record($actor, $context, 'category.created', $category, [
                'is_active' => $category->is_active,
            ]);

            return $category;
        }, 5);
    }
}
