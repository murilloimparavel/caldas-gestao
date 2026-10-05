<?php

use App\Http\Middleware\EnforceSaaSAccess;
use App\Http\Middleware\EnsureFirstLoginComplete;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\ResolveTenantDomain;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/health.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->is('admin') || $request->is('admin/*')
            ? route('admin.login')
            : route('login'));
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
            'tenant.domain' => ResolveTenantDomain::class,
            'saas.access' => EnforceSaaSAccess::class,
            'first.login.complete' => EnsureFirstLoginComplete::class,
            'super.admin' => EnsureSuperAdmin::class,
        ]);

        $middleware->web(append: [
            ResolveTenantDomain::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('outbox:pump')->everyMinute()->withoutOverlapping(2)->onOneServer();
        $schedule->command('inbox:reap')->everyMinute()->withoutOverlapping(2)->onOneServer();
        $schedule->command('app:expire-customer-packages')->daily()->withoutOverlapping(10)->onOneServer();
        $schedule->command('billing:reconcile')->hourly()->withoutOverlapping(2)->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
