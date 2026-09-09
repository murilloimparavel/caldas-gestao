<?php

namespace App\Models;

use Database\Factories\OnlineBookingCampaignLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'unit_id', 'site_id', 'name', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'short_code', 'is_active', 'created_by'])]
class OnlineBookingCampaignLink extends Model
{
    /** @use HasFactory<OnlineBookingCampaignLinkFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<OnlineBookingSite, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(OnlineBookingSite::class, 'site_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
