<?php

namespace App\Models;

use Database\Factories\CommissionAccrualFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $professional_id
 * @property string $sale_id
 * @property string $sale_item_id
 * @property string|null $source_type
 * @property string|null $service_id
 * @property string|null $package_template_id
 * @property string|null $customer_package_id
 * @property string $item_name_snapshot
 * @property string|null $service_name_snapshot
 * @property string|null $package_name_snapshot
 * @property int $gross_amount_cents
 * @property int|null $quantity
 * @property int|null $covered_quantity
 * @property int|null $commissionable_quantity
 * @property string $rate_type
 * @property int $rate_value
 * @property int $commission_amount_cents
 * @property string $status
 * @property Carbon|null $settled_at
 * @property string|null $settlement_id
 * @property int $lock_version
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'professional_id',
    'sale_id',
    'sale_item_id',
    'source_type',
    'service_id',
    'package_template_id',
    'customer_package_id',
    'item_name_snapshot',
    'service_name_snapshot',
    'package_name_snapshot',
    'gross_amount_cents',
    'quantity',
    'covered_quantity',
    'commissionable_quantity',
    'rate_type',
    'rate_value',
    'commission_amount_cents',
    'status',
    'settled_at',
    'settlement_id',
    'lock_version',
])]
class CommissionAccrual extends Model
{
    /** @use HasFactory<CommissionAccrualFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'accrued',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'gross_amount_cents' => 'integer',
            'quantity' => 'integer',
            'covered_quantity' => 'integer',
            'commissionable_quantity' => 'integer',
            'rate_value' => 'integer',
            'commission_amount_cents' => 'integer',
            'settled_at' => 'datetime',
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

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
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

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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

    /** @return BelongsTo<CommissionSettlement, $this> */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class, 'settlement_id');
    }
}
