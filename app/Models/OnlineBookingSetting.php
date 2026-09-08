<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['tenant_id', 'unit_id', 'public_slug', 'description', 'cover_image_path', 'whatsapp_phone', 'phone', 'instagram_url', 'facebook_url', 'website_url', 'brand_color', 'booking_flow', 'minimum_notice_minutes', 'public_hours'])]
class OnlineBookingSetting extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory, HasUuids;

    protected $appends = ['cover_image_url'];

    protected $attributes = ['brand_color' => '#2563eb', 'booking_flow' => 'service_first', 'minimum_notice_minutes' => 0, 'lock_version' => 0];

    protected function casts(): array
    {
        return ['public_hours' => 'array', 'minimum_notice_minutes' => 'integer', 'lock_version' => 'integer'];
    }

    /** @return Attribute<string|null, void> */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->cover_image_path
                ? Storage::disk(config('filesystems.media_disk'))->url($this->cover_image_path)
                : null,
        );
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
