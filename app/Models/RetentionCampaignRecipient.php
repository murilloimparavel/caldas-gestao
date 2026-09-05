<?php

namespace App\Models;

use Database\Factories\RetentionCampaignRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id', 'unit_id', 'retention_campaign_id', 'customer_id', 'channel', 'status', 'selected_at', 'consent_snapshot_at', 'last_activity_snapshot_at', 'retention_status_snapshot'])]
class RetentionCampaignRecipient extends Model
{
    /** @use HasFactory<RetentionCampaignRecipientFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['selected_at' => 'datetime', 'consent_snapshot_at' => 'datetime', 'last_activity_snapshot_at' => 'datetime'];
    }

    /** @return BelongsTo<RetentionCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RetentionCampaign::class, 'retention_campaign_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    /** @return HasOne<RetentionDelivery, $this> */
    public function delivery(): HasOne
    {
        return $this->hasOne(RetentionDelivery::class);
    }
}
