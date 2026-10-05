<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAssistantEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('assistant.enabled', false), 404);

        return $next($request);
    }
}
