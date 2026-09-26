<?php

namespace App\Models;

use App\Policies\ProductPolicy;
use App\Support\Images\MediaUrl;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $source_id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string|null $category_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $barcode
 * @property int $cost_price_cents
 * @property int $sale_price_cents
 * @property string $unit_of_measure
 * @property int $min_stock
 * @property int $current_stock
 * @property bool $is_active
 * @property int $lock_version
 * @property string|null $image_path
 * @property-read string|null $image_url
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'source_id',
    'category_id',
    'name',
    'sku',
    'barcode',
    'cost_price_cents',
    'sale_price_cents',
    'unit_of_measure',
    'min_stock',
    'current_stock',
    'is_active',
    'lock_version',
    'image_path',
])]
#[UsePolicy(ProductPolicy::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected $appends = ['image_url'];

    protected $attributes = [
        'cost_price_cents' => 0,
        'sale_price_cents' => 0,
        'unit_of_measure' => 'un',
        'min_stock' => 0,
        'current_stock' => 0,
        'is_active' => true,
        'lock_version' => 1,
    ];

    /** @return Attribute<string|null, void> */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => MediaUrl::for($this->image_path),
        );
    }

    protected function casts(): array
    {
        return [
            'cost_price_cents' => 'integer',
            'sale_price_cents' => 'integer',
            'min_stock' => 'integer',
            'current_stock' => 'integer',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
