<?php

namespace App\Models;

use Database\Factories\AppointmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'unit_id', 'appointment_id', 'service_id', 'professional_id', 'service_name_snapshot', 'duration_minutes', 'price_cents', 'currency', 'position'])]
class AppointmentItem extends Model
{
    /** @use HasFactory<AppointmentItemFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['currency' => 'BRL', 'position' => 1];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'price_cents' => 'integer', 'position' => 'integer'];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
