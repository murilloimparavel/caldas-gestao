<?php

namespace App\Models;

use App\Policies\ClosingSessionPolicy;
use Database\Factories\ClosingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $closing_subject
 * @property string $currency
 * @property int $expected_total_cents
 * @property int $final_total_cents
 * @property string $status
 * @property string|null $receipt_number
 * @property array<string, mixed>|null $receipt_payload
 * @property string|null $idempotency_key
 * @property string|null $closed_by_user_id
 * @property int $lock_version
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'closing_subject',
    'currency',
    'expected_total_cents',
    'final_total_cents',
    'status',
    'receipt_number',
    'receipt_payload',
    'idempotency_key',
    'closed_by_user_id',
    'lock_version',
])]
#[UsePolicy(ClosingSessionPolicy::class)]
class ClosingSession extends Model
{
    /** @use HasFactory<ClosingSessionFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'currency' => 'BRL',
        'final_total_cents' => 0,
        'status' => 'draft',
        'lock_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'expected_total_cents' => 'integer',
            'final_total_cents' => 'integer',
            'receipt_payload' => 'array',
            'lock_version' => 'integer',
        ];
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
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /** @return BelongsToMany<Sale, $this> */
    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class, 'closing_session_sales', 'closing_session_id', 'sale_id');
    }
}
