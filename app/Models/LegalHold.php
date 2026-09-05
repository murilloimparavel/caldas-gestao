<?php

namespace App\Models;

use App\Policies\LegalHoldPolicy;
use Database\Factories\LegalHoldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'unit_id', 'customer_id', 'resource_type', 'resource_id', 'reason', 'reference', 'placed_by_user_id', 'released_by_user_id', 'placed_at', 'released_at'])]
#[UsePolicy(LegalHoldPolicy::class)]
class LegalHold extends Model
{
    /** @use HasFactory<LegalHoldFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = ['placed_at' => null];

    protected function casts(): array
    {
        return ['placed_at' => 'datetime', 'released_at' => 'datetime'];
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }
}
