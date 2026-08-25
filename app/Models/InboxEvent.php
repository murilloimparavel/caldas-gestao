<?php

namespace App\Models;

use App\Enums\InboxStatus;
use Database\Factories\InboxEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property InboxStatus $status */
#[Fillable(['tenant_id', 'consumer', 'event_id', 'event_type', 'event_version', 'correlation_id', 'causation_id', 'payload', 'status', 'attempts', 'received_at', 'processed_at', 'available_at', 'last_attempt_at', 'dead_at', 'locked_at', 'lease_until', 'locked_by', 'last_error'])]
class InboxEvent extends Model
{
    /** @use HasFactory<InboxEventFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => InboxStatus::Received->value,
        'payload' => [],
        'event_version' => 1,
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => InboxStatus::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'available_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'dead_at' => 'datetime',
            'locked_at' => 'datetime',
            'lease_until' => 'datetime',
            'attempts' => 'integer',
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
}
