<?php

namespace App\Providers;

use App\Http\Controllers\TelemetryBrowserController;
use App\Support\Telemetry\IncidentCollector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IncidentCollector::class);
    }

    public function boot(): void
    {
        RateLimiter::for('telemetry.browser', fn (Request $request): array => [
            Limit::perMinute((int) config('telemetry.global_per_minute', 100))->by('telemetry-global'),
            Limit::perMinute((int) config('telemetry.per_ip_per_minute', 10))->by('telemetry-ip:'.hash('sha256', (string) $request->ip())),
        ]);
        Route::middleware(['web', 'throttle:telemetry.browser'])->post('/telemetry/browser', TelemetryBrowserController::class)->name('telemetry.browser');
    }
}
