<?php

namespace App\Actions\Products;

use App\Actions\Operational\OperationalAction;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateProduct extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Product
    {
        $unit = $this->unit($actor, $context, 'product.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): Product {
            if (! empty($data['category_id'])) {
                $validCategory = Category::query()
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $unit->getKey())
                    ->whereKey($data['category_id'])
                    ->exists();

                if (! $validCategory) {
                    throw new \InvalidArgumentException('The category must belong to the active unit.');
                }
            }

            $product = Product::query()->create([
                ...$data,
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'lock_version' => 1,
            ]);

            $this->events->record($actor, $context, 'product.created', $product, [
                'sale_price_cents' => $product->sale_price_cents,
                'cost_price_cents' => $product->cost_price_cents,
                'current_stock' => $product->current_stock,
                'min_stock' => $product->min_stock,
                'unit_of_measure' => $product->unit_of_measure,
                'is_active' => $product->is_active,
                'category_id' => $product->category_id,
            ]);

            return $product->load('category');
        }, 5);
    }
}
