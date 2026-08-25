<?php

namespace App\Models;

use App\Policies\CashShiftPolicy;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\CashShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $opened_by_user_id
 * @property string|null $closed_by_user_id
 * @property int $initial_amount_cents
 * @property int $expected_amount_cents
 * @property int|null $final_amount_cents
 * @property int|null $difference_cents
 * @property string $status
 * @property CarbonImmutable|Carbon $opened_at
 * @property CarbonImmutable|Carbon|null $closed_at
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'opened_by_user_id',
    'closed_by_user_id',
    'initial_amount_cents',
    'expected_amount_cents',
    'final_amount_cents',
    'difference_cents',
    'status',
    'opened_at',
    'closed_at',
    'notes',
    'lock_version',
])]
#[UsePolicy(CashShiftPolicy::class)]
class CashShift extends Model
{
    /** @use HasFactory<CashShiftFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'open',
        'initial_amount_cents' => 0,
        'expected_amount_cents' => 0,
        'lock_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'initial_amount_cents' => 'integer',
            'expected_amount_cents' => 'integer',
            'final_amount_cents' => 'integer',
            'difference_cents' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
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
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cash_shift_id')->orderByDesc('created_at');
    }
}
