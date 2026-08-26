<?php

namespace App\Models;

use App\Policies\PackageTemplatePolicy;
use Database\Factories\PackageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $name
 * @property string|null $description
 * @property int $price_cents
 * @property int $total_sessions
 * @property int $validity_days
 * @property bool $is_active
 * @property int $lock_version
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'name',
    'description',
    'price_cents',
    'total_sessions',
    'validity_days',
    'is_active',
    'lock_version',
])]
#[UsePolicy(PackageTemplatePolicy::class)]
class PackageTemplate extends Model
{
    /** @use HasFactory<PackageTemplateFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'validity_days' => 90,
        'is_active' => true,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'total_sessions' => 'integer',
            'validity_days' => 'integer',
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

    /** @return BelongsToMany<Service, $this, Pivot, 'pivot'> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'package_template_services')
            ->withPivot(['tenant_id', 'unit_id'])
            ->withTimestamps();
    }

    /** @return HasMany<CustomerPackage, $this> */
    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }
}
