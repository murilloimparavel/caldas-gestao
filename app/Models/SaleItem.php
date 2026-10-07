<?php

namespace App\Models;

use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string $sale_id
 * @property string $item_type
 * @property string|null $service_id
 * @property string|null $product_id
 * @property string|null $package_template_id
 * @property string|null $customer_package_id
 * @property string|null $professional_id
 * @property string|null $seller_professional_id
 * @property string $name_snapshot
 * @property int $unit_price_cents
 * @property int $quantity
 * @property int $covered_quantity
 * @property int $discount_cents
 * @property int $total_cents
 * @property array<string, mixed>|null $source_metadata
 */
#[Fillable([
    'source_id',
    'tenant_id',
    'unit_id',
    'sale_id',
    'item_type',
    'service_id',
    'product_id',
    'package_template_id',
    'customer_package_id',
    'professional_id',
    'seller_professional_id',
    'name_snapshot',
    'unit_price_cents',
    'quantity',
    'covered_quantity',
    'discount_cents',
    'total_cents',
    'source_metadata',
])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'item_type' => 'custom',
        'quantity' => 1,
        'covered_quantity' => 0,
        'discount_cents' => 0,
    ];

    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'covered_quantity' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'source_metadata' => 'array',
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

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<PackageTemplate, $this> */
    public function packageTemplate(): BelongsTo
    {
        return $this->belongsTo(PackageTemplate::class);
    }

    /** @return BelongsTo<CustomerPackage, $this> */
    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function sellerProfessional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'seller_professional_id');
    }

    /** @return HasMany<PackageUsage, $this> */
    public function packageUsages(): HasMany
    {
        return $this->hasMany(PackageUsage::class);
    }

    /** @return HasMany<PackageUsageReservation, $this> */
    public function packageReservations(): HasMany
    {
        return $this->hasMany(PackageUsageReservation::class);
    }
}
