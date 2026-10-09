<?php

namespace App\Models;

use App\Policies\CustomerPackagePolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $customer_id
 * @property string $package_template_id
 * @property string|null $name_snapshot
 * @property int|null $price_cents_snapshot
 * @property int|null $total_sessions_snapshot
 * @property int|null $validity_days_snapshot
 * @property array<int, array{id:string,name:string,quantity?:int,price_cents?:int}>|null $eligible_services_snapshot
 * @property string|null $sale_id
 * @property int $total_sessions
 * @property int $remaining_sessions
 * @property CarbonImmutable|Carbon|null $expires_at
 * @property CarbonImmutable|Carbon|null $activated_at
 * @property string $status
 * @property string|null $archived_from_status
 * @property CarbonImmutable|Carbon|null $archived_at
 * @property int $lock_version
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'customer_id',
    'package_template_id',
    'name_snapshot',
    'price_cents_snapshot',
    'total_sessions_snapshot',
    'validity_days_snapshot',
    'eligible_services_snapshot',
    'sale_id',
    'total_sessions',
    'remaining_sessions',
    'expires_at',
    'activated_at',
    'status',
    'archived_from_status',
    'archived_at',
    'lock_version',
])]
#[UsePolicy(CustomerPackagePolicy::class)]
class CustomerPackage extends Model
{
    /** @use HasFactory<CustomerPackageFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'pending',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'total_sessions' => 'integer',
            'remaining_sessions' => 'integer',
            'price_cents_snapshot' => 'integer',
            'total_sessions_snapshot' => 'integer',
            'validity_days_snapshot' => 'integer',
            'eligible_services_snapshot' => 'array',
            'expires_at' => 'date',
            'activated_at' => 'datetime',
            'archived_at' => 'datetime',
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

    /** @return BelongsTo<PackageTemplate, $this> */
    public function packageTemplate(): BelongsTo
    {
        return $this->belongsTo(PackageTemplate::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return HasOne<FinancialObligation, $this> */
    public function financialObligation(): HasOne
    {
        return $this->hasOne(FinancialObligation::class);
    }

    /** @return HasMany<PackageUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(PackageUsage::class);
    }

    /** @return HasMany<PackageUsage, $this> */
    public function packageUsages(): HasMany
    {
        return $this->hasMany(PackageUsage::class);
    }

    /** @return HasMany<PackageUsageReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(PackageUsageReservation::class);
    }

    /** @return HasMany<CustomerPackageService, $this> */
    public function serviceBalances(): HasMany
    {
        return $this->hasMany(CustomerPackageService::class);
    }
}
