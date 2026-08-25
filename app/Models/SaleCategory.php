<?php

namespace App\Models;

use App\Policies\SaleCategoryPolicy;
use Database\Factories\SaleCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $name
 * @property string $key
 * @property string $type
 * @property string $uniqueness_scope
 * @property bool $is_active
 * @property int $lock_version
 */
#[Fillable(['tenant_id', 'unit_id', 'name', 'key', 'type', 'uniqueness_scope', 'is_active', 'lock_version'])]
#[UsePolicy(SaleCategoryPolicy::class)]
class SaleCategory extends Model
{
    /** @use HasFactory<SaleCategoryFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'type' => 'service',
        'uniqueness_scope' => 'none',
        'is_active' => true,
        'lock_version' => 1,
    ];

    protected function casts(): array
    {
        return [
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

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
