<?php

namespace App\Support\Telemetry;

use App\Models\TelemetryIncident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IncidentHistory
{
    public static function record(TelemetryIncident $incident, string $event, ?string $from = null, ?string $to = null, ?string $reason = null, ?string $actor = null): void
    {
        DB::table('telemetry_incident_events')->insert(['id' => (string) Str::uuid(), 'incident_id' => $incident->id, 'actor_user_id' => $actor, 'owner_user_id' => $incident->owner_user_id, 'event_type' => $event, 'from_status' => $from, 'to_status' => $to, 'reason_code' => $reason, 'created_at' => now()]);
        $keep = DB::table('telemetry_incident_events')->where('incident_id', $incident->id)->orderByDesc('created_at')->orderByDesc('id')->limit(100)->pluck('id');
        DB::table('telemetry_incident_events')->where('incident_id', $incident->id)->whereNotIn('id', $keep)->delete();
    }
}
