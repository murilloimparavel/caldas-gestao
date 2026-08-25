<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['key', 'description'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsToMany<Role, $this, Pivot, 'pivot'> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, (new RolePermission)->getTable())
            ->withPivot(['tenant_id', 'created_at']);
    }

    /** @return HasMany<RolePermission, $this> */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }
}
