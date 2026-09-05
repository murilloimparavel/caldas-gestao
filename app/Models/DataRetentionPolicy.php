<?php

namespace App\Models;

use Database\Factories\DataRetentionPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'unit_id', 'data_class', 'retention_days', 'anchor', 'enabled', 'created_by_user_id', 'updated_by_user_id'])]
class DataRetentionPolicy extends Model
{
    /** @use HasFactory<DataRetentionPolicyFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = [
        'anchor' => 'last_activity_at',
        'enabled' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $policy): void {
            if ($policy->data_class !== 'customer') {
                throw new \InvalidArgumentException('Unsupported retention data class.');
            }

            if (! in_array($policy->anchor, ['last_activity_at', 'created_at'], true)) {
                throw new \InvalidArgumentException('Unsupported retention policy anchor.');
            }

            if ($policy->retention_days < 1) {
                throw new \InvalidArgumentException('Retention days must be at least one.');
            }
        });
    }

    protected function casts(): array
    {
        return ['retention_days' => 'integer', 'enabled' => 'boolean'];
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

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
