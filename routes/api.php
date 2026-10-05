<?php

use App\Http\Controllers\Integrations\IntegrationCapabilityController;
use App\Http\Controllers\Integrations\IntegrationCatalogController;
use App\Http\Controllers\Integrations\ProposedOperationController;
use App\Http\Middleware\RequireIntegrationCapability;
use App\Http\Middleware\RequireIntegrationCredential;
use App\Models\Integrations\IntegrationCredential;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('v1/context', function (Request $request) {
    $context = $request->attributes->get(TenantContext::class);
    $credential = $request->attributes->get(IntegrationCredential::class);

    abort_unless($context instanceof TenantContext && $credential instanceof IntegrationCredential, 403);

    return response()->json([
        'data' => [
            'actor' => ['authenticated' => true],
            'tenant' => ['bound' => true],
            'unit' => ['bound' => $context->unit !== null],
            'capabilities' => $credential->capabilities,
        ],
    ])->header('Cache-Control', 'no-store, private');
})->middleware(['auth:api', 'throttle:60,1', RequireIntegrationCredential::class, RequireIntegrationCapability::class.':context:read'])->name('api.v1.context');

Route::get('v1/capabilities', [IntegrationCapabilityController::class, 'index'])
    ->middleware(['auth:api', 'throttle:60,1', RequireIntegrationCredential::class])
    ->name('api.v1.capabilities');

Route::middleware(['auth:api', 'throttle:60,1', RequireIntegrationCredential::class, RequireIntegrationCapability::class.':catalog:read'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('categories', [IntegrationCatalogController::class, 'categories'])->name('categories.index');
        Route::get('categories/{category}', [IntegrationCatalogController::class, 'category'])->whereUuid('category')->name('categories.show');
        Route::get('services', [IntegrationCatalogController::class, 'services'])->name('services.index');
        Route::get('services/{service}', [IntegrationCatalogController::class, 'service'])->whereUuid('service')->name('services.show');
        Route::get('professionals', [IntegrationCatalogController::class, 'professionals'])->name('professionals.index');
        Route::get('professionals/{professional}', [IntegrationCatalogController::class, 'professional'])->whereUuid('professional')->name('professionals.show');
    });

Route::get('v1/setup/status', [IntegrationCatalogController::class, 'setupStatus'])
    ->middleware(['auth:api', 'throttle:60,1', RequireIntegrationCredential::class, RequireIntegrationCapability::class.':setup:read'])
    ->name('api.v1.setup.status');

Route::middleware(['auth:api', 'throttle:20,1', RequireIntegrationCredential::class, RequireIntegrationCapability::class.':operations:propose'])
    ->prefix('v1/operations')
    ->name('api.v1.operations.')
    ->group(function (): void {
        Route::post('/', [ProposedOperationController::class, 'propose'])->name('propose');
        Route::get('{operation}', [ProposedOperationController::class, 'apiShow'])->whereUuid('operation')->name('show');
    });
