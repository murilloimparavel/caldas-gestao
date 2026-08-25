<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property MembershipStatus $status
 * @property int $lock_version
 */
#[Fillable(['tenant_id', 'user_id', 'status', 'joined_at', 'revoked_at'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => MembershipStatus::Invited->value,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
            'revoked_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<MembershipUnit, $this> */
    public function membershipUnits(): HasMany
    {
        return $this->hasMany(MembershipUnit::class);
    }

    /** @return BelongsToMany<Unit, $this, Pivot, 'pivot'> */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'membership_units')
            ->wherePivot('tenant_id', $this->tenant_id)
            ->withPivot(['tenant_id', 'is_primary', 'created_at']);
    }

    /** @return HasMany<MembershipRole, $this> */
    public function membershipRoles(): HasMany
    {
        return $this->hasMany(MembershipRole::class);
    }

    /** @return BelongsToMany<Role, $this, Pivot, 'pivot'> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_roles')
            ->wherePivot('tenant_id', $this->tenant_id)
            ->wherePivotNull('revoked_at')
            ->withPivot(['tenant_id', 'scope_kind', 'assignment_scope', 'unit_id', 'revoked_at', 'lock_version']);
    }
}
