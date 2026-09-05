<?php

namespace App\Http\Middleware;

use App\Support\SaaSBillingService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceSaaSAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = $request->attributes->get(TenantContext::class);
        if (! $context instanceof TenantContext) {
            return $next($request);
        }
        $subscription = app(SaaSBillingService::class)->ensureFreeTier($context->tenant);
        if (! $subscription->grantsAccess() && ! $request->routeIs('billing.*')) {
            return to_route('billing.index')->with('warning', 'Sua assinatura precisa ser renovada para continuar.');
        }

        return $next($request);
    }
}
