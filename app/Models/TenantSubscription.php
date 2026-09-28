<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TenantSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'platform_plan_id', 'provider', 'external_subscription_id', 'external_product_id', 'status', 'billing_cycle', 'starts_at', 'ends_at', 'next_billing_at', 'grace_ends_at', 'last_payment_at', 'metadata'])]
/** @property CarbonImmutable|string|null $ends_at */
/** @property CarbonImmutable|string|null $grace_ends_at */
class TenantSubscription extends Model
{
    /** @use HasFactory<TenantSubscriptionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'next_billing_at' => 'datetime', 'grace_ends_at' => 'datetime', 'last_payment_at' => 'datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<PlatformPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'platform_plan_id');
    }

    public function grantsAccess(): bool
    {
        return match ($this->status) {
            'trial', 'active' => $this->ends_at === null || CarbonImmutable::parse($this->ends_at)->isFuture(),
            'grace' => $this->grace_ends_at !== null && CarbonImmutable::parse($this->grace_ends_at)->isFuture(),
            default => false,
        };
    }
}
