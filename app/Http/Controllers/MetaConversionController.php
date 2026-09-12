<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMetaConversionRequest;
use App\Support\MetaConversionsApi;
use Illuminate\Http\JsonResponse;

final class MetaConversionController extends Controller
{
    public function __invoke(StoreMetaConversionRequest $request, MetaConversionsApi $metaConversionsApi): JsonResponse
    {
        $metaConversionsApi->send($request->validated(), $request);

        return response()->json(['accepted' => true], 202);
    }
}
