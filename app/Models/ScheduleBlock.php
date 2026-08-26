<?php

namespace App\Models;

use App\Policies\ScheduleBlockPolicy;
use Carbon\Carbon;
use Database\Factories\ScheduleBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $lock_version
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
#[Fillable(['tenant_id', 'unit_id', 'professional_id', 'starts_at', 'ends_at', 'timezone', 'reason', 'status'])]
#[UsePolicy(ScheduleBlockPolicy::class)]
class ScheduleBlock extends Model
{
    /** @use HasFactory<ScheduleBlockFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'active', 'lock_version' => 0];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'lock_version' => 'integer'];
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
}
