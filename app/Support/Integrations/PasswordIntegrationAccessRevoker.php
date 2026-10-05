<?php

namespace App\Support\Integrations;

use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\OAuthGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

final class PasswordIntegrationAccessRevoker
{
    public function __construct(private readonly OAuthGrantWriter $grants) {}

    public function revokeFor(User $user): void
    {
        DB::transaction(function () use ($user): void {
            IntegrationCredential::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->with('passportToken')
                ->get()
                ->each(function (IntegrationCredential $credential): void {
                    $credential->passportToken?->revoke();
                    $credential->forceFill(['revoked_at' => now()])->save();
                });

            OAuthGrant::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->with(['passportToken', 'passportRefreshToken'])
                ->get()
                ->each(function (OAuthGrant $grant): void {
                    $this->grants->revoke($grant);
                });

            $authCodeIds = OAuthGrant::query()
                ->where('user_id', $user->getKey())
                ->whereNotNull('auth_code_id')
                ->pluck('auth_code_id');

            if ($authCodeIds->isNotEmpty()) {
                Passport::authCode()
                    ->newQuery()
                    ->whereIn('id', $authCodeIds->all())
                    ->where('revoked', false)
                    ->update(['revoked' => true]);
            }
        });
    }
}
