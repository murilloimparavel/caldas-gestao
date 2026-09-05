<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CarbonImmutable|Carbon $starts_on */
#[Fillable(['tenant_id', 'unit_id', 'customer_subscription_id', 'cycle_number', 'starts_on', 'ends_on', 'status', 'previous_cycle_id', 'next_cycle_id', 'renewal_key', 'closed_at'])]
class SubscriptionCycle extends Model
{
    /** @use HasFactory<SubscriptionCycleFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['cycle_number' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'closed_at' => 'datetime'];
    }

    /** @return BelongsTo<CustomerSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'customer_subscription_id');
    }

    /** @return BelongsTo<SubscriptionCycle, $this> */
    public function previousCycle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCycle::class, 'previous_cycle_id');
    }

    /** @return BelongsTo<SubscriptionCycle, $this> */
    public function nextCycle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCycle::class, 'next_cycle_id');
    }

    /** @return HasMany<SubscriptionCycleUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(SubscriptionCycleUsage::class);
    }

    /** @return HasMany<SubscriptionUsageEntry, $this> */
    public function usageEntries(): HasMany
    {
        return $this->hasMany(SubscriptionUsageEntry::class);
    }
}
