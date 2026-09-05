<?php

namespace App\Models;

use Database\Factories\SubscriptionUsageEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'unit_id', 'customer_subscription_id', 'subscription_cycle_id', 'subscription_cycle_usage_id', 'service_id', 'quantity', 'idempotency_key', 'payload_hash', 'payload_metadata', 'actor_user_id', 'consumed_at', 'reversed_at', 'reversal_reason'])]
class SubscriptionUsageEntry extends Model
{
    /** @use HasFactory<SubscriptionUsageEntryFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['quantity' => 1];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'payload_metadata' => 'array', 'consumed_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    /** @return BelongsTo<SubscriptionCycleUsage, $this> */
    public function cycleUsage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCycleUsage::class, 'subscription_cycle_usage_id');
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
}
