<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class AdminAwareLoginResponse implements LoginResponseContract, PasskeyLoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        $adminLogin = $request->boolean('admin_login_intent')
            || (bool) $request->session()->pull('admin_login_intent', false);

        if ($adminLogin && ! $request->user()?->isSuperAdmin()) {
            $this->logout($request);

            if ($request->is('passkeys/login') && $request->wantsJson()) {
                return response()->json(['message' => 'These credentials are not authorized for the admin panel.'], 403);
            }

            return redirect()->route('admin.login')->withErrors([
                'email' => 'Estas credenciais não têm acesso ao painel administrativo.',
            ]);
        }

        if ($request->is('passkeys/login')) {
            $redirect = $adminLogin
                ? redirect()->route('admin.dashboard')
                : redirect()->intended(config('passkeys.redirect', '/'));

            return $request->wantsJson()
                ? response()->json(['redirect' => $redirect->getTargetUrl()])
                : $redirect;
        }

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return $adminLogin
            ? redirect()->route('admin.dashboard')
            : redirect()->intended(Fortify::redirects('login'));
    }

    private function logout(Request $request): void
    {
        Auth::guard((string) config('fortify.guard'))->logout();
        $request->session()->regenerate();
    }
}
