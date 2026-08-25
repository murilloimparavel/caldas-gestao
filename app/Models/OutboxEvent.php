<?php

namespace App\Models;

use App\Enums\OutboxStatus;
use Database\Factories\OutboxEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'tenant_id', 'unit_id', 'actor_user_id', 'aggregate_type', 'aggregate_id', 'aggregate_version', 'event_type', 'event_version', 'correlation_id', 'causation_id', 'payload', 'status', 'occurred_at', 'available_at', 'last_attempt_at', 'published_at', 'dead_at', 'locked_at', 'lease_until', 'locked_by', 'attempts', 'last_error', 'created_at'])]
/** @property OutboxStatus $status */
class OutboxEvent extends Model
{
    /** @use HasFactory<OutboxEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $attributes = [
        'status' => OutboxStatus::Pending->value,
        'payload' => [],
        'event_version' => 1,
        'aggregate_version' => 1,
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => OutboxStatus::class,
            'occurred_at' => 'datetime',
            'available_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'published_at' => 'datetime',
            'dead_at' => 'datetime',
            'locked_at' => 'datetime',
            'lease_until' => 'datetime',
            'attempts' => 'integer',
            'aggregate_version' => 'integer',
            'event_version' => 'integer',
        ];
    }

    /** @return Attribute<array<string, mixed>, array<string, mixed>> */
    protected function payload(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): array => is_array($value) ? $value : (json_decode((string) $value, true) ?: []),
            set: static fn (mixed $value): string => json_encode((array) $value, JSON_THROW_ON_ERROR),
        );
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
