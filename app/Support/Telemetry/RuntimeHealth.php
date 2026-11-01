<?php

namespace App\Support\Telemetry;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RuntimeHealth
{
    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function report(array $payload): array
    {
        if (array_diff(array_keys($payload), ['version', 'overall', 'checks']) !== []) {
            throw ValidationException::withMessages(['payload' => 'Unsupported health fields.']);
        }
        $data = Validator::make($payload, [
            'version' => ['required', 'integer', 'in:1'],
            'overall' => ['required', Rule::in(['healthy', 'degraded', 'paused'])],
            'checks' => ['required', 'array', 'size:8'],
            'checks.*' => ['array:key,status,code,value'],
            'checks.*.key' => ['required', 'distinct', Rule::in(['image', 'network', 'auth_file', 'proxy', 'paused', 'freshness', 'queue', 'disk'])],
            'checks.*.status' => ['required', Rule::in(['ok', 'missing', 'error', 'paused', 'stale', 'unknown'])],
            'checks.*.code' => ['sometimes', Rule::in(['ready', 'missing', 'unavailable', 'expired', 'not_checked', 'paused', 'low_space', 'stale', 'pending', 'running', 'quota', 'unknown', 'ok', 'error'])],
            'checks.*.value' => ['sometimes', 'integer', 'min:0', 'max:1000000000000000'],
        ])->validate();
        $statuses = array_column($data['checks'], 'status', 'key');
        $allHealthy = count(array_filter($statuses, fn (string $status): bool => $status !== 'ok')) === 0;
        if (($data['overall'] === 'healthy' && ! $allHealthy) || ($data['overall'] === 'degraded' && $allHealthy) || ($data['overall'] === 'paused' && $statuses['paused'] !== 'paused')) {
            throw ValidationException::withMessages(['overall' => 'Health summary does not match the checks.']);
        }
        $result = DB::transaction(function () use ($data): array {
            $previous = DB::table('telemetry_runtime_health')->where('component', 'runner')->lockForUpdate()->first();
            $healthy = $data['overall'] === 'healthy';
            $values = ['status' => $data['overall'], 'checks' => json_encode($data['checks'], JSON_THROW_ON_ERROR), 'last_seen_at' => now(), 'last_healthy_at' => $healthy ? now() : $previous?->last_healthy_at, 'resolved_at' => $healthy && $previous !== null && $previous->status !== 'healthy' ? now() : ($healthy ? $previous?->resolved_at : null)];
            DB::table('telemetry_runtime_health')->updateOrInsert(['component' => 'runner'], [...$values, 'first_seen_at' => $previous->first_seen_at ?? now()]);
            $snapshot = (array) DB::table('telemetry_runtime_health')->where('component', 'runner')->first();
            $snapshot['checks'] = json_decode($snapshot['checks'], true, flags: JSON_THROW_ON_ERROR);

            return $snapshot;
        });
        if ($data['overall'] === 'degraded') {
            app(IncidentCollector::class)->captureSignal('operational', 'runner_unavailable');
        }

        return $result;
    }
}
