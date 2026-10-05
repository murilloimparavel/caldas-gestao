<?php

namespace App\Http\Controllers;

use App\Http\Requests\FirstLoginPasswordRequest;
use App\Support\PasswordSessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class FirstLoginPasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->must_change_password, 403);

        return Inertia::render('auth/first-login-password', ['passwordRules' => Password::defaults()->toPasswordRulesString()]);
    }

    public function update(FirstLoginPasswordRequest $request, PasswordSessionRevoker $sessions): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'first_login_at' => now(),
        ])->save();
        $sessions->revoke($request->user());

        return to_route('dashboard');
    }
}
