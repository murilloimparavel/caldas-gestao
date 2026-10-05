<?php

namespace App\Http\Middleware;

use App\Support\Performance\QueryMetricsCollector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CollectPerformanceMetrics
{
    public function __construct(private readonly QueryMetricsCollector $collector) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('testing') || ! config('performance.http_metrics_enabled', false)) {
            return $next($request);
        }

        $this->collector->reset();
        $response = $next($request);

        $response->headers->set('X-Performance-Query-Count', (string) $this->collector->queryCount());
        $response->headers->set('X-Performance-Query-Time-Ms', (string) $this->collector->queryTimeMs());

        return $response;
    }
}
