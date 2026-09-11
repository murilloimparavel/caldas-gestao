<?php

namespace App\Models;

use Database\Factories\OnlineBookingPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $content
 * @property int $version
 * @property int $source_revision
 * @property Carbon $published_at
 * @property Carbon|null $superseded_at
 */
#[Fillable(['tenant_id', 'unit_id', 'site_id', 'public_domain_id', 'version', 'source_revision', 'content', 'content_hash', 'template_key', 'public_slug', 'published_by', 'published_at', 'superseded_at'])]
class OnlineBookingPublication extends Model
{
    /** @use HasFactory<OnlineBookingPublicationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['content' => 'array', 'version' => 'integer', 'source_revision' => 'integer', 'published_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    /** @return BelongsTo<OnlineBookingSite, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(OnlineBookingSite::class, 'site_id');
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
