<?php

namespace App\Models;

use App\Policies\PackageUsagePolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\PackageUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $customer_package_id
 * @property string|null $sale_id
 * @property string|null $sale_item_id
 * @property int $sessions_consumed
 * @property string $user_id
 * @property CarbonImmutable|Carbon|null $reversed_at
 * @property string|null $reversed_by_user_id
 * @property string|null $reversal_reason
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'customer_package_id',
    'sale_id',
    'sale_item_id',
    'sessions_consumed',
    'user_id',
    'reversed_at',
    'reversed_by_user_id',
    'reversal_reason',
])]
#[UsePolicy(PackageUsagePolicy::class)]
class PackageUsage extends Model
{
    /** @use HasFactory<PackageUsageFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'sessions_consumed' => 1,
    ];

    protected function casts(): array
    {
        return [
            'sessions_consumed' => 'integer',
            'reversed_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
