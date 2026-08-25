<?php

namespace App\Models;

use Database\Factories\MembershipUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property bool $is_primary */
#[Fillable(['tenant_id', 'membership_id', 'unit_id', 'is_primary'])]
class MembershipUnit extends Model
{
    /** @use HasFactory<MembershipUnitFactory> */
    use HasFactory;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $membershipUnit): void {
            $membershipUnit->created_at ??= now();
        });
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

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
