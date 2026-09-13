<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMetaConversionRequest;
use App\Support\MetaConversionsApi;
use Illuminate\Http\JsonResponse;

final class MetaConversionController extends Controller
{
    public function __invoke(StoreMetaConversionRequest $request, MetaConversionsApi $metaConversionsApi): JsonResponse
    {
        /** @var array{event_name: string, event_id: string, event_source_url: string, fbp?: string|null, fbc?: string|null, custom_data?: array<string, mixed>|null} $event */
        $event = $request->validated();

        $metaConversionsApi->send($event, $request);

        return response()->json(['accepted' => true], 202);
    }
}
