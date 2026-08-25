<?php

namespace App\Models;

use App\Policies\ProfessionalPolicy;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** @property int $lock_version */
#[Fillable(['tenant_id', 'unit_id', 'name', 'email', 'phone', 'status'])]
#[UsePolicy(ProfessionalPolicy::class)]
class Professional extends Model
{
    /** @use HasFactory<ProfessionalFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'active',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
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
}
