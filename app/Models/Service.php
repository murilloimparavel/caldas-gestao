<?php

namespace App\Models;

use App\Policies\ServicePolicy;
use App\Support\Images\MediaUrl;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string|null $category_id
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $price_cents
 * @property string $status
 * @property bool $online_booking_enabled
 * @property int $lock_version
 * @property string|null $image_path
 * @property-read string|null $image_url
 */
#[Fillable(['tenant_id', 'unit_id', 'category_id', 'name', 'description', 'duration_minutes', 'price_cents', 'status', 'online_booking_enabled', 'image_path', 'thumbnail_path'])]
#[UsePolicy(ServicePolicy::class)]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $appends = ['image_url', 'thumbnail_url'];

    protected $attributes = [
        'status' => 'active',
        'online_booking_enabled' => false,
        'lock_version' => 0,
    ];

    /** @return Attribute<string|null, void> */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => MediaUrl::for($this->image_path),
        );
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => MediaUrl::for($this->thumbnail_path));
    }

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price_cents' => 'integer',
            'lock_version' => 'integer',
            'online_booking_enabled' => 'boolean',
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

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Professional, $this, Pivot, 'pivot'> */
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class)
            ->withPivot(['tenant_id', 'unit_id'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<PackageTemplate, $this, Pivot, 'pivot'> */
    public function packageTemplates(): BelongsToMany
    {
        return $this->belongsToMany(PackageTemplate::class, 'package_template_services')
            ->withPivot(['tenant_id', 'unit_id'])
            ->withTimestamps();
    }
}
