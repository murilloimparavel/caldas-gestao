<?php

namespace App\Models;

use App\Policies\AppointmentPolicy;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @property int $lock_version */
#[Fillable(['tenant_id', 'unit_id', 'source_id', 'customer_id', 'professional_id', 'starts_at', 'ends_at', 'timezone', 'status', 'source', 'online_booking_campaign_link_id', 'color', 'reminder_enabled', 'fit_in', 'notes', 'cancelled_at', 'cancel_reason'])]
#[UsePolicy(AppointmentPolicy::class)]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'confirmed', 'source' => 'internal', 'reminder_enabled' => true, 'fit_in' => false, 'lock_version' => 0];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminder_enabled' => 'boolean',
            'fit_in' => 'boolean',
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<OnlineBookingCampaignLink, $this> */
    public function onlineBookingCampaignLink(): BelongsTo
    {
        return $this->belongsTo(OnlineBookingCampaignLink::class, 'online_booking_campaign_link_id');
    }

    /** @return HasMany<AppointmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AppointmentItem::class);
    }

    /** @return HasMany<AppointmentStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class);
    }

    /** @return HasMany<AppointmentSaleLink, $this> */
    public function saleLinks(): HasMany
    {
        return $this->hasMany(AppointmentSaleLink::class);
    }

    /** @return HasOne<AppointmentSaleLink, $this> */
    public function saleLink(): HasOne
    {
        return $this->hasOne(AppointmentSaleLink::class);
    }
}
