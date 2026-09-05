<?php

namespace App\Models;

use App\Policies\RetentionCampaignPolicy;
use Database\Factories\RetentionCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'unit_id', 'created_by_user_id', 'name', 'status', 'channel', 'purpose', 'segment_definition', 'subject', 'message', 'starts_at', 'ends_at', 'audience_snapshot_at', 'lock_version'])]
#[UsePolicy(RetentionCampaignPolicy::class)]
class RetentionCampaign extends Model
{
    /** @use HasFactory<RetentionCampaignFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'draft', 'purpose' => 'marketing', 'lock_version' => 0];

    protected function casts(): array
    {
        return [
            'segment_definition' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'audience_snapshot_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<RetentionCampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(RetentionCampaignRecipient::class);
    }

    /** @return HasMany<RetentionDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(RetentionDelivery::class);
    }
}
