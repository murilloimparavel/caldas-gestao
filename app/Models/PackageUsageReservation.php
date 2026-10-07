<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $customer_package_id
 * @property string $sale_id
 * @property string $sale_item_id
 * @property string $service_id
 * @property string $user_id
 * @property string|null $package_usage_id
 * @property int $sessions_reserved
 * @property string $status
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'customer_package_id',
    'sale_id',
    'sale_item_id',
    'service_id',
    'user_id',
    'package_usage_id',
    'sessions_reserved',
    'status',
    'released_at',
    'release_reason',
])]
class PackageUsageReservation extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'sessions_reserved' => 'integer',
            'released_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CustomerPackage, $this> */
    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<SaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    /** @return BelongsTo<PackageUsage, $this> */
    public function packageUsage(): BelongsTo
    {
        return $this->belongsTo(PackageUsage::class);
    }
}
