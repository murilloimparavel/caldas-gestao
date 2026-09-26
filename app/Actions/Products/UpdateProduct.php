<?php

namespace App\Actions\Products;

use App\Actions\Operational\OperationalAction;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Images\UploadedImageOptimizer;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateProduct extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Product $product, array $data, ?int $expectedVersion = null): Product
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);
        $unit = $this->unit($actor, $context, 'product.manage');

        if ($product->tenant_id !== $context->tenant->getKey() || $product->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The product belongs to another workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('The product lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $product, $data, $unit, $expectedVersion): Product {
            if (array_key_exists('category_id', $data) && ! empty($data['category_id'])) {
                $validCategory = Category::query()
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $unit->getKey())
                    ->whereKey($data['category_id'])
                    ->exists();

                if (! $validCategory) {
                    throw new \InvalidArgumentException('The category must belong to the active unit.');
                }
            }

            $hasImageKey = array_key_exists('image', $data) || array_key_exists('image_file', $data);
            /** @var UploadedFile|null $imageFile */
            $imageFile = $data['image'] ?? $data['image_file'] ?? null;
            unset($data['image'], $data['image_file']);

            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The product was modified concurrently.');
            }

            if ($hasImageKey) {
                $diskName = (string) config('filesystems.media_disk');
                if ($imageFile instanceof UploadedFile) {
                    if ($locked->image_path) {
                        Storage::disk($diskName)->delete($locked->image_path);
                    }
                    $path = "{$context->tenant->getKey()}/products/{$locked->getKey()}/".Str::random(40).'.webp';
                    $stored = app(UploadedImageOptimizer::class)->storeWebp($imageFile, Storage::disk($diskName), $path);
                    $data['image_path'] = $stored ? $path : null;
                } elseif ($imageFile === null) {
                    if ($locked->image_path) {
                        Storage::disk($diskName)->delete($locked->image_path);
                    }
                    $data['image_path'] = null;
                }
            }

            $locked->forceFill([...$data, 'lock_version' => $locked->lock_version + 1])->save();

            $this->events->record($actor, $context, 'product.updated', $locked, [
                'sale_price_cents' => $locked->sale_price_cents,
                'cost_price_cents' => $locked->cost_price_cents,
                'current_stock' => $locked->current_stock,
                'min_stock' => $locked->min_stock,
                'unit_of_measure' => $locked->unit_of_measure,
                'is_active' => $locked->is_active,
                'category_id' => $locked->category_id,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh()->load('category');
        }, 5);
    }
}
