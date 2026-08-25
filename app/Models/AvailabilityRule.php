<?php

namespace App\Models;

use App\Policies\AvailabilityRulePolicy;
use Database\Factories\AvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $lock_version */
#[Fillable(['tenant_id', 'unit_id', 'professional_id', 'weekday', 'starts_at', 'ends_at', 'timezone', 'status'])]
#[UsePolicy(AvailabilityRulePolicy::class)]
class AvailabilityRule extends Model
{
    /** @use HasFactory<AvailabilityRuleFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'active', 'lock_version' => 0];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'lock_version' => 'integer'];
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
