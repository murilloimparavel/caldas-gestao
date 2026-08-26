<?php

namespace App\Models;

use Database\Factories\CommissionSettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $professional_id
 * @property int $total_amount_cents
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon $paid_at
 * @property string $user_id
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable(['tenant_id', 'unit_id', 'professional_id', 'total_amount_cents', 'period_start', 'period_end', 'paid_at', 'user_id', 'notes', 'lock_version'])]
class CommissionSettlement extends Model
{
    /** @use HasFactory<CommissionSettlementFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'total_amount_cents' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
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

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CommissionAccrual, $this> */
    public function accruals(): HasMany
    {
        return $this->hasMany(CommissionAccrual::class, 'settlement_id');
    }
}
