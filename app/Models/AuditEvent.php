<?php

namespace App\Models;

use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'tenant_id', 'unit_id', 'actor_user_id', 'action', 'resource_type', 'resource_id', 'request_id', 'correlation_id', 'reason', 'metadata', 'ip_address', 'user_agent_hash', 'occurred_at'])]
class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory;

    public $timestamps = false;

    public $incrementing = true;

    protected $attributes = [
        'metadata' => [],
    ];

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new \LogicException('Audit events are append-only.');
        });

        static::deleting(static function (): void {
            throw new \LogicException('Audit events are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /** @return Attribute<array<string, mixed>, array<string, mixed>> */
    protected function metadata(): Attribute
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
