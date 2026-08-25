<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['tenant_id', 'key', 'name', 'description', 'is_system'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'is_system' => false,
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            if ($role->is_system) {
                throw new \LogicException('System roles can only be created by the platform catalog.');
            }
        });

        static::updating(function (self $role): void {
            if ((bool) $role->getRawOriginal('is_system') && $role->isDirty(['key', 'name', 'description', 'is_system'])) {
                throw new \LogicException('System roles are immutable in the tenant domain.');
            }

            if ($role->isDirty('is_system') && $role->is_system) {
                throw new \LogicException('System roles can only be created by the platform catalog.');
            }
        });

        static::deleting(function (self $role): void {
            if ($role->is_system) {
                throw new \LogicException('System roles cannot be deleted in the tenant domain.');
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<Permission, $this, Pivot, 'pivot'> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, (new RolePermission)->getTable())
            ->wherePivot('tenant_id', $this->tenant_id)
            ->withPivot(['tenant_id', 'created_at']);
    }

    /** @return HasMany<RolePermission, $this> */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /** @return HasMany<MembershipRole, $this> */
    public function membershipRoles(): HasMany
    {
        return $this->hasMany(MembershipRole::class);
    }

    /** @return BelongsToMany<Membership, $this, Pivot, 'pivot'> */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_roles')
            ->wherePivot('tenant_id', $this->tenant_id)
            ->wherePivotNull('revoked_at')
            ->withPivot(['tenant_id', 'scope_kind', 'assignment_scope', 'unit_id', 'revoked_at', 'lock_version']);
    }
}
