<?php

namespace App\Support;

use App\Models\User;
use App\Support\Integrations\PasswordIntegrationAccessRevoker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PasswordSessionRevoker
{
    public function __construct(private readonly PasswordIntegrationAccessRevoker $integrations) {}

    public function revoke(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->integrations->revokeFor($user);

            $user->setRememberToken(Str::random(60));
            $user->saveQuietly();

            if (config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $user->getAuthIdentifier())
                    ->delete();
            }
        });
    }
}
