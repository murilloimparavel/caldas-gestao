<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PasswordSessionRevoker
{
    public function revoke(User $user): void
    {
        $user->setRememberToken(Str::random(60));
        $user->saveQuietly();

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
    }
}
