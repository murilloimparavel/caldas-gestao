<?php

namespace App\Models;

use Database\Factories\OnlineBookingVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'unit_id', 'publication_id', 'campaign_link_id', 'visitor_hash', 'landing_path', 'referer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'consent', 'occurred_at'])]
class OnlineBookingVisit extends Model
{
    /** @use HasFactory<OnlineBookingVisitFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['consent' => 'boolean', 'occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<OnlineBookingPublication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(OnlineBookingPublication::class);
    }

    /** @return BelongsTo<OnlineBookingCampaignLink, $this> */
    public function campaignLink(): BelongsTo
    {
        return $this->belongsTo(OnlineBookingCampaignLink::class, 'campaign_link_id');
    }
}
