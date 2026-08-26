<?php

namespace App\Models;

use App\Policies\SalePolicy;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string|null $customer_id
 * @property string $sale_category_id
 * @property string|null $category_key_snapshot
 * @property string|null $category_name_snapshot
 * @property string|null $reference_label
 * @property string|null $open_context_key
 * @property string $status
 * @property string $currency
 * @property int $total_amount_cents
 * @property int $discount_amount_cents
 * @property int $final_amount_cents
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'customer_id',
    'sale_category_id',
    'category_key_snapshot',
    'category_name_snapshot',
    'reference_label',
    'open_context_key',
    'status',
    'currency',
    'total_amount_cents',
    'discount_amount_cents',
    'final_amount_cents',
    'notes',
    'lock_version',
])]
#[UsePolicy(SalePolicy::class)]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
        'currency' => 'BRL',
        'total_amount_cents' => 0,
        'discount_amount_cents' => 0,
        'final_amount_cents' => 0,
        'lock_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'total_amount_cents' => 'integer',
            'discount_amount_cents' => 'integer',
            'final_amount_cents' => 'integer',
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<SaleCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SaleCategory::class, 'sale_category_id');
    }

    /** @return BelongsTo<SaleCategory, $this> */
    public function saleCategory(): BelongsTo
    {
        return $this->belongsTo(SaleCategory::class, 'sale_category_id');
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** @return HasOne<AppointmentSaleLink, $this> */
    public function appointmentLink(): HasOne
    {
        return $this->hasOne(AppointmentSaleLink::class);
    }

    /** @return HasMany<SaleStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(SaleStatusHistory::class);
    }

    /** @return BelongsToMany<ClosingSession, $this> */
    public function closingSessions(): BelongsToMany
    {
        return $this->belongsToMany(ClosingSession::class, 'closing_session_sales', 'sale_id', 'closing_session_id');
    }

    /** @return HasMany<CustomerPackage, $this> */
    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    /** @return HasMany<PackageUsage, $this> */
    public function packageUsages(): HasMany
    {
        return $this->hasMany(PackageUsage::class);
    }
}
