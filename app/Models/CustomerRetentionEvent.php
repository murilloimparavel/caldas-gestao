<?php

namespace App\Models;

use Database\Factories\CustomerRetentionEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'unit_id', 'customer_id', 'actor_user_id', 'event_type', 'idempotency_key', 'metadata', 'occurred_at'])]
class CustomerRetentionEvent extends Model
{
    /** @use HasFactory<CustomerRetentionEventFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
