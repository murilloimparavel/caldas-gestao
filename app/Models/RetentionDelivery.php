<?php

namespace App\Models;

use Database\Factories\RetentionDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'unit_id', 'retention_campaign_id', 'retention_campaign_recipient_id', 'customer_id', 'channel', 'status', 'idempotency_key', 'attempts', 'last_error', 'available_at', 'sent_at'])]
class RetentionDelivery extends Model
{
    /** @use HasFactory<RetentionDeliveryFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'available_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    /** @return BelongsTo<RetentionCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(RetentionCampaign::class, 'retention_campaign_id');
    }

    /** @return BelongsTo<RetentionCampaignRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(RetentionCampaignRecipient::class, 'retention_campaign_recipient_id');
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
}
