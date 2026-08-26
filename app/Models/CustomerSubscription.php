<?php

namespace App\Models;

use App\Policies\CustomerSubscriptionPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $customer_id
 * @property string $subscription_plan_id
 * @property int $price_cents
 * @property string $billing_cycle
 * @property string $status
 * @property CarbonImmutable|Carbon $start_date
 * @property CarbonImmutable|Carbon|null $next_billing_date
 * @property CarbonImmutable|Carbon|null $cancelled_at
 * @property string|null $notes
 * @property int $lock_version
 * @property CarbonImmutable|Carbon|null $created_at
 * @property CarbonImmutable|Carbon|null $updated_at
 * @property CarbonImmutable|Carbon|null $deleted_at
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'customer_id',
    'subscription_plan_id',
    'price_cents',
    'billing_cycle',
    'status',
    'start_date',
    'next_billing_date',
    'cancelled_at',
    'notes',
    'lock_version',
])]
#[UsePolicy(CustomerSubscriptionPolicy::class)]
class CustomerSubscription extends Model
{
    /** @use HasFactory<CustomerSubscriptionFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'active',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'next_billing_date' => 'date',
            'price_cents' => 'integer',
            'cancelled_at' => 'datetime',
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

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @param Builder<CustomerSubscription> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /** @param Builder<CustomerSubscription> $query */
    public function scopePaused(Builder $query): void
    {
        $query->where('status', 'paused');
    }
}
