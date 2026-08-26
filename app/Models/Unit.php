<?php

namespace App\Models;

use App\Enums\UnitStatus;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property UnitStatus $status
 * @property int $lock_version
 */
#[Fillable(['tenant_id', 'slug', 'name', 'status', 'timezone', 'address', 'online_booking_enabled'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => UnitStatus::Active->value,
        'online_booking_enabled' => false,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'address' => 'array',
            'online_booking_enabled' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<MembershipUnit, $this> */
    public function membershipUnits(): HasMany
    {
        return $this->hasMany(MembershipUnit::class);
    }

    /** @return BelongsToMany<Membership, $this, Pivot, 'pivot'> */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_units')
            ->wherePivot('tenant_id', $this->tenant_id)
            ->withPivot(['tenant_id', 'is_primary', 'created_at']);
    }

    /** @return HasMany<MembershipRole, $this> */
    public function membershipRoles(): HasMany
    {
        return $this->hasMany(MembershipRole::class);
    }
}
