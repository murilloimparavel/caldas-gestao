<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFirstLoginComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs('first-login-password.*')) {
            if ($user->temporary_password_expires_at?->isPast()) {
                abort(403, 'A senha temporária expirou. Solicite uma nova recuperação de acesso.');
            }

            return to_route('first-login-password.edit');
        }

        return $next($request);
    }
}
