<?php

namespace App\Actions\Products;

use App\Actions\Operational\OperationalAction;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

            /** @var UploadedFile|null $imageFile */
            $imageFile = $data['image'] ?? $data['image_file'] ?? null;
            unset($data['image'], $data['image_file']);

            $productId = (string) Str::uuid7();
            $imagePath = null;
            if ($imageFile instanceof UploadedFile) {
                $hash = Str::random(40);
                $ext = $imageFile->guessExtension() ?: $imageFile->getClientOriginalExtension();
                $diskName = (string) config('filesystems.media_disk', 'public');
                $storedPath = Storage::disk($diskName)->putFileAs(
                    "{$context->tenant->getKey()}/products/{$productId}",
                    $imageFile,
                    "{$hash}.{$ext}"
                );
                $imagePath = $storedPath !== false ? $storedPath : null;
            }

            $product = new Product;
            $product->id = $productId;
            $product->tenant_id = $context->tenant->getKey();
            $product->unit_id = $unit->getKey();
            $product->image_path = $imagePath;
            $product->lock_version = 1;
            $product->fill($data);
            $product->save();

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
