<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSharedBookingHost
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('public_booking_shared_host') === true, 404);

        return $next($request);
    }
}
