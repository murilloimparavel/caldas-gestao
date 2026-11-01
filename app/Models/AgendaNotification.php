<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expires_at
 * @property Carbon|null $appointment_starts_at
 * @property Carbon|null $read_at
 * @property Carbon|null $push_processed_at
 * @property array{professional_id: string, starts_at: int, ends_at: int, service_ids: list<string>}|null $appointment_snapshot
 * @property int|null $appointment_version
 */
#[Fillable(['tenant_id', 'unit_id', 'user_id', 'professional_id', 'appointment_id', 'category', 'deduplication_key', 'title', 'body', 'appointment_starts_at', 'appointment_version', 'appointment_snapshot', 'expires_at', 'read_at', 'push_processed_at'])]
class AgendaNotification extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['appointment_snapshot' => 'array', 'appointment_starts_at' => 'datetime', 'appointment_version' => 'integer', 'expires_at' => 'datetime', 'read_at' => 'datetime', 'push_processed_at' => 'datetime'];
    }
}
