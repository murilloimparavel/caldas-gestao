<?php

namespace App\Models;

use Database\Factories\OnlineBookingDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $content
 * @property int $revision
 */
#[Fillable(['tenant_id', 'unit_id', 'site_id', 'revision', 'content', 'content_hash', 'updated_by'])]
class OnlineBookingDraft extends Model
{
    /** @use HasFactory<OnlineBookingDraftFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['content' => 'array', 'revision' => 'integer'];
    }

    /** @return BelongsTo<OnlineBookingSite, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(OnlineBookingSite::class, 'site_id');
    }
}
