<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonImmutable|null $lease_expires_at */
final class TelemetryIncident extends Model
{
    use HasUuids;

    protected $fillable = ['fingerprint', 'release', 'kind', 'category', 'exception_class', 'route_name', 'frames', 'occurrences', 'status', 'result', 'attempts', 'claim_id', 'lease_expires_at', 'first_seen_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['frames' => 'array', 'occurrences' => 'integer', 'attempts' => 'integer', 'first_seen_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime', 'lease_expires_at' => 'immutable_datetime'];
    }
}
