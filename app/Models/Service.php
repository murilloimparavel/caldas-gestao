<?php

namespace App\Models;

use App\Policies\ServicePolicy;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** @property int $duration_minutes @property int $price_cents @property int $lock_version @property string|null $category_id */
#[Fillable(['tenant_id', 'unit_id', 'category_id', 'name', 'description', 'duration_minutes', 'price_cents', 'status'])]
#[UsePolicy(ServicePolicy::class)]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'active',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price_cents' => 'integer',
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

    /** @return BelongsToMany<Professional, $this, Pivot, 'pivot'> */
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class)
            ->withPivot(['tenant_id', 'unit_id'])
            ->withTimestamps();
    }
}
