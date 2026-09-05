<?php

namespace App\Models;

use Database\Factories\SubscriptionCycleUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'unit_id', 'customer_subscription_id', 'subscription_cycle_id', 'service_id', 'max_uses_snapshot', 'used_count'])]
class SubscriptionCycleUsage extends Model
{
    /** @use HasFactory<SubscriptionCycleUsageFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['used_count' => 0];

    protected function casts(): array
    {
        return ['max_uses_snapshot' => 'integer', 'used_count' => 'integer'];
    }

    /** @return BelongsTo<SubscriptionCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCycle::class, 'subscription_cycle_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return HasMany<SubscriptionUsageEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(SubscriptionUsageEntry::class);
    }
}
