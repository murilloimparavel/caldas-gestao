<?php

namespace App\Models;

use App\Policies\SubscriptionPlanPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionPlanFactory;
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
 * @property string $billing_cycle
 * @property bool $is_active
 * @property int $lock_version
 * @property CarbonImmutable|Carbon|null $created_at
 * @property CarbonImmutable|Carbon|null $updated_at
 * @property CarbonImmutable|Carbon|null $deleted_at
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'name',
    'description',
    'price_cents',
    'billing_cycle',
    'is_active',
    'lock_version',
])]
#[UsePolicy(SubscriptionPlanPolicy::class)]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
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
        return $this->belongsToMany(Service::class, 'subscription_plan_services')
            ->withPivot(['tenant_id', 'unit_id', 'max_uses_per_cycle'])
            ->withTimestamps();
    }

    /** @return HasMany<CustomerSubscription, $this> */
    public function customerSubscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }
}
