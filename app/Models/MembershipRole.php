<?php

namespace App\Models;

use App\Enums\MembershipRoleScope;
use Database\Factories\MembershipRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property MembershipRoleScope $scope_kind
 * @property int $lock_version
 */
#[Fillable(['tenant_id', 'membership_id', 'role_id', 'scope_kind', 'assignment_scope', 'unit_id', 'revoked_at'])]
class MembershipRole extends Model
{
    /** @use HasFactory<MembershipRoleFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $attributes = [
        'lock_version' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $membershipRole): void {
            $membershipRole->created_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'scope_kind' => MembershipRoleScope::class,
            'revoked_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
