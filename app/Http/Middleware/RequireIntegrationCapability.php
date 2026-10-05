<?php

namespace App\Http\Middleware;

use App\Models\Integrations\IntegrationCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireIntegrationCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $credential = $request->attributes->get(IntegrationCredential::class);

        abort_unless(
            $credential instanceof IntegrationCredential
                && in_array($capability, $credential->capabilities, true)
                && $request->user()?->tokenCan($capability),
            403,
        );

        return $next($request);
    }
}
