<?php

namespace App\Models;

use Database\Factories\PlatformPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'price_cents', 'billing_cycle', 'trial_days', 'features', 'limits', 'lastlink_product_id', 'lastlink_offer_id', 'is_active'])]
class PlatformPlan extends Model
{
    /** @use HasFactory<PlatformPlanFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'trial_days' => 'integer', 'features' => 'array', 'limits' => 'array', 'is_active' => 'boolean'];
    }

    /** @return HasMany<TenantSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }
}
