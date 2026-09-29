<?php

namespace App\Models;

use App\Enums\OnlineBookingHandleStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property OnlineBookingHandleStatus $status
 * @property CarbonInterface|null $redirect_until
 */
#[Fillable(['tenant_id', 'unit_id', 'handle', 'status', 'redirect_until'])]
class OnlineBookingHandle extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => OnlineBookingHandleStatus::class,
            'redirect_until' => 'datetime',
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

    /** @param Builder<OnlineBookingHandle> $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', OnlineBookingHandleStatus::Current->value);
    }

    /** @param Builder<OnlineBookingHandle> $query */
    public function scopeReserved(Builder $query): void
    {
        $query->where('status', OnlineBookingHandleStatus::Reserved->value);
    }

    /** @param Builder<OnlineBookingHandle> $query */
    public function scopeActiveRedirect(Builder $query): void
    {
        $query->where('status', OnlineBookingHandleStatus::Redirect->value)
            ->where('redirect_until', '>', now());
    }

    public function isCurrent(): bool
    {
        return $this->status === OnlineBookingHandleStatus::Current;
    }

    public function isRedirectActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->status === OnlineBookingHandleStatus::Redirect
            && $this->redirect_until instanceof CarbonInterface
            && $this->redirect_until->isAfter($at);
    }
}
