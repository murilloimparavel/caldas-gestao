<?php

namespace App\Models;

use App\Policies\ProfessionalPolicy;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Storage;

/** @property int $lock_version */
#[Fillable(['tenant_id', 'unit_id', 'name', 'email', 'phone', 'avatar_path', 'status', 'online_booking_enabled'])]
#[UsePolicy(ProfessionalPolicy::class)]
class Professional extends Model
{
    /** @use HasFactory<ProfessionalFactory> */
    use HasFactory, HasUuids;

    protected $appends = [
        'avatar_url',
    ];

    /**
     * @return Attribute<?string, void>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->avatar_path
            ? Storage::disk(config('filesystems.media_disk'))->url($this->avatar_path)
                : null,
        );
    }

    protected $attributes = [
        'status' => 'active',
        'online_booking_enabled' => false,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'online_booking_enabled' => 'boolean'];
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

    /** @return BelongsToMany<Service, $this, Pivot, 'pivot'> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withPivot(['tenant_id', 'unit_id'])
            ->withTimestamps();
    }

    /** @return HasMany<AvailabilityRule, $this> */
    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    /** @return HasMany<ScheduleBlock, $this> */
    public function scheduleBlocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class);
    }

    /** @return HasMany<CommissionRule, $this> */
    public function commissionRules(): HasMany
    {
        return $this->hasMany(CommissionRule::class);
    }

    /** @return HasMany<CommissionAccrual, $this> */
    public function commissionAccruals(): HasMany
    {
        return $this->hasMany(CommissionAccrual::class);
    }

    /** @return HasMany<CommissionSettlement, $this> */
    public function commissionSettlements(): HasMany
    {
        return $this->hasMany(CommissionSettlement::class);
    }
}
