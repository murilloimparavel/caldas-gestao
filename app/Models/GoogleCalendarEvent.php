<?php

namespace App\Models;

use Database\Factories\GoogleCalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon|null $synced_at */
#[Fillable(['connection_id', 'tenant_id', 'unit_id', 'appointment_id', 'google_event_id', 'sync_status', 'operation', 'appointment_lock_version', 'payload_hash', 'last_error', 'synced_at'])]
class GoogleCalendarEvent extends Model
{
    /** @use HasFactory<GoogleCalendarEventFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'appointment_lock_version' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GoogleCalendarConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarConnection::class);
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

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
