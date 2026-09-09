<?php

namespace App\Models;

use App\Enums\OnlineBookingPublicationStatus;
use Database\Factories\OnlineBookingSiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id', 'unit_id', 'public_domain_id', 'active_publication_id', 'public_slug', 'template_key', 'status', 'draft_revision', 'published_at', 'unpublished_at', 'lock_version'])]
class OnlineBookingSite extends Model
{
    /** @use HasFactory<OnlineBookingSiteFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => OnlineBookingPublicationStatus::class,
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'draft_revision' => 'integer',
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

    /** @return BelongsTo<TenantDomain, $this> */
    public function publicDomain(): BelongsTo
    {
        return $this->belongsTo(TenantDomain::class, 'public_domain_id');
    }

    /** @return HasOne<OnlineBookingDraft, $this> */
    public function draft(): HasOne
    {
        return $this->hasOne(OnlineBookingDraft::class, 'site_id');
    }

    /** @return BelongsTo<OnlineBookingPublication, $this> */
    public function activePublication(): BelongsTo
    {
        return $this->belongsTo(OnlineBookingPublication::class, 'active_publication_id');
    }
}
