<?php

namespace App\Support\Telemetry;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Throwable;

final class SignalListeners
{
    public function handled(RequestHandled $event): void
    {
        try {
            if ($this->skip($event->request)) {
                return;
            }
            $collector = app(IncidentCollector::class);
            $status = $event->response->getStatusCode();
            $route = $event->request->route()?->getName();
            if ($status >= 400 && ! ($status >= 500 && $event->request->attributes->get('telemetry.exception_recorded'))) {
                $collector->captureHttpStatus($status, $route);
            }
            $start = $event->request->server('REQUEST_TIME_FLOAT');
            $elapsed = is_numeric($start) ? max(0, (microtime(true) - (float) $start) * 1000) : 0;
            if ($elapsed >= (int) config('telemetry.slow_route_ms', 3000) && $this->sample()) {
                $collector->captureSignal('performance', 'slow_route', $route, $this->bucket($elapsed));
            }
        } catch (Throwable) {
            // Detection must never affect the response.
        }
    }

    public function query(QueryExecuted $event): void
    {
        try {
            $request = request();
            $collector = app(IncidentCollector::class);
            if ($collector->isCapturing() || $this->skip($request) || preg_match('/\btelemetry_(?:incidents|incident_events|runtime_health)\b/i', $event->sql) || $event->time < (int) config('telemetry.slow_query_ms', 1500) || $request->attributes->get('telemetry.slow_query_recorded') || ! $this->sample()) {
                return;
            }
            $request->attributes->set('telemetry.slow_query_recorded', true);
            $collector->captureSignal('performance', 'slow_query', $request->route()?->getName(), $this->bucket($event->time));
        } catch (Throwable) {
            // SQL and bindings are deliberately never serialized or logged here.
        }
    }

    private function skip(Request $request): bool
    {
        $route = $request->route()?->getName() ?? '';

        return ! config('telemetry.enabled') || config('telemetry.probe') || $request->header('X-Telemetry-Probe') === '1' || str_starts_with($route, 'telemetry.') || str_starts_with($route, 'admin.incidents.') || $request->is('telemetry/*', 'up', 'health', 'health/*');
    }

    private function sample(): bool
    {
        return random_int(1, 10000) <= max(0, min(10000, (int) ((float) config('telemetry.performance_sample_rate', 0.1) * 10000)));
    }

    private function bucket(float $milliseconds): string
    {
        return $milliseconds >= 10000 ? 'over_10s' : ($milliseconds >= 3000 ? '3s_10s' : 'under_3s');
    }
}
