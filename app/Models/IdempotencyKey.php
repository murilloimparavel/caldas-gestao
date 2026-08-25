<?php

namespace App\Models;

use App\Enums\IdempotencyStatus;
use Database\Factories\IdempotencyKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property IdempotencyStatus $status */
#[Fillable(['tenant_id', 'actor_user_id', 'key', 'request_hash', 'status', 'response_code', 'resource_type', 'resource_id', 'response_ref', 'expires_at', 'completed_at'])]
class IdempotencyKey extends Model
{
    /** @use HasFactory<IdempotencyKeyFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => IdempotencyStatus::Started->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return Attribute<array<string, mixed>|null, array<string, mixed>|null> */
    protected function responseRef(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): ?array => $value === null ? null : (is_array($value) ? $value : json_decode((string) $value, true)),
            set: static fn (mixed $value): ?string => $value === null ? null : json_encode((array) $value, JSON_THROW_ON_ERROR),
        );
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function isExpired(?\DateTimeInterface $asOf = null): bool
    {
        return $this->expires_at !== null && $this->expires_at <= ($asOf ?? now());
    }
}
