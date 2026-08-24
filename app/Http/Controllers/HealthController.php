<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function app(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'service' => 'app']);
    }

    public function database(): JsonResponse
    {
        try {
            DB::connection()->select('select 1');
        } catch (Throwable) {
            return response()->json(['status' => 'unhealthy', 'service' => 'database'], 503);
        }

        return response()->json(['status' => 'ok', 'service' => 'database']);
    }

    public function redis(): JsonResponse
    {
        try {
            Redis::connection()->ping();
        } catch (Throwable) {
            return response()->json(['status' => 'unhealthy', 'service' => 'redis'], 503);
        }

        return response()->json(['status' => 'ok', 'service' => 'redis']);
    }
}
