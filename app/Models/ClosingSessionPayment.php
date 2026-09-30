<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id', 'unit_id', 'closing_session_id', 'cash_shift_id', 'payment_method', 'amount_cents', 'tendered_cents', 'change_cents', 'recorded_by_user_id', 'recorded_at', 'reversal_of_id', 'is_reversal', 'reversal_reason'])]
class ClosingSessionPayment extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new \LogicException('Closing session payments are append-only.');
        });

        static::deleting(static function (): void {
            throw new \LogicException('Closing session payments are append-only.');
        });
    }

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'tendered_cents' => 'integer', 'change_cents' => 'integer', 'recorded_at' => 'datetime', 'is_reversal' => 'boolean'];
    }

    /** @return BelongsTo<ClosingSession, $this> */
    public function closingSession(): BelongsTo
    {
        return $this->belongsTo(ClosingSession::class);
    }

    /** @return BelongsTo<CashShift, $this> */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return BelongsTo<self, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasOne<self, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }
}
