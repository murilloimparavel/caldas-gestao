<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** @property CarbonImmutable|null $lease_expires_at */
final class TelemetryIncident extends Model
{
    use HasUuids;

    protected $fillable = ['fingerprint', 'release', 'kind', 'category', 'exception_class', 'route_name', 'frames', 'occurrences', 'status', 'result', 'attempts', 'claim_id', 'lease_expires_at', 'first_seen_at', 'last_seen_at', 'title', 'classification', 'module', 'severity', 'confidence', 'impact_summary', 'workflow_status', 'owner_user_id', 'lock_version', 'detection_source', 'priority', 'auto_diagnosis_eligible', 'diagnosis_threshold', 'http_status', 'duration_bucket', 'resolved_at'];

    protected function casts(): array
    {
        return ['frames' => 'array', 'occurrences' => 'integer', 'attempts' => 'integer', 'first_seen_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime', 'lease_expires_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime', 'lock_version' => 'integer', 'confidence' => 'integer', 'priority' => 'integer', 'diagnosis_threshold' => 'integer', 'http_status' => 'integer', 'auto_diagnosis_eligible' => 'boolean'];
    }
}
