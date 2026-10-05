<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Support\Integrations\IntegrationCapabilityCatalog;
use Illuminate\Http\JsonResponse;

final class IntegrationCapabilityController extends Controller
{
    public function index(IntegrationCapabilityCatalog $catalog): JsonResponse
    {
        return response()->json([
            'data' => [
                'capabilities' => $catalog->capabilities(),
                'operations' => $catalog->operations(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
