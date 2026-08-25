<?php

namespace App\Models;

use App\Enums\EntitlementSource;
use App\Enums\EntitlementStatus;
use App\Support\PayloadGovernance;
use Database\Factories\EntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EntitlementStatus $status
 * @property EntitlementSource $source
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $quantity
 * @property array<string, mixed> $config
 */
#[Fillable(['tenant_id', 'key', 'status', 'quantity', 'starts_at', 'ends_at', 'source', 'config'])]
class Entitlement extends Model
{
    /** @use HasFactory<EntitlementFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => EntitlementStatus::Trial->value,
        'config' => '{}',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => EntitlementStatus::class,
            'source' => EntitlementSource::class,
            'quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /** @return Attribute<array<string, mixed>, array<string, mixed>> */
    protected function config(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): array => is_array($value) ? $value : (json_decode((string) $value, true) ?: []),
            set: static fn (mixed $value): string => json_encode((new PayloadGovernance)->entitlementConfig((array) $value), JSON_THROW_ON_ERROR),
        );
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActiveAt(?\DateTimeInterface $asOf = null): bool
    {
        $asOf ??= now();

        return $this->status->grantsAccess()
            && $this->starts_at <= $asOf
            && ($this->ends_at === null || $this->ends_at > $asOf);
    }
}
