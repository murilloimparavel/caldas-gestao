<?php

namespace App\Http\Controllers;

use App\Support\SaaSBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LastlinkWebhookController extends Controller
{
    public function __invoke(Request $request, SaaSBillingService $billing): JsonResponse
    {
        $secret = (string) config('services.lastlink.webhook_secret');
        abort_if($secret === '', 503, 'Lastlink webhook secret is not configured.');
        abort_unless(hash_equals($secret, (string) $request->header('X-Lastlink-Secret')), 401);
        $billing->processLastlink($request->json()->all());

        return response()->json(['received' => true]);
    }
}
