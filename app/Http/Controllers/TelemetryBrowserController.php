<?php

namespace App\Http\Controllers;

use App\Http\Requests\TelemetryBrowserRequest;
use App\Support\Telemetry\IncidentCollector;
use Illuminate\Http\JsonResponse;

final class TelemetryBrowserController extends Controller
{
    public function __invoke(TelemetryBrowserRequest $request, IncidentCollector $collector): JsonResponse
    {
        $collector->captureBrowser($request->validated());

        return response()->json(['accepted' => true], 202);
    }
}
