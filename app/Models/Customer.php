<?php

namespace App\Models;

use App\Policies\CustomerPolicy;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $lock_version */
#[Fillable(['tenant_id', 'unit_id', 'source_id', 'name', 'email', 'phone', 'phone_normalized', 'birth_date', 'notes', 'source_metadata', 'status', 'last_activity_at', 'retention_status', 'retention_marked_at', 'retention_reactivated_at', 'anonymized_at', 'anonymization_version'])]
#[UsePolicy(CustomerPolicy::class)]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'active',
        'retention_status' => 'none',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'source_metadata' => 'array',
            'last_activity_at' => 'datetime',
            'retention_marked_at' => 'datetime',
            'retention_reactivated_at' => 'datetime',
            'anonymized_at' => 'datetime',
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

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<CustomerPackage, $this> */
    public function packages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    /** @return HasMany<CustomerPackage, $this> */
    public function customerPackages(): HasMany
    {
        return $this->hasMany(CustomerPackage::class);
    }

    /** @return HasMany<CustomerSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    /** @return HasMany<CustomerCommunicationPreference, $this> */
    public function communicationPreferences(): HasMany
    {
        return $this->hasMany(CustomerCommunicationPreference::class);
    }

    /** @return HasMany<CustomerRetentionEvent, $this> */
    public function retentionEvents(): HasMany
    {
        return $this->hasMany(CustomerRetentionEvent::class);
    }

    /** @param Builder<Customer> $query */
    public function scopeInactiveFor(Builder $query, int $days): void
    {
        $query->where('last_activity_at', '<=', now()->subDays(max(1, $days)));
    }
}
