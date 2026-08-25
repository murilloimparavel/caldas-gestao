<?php

namespace App\Actions\SaleCategories;

use App\Actions\Operational\OperationalAction;
use App\Models\SaleCategory;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateSaleCategory extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): SaleCategory
    {
        $unit = $this->unit($actor, $context, 'sale_category.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): SaleCategory {
            $rawKey = trim((string) ($data['key'] ?? ''));
            $key = $rawKey !== '' ? Str::slug($rawKey) : Str::slug((string) $data['name']);

            if ($key === '') {
                $key = 'categoria-'.Str::lower(Str::random(6));
            }

            // Check if key already exists in tenant and unit; append random suffix if needed
            $keyExists = SaleCategory::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('key', $key)
                ->exists();

            if ($keyExists) {
                $key .= '-'.Str::lower(Str::random(4));
            }

            $saleCategory = SaleCategory::query()->create([
                ...$data,
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'key' => $key,
                'lock_version' => 1,
            ]);

            $this->events->record($actor, $context, 'sale_category.created', $saleCategory, [
                'type' => $saleCategory->type,
                'uniqueness_scope' => $saleCategory->uniqueness_scope,
                'is_active' => $saleCategory->is_active,
            ]);

            return $saleCategory;
        }, 5);
    }
}
