<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RequireIntegrationManagement
{
    public function handle(Request $request, Closure $next): Response
    {
        Gate::authorize('manage-integrations');

        return $next($request);
    }
}
