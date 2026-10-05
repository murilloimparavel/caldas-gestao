<?php

namespace App\Support\Integrations;

use App\Models\Integrations\OAuthGrant;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

final class OAuthGrantWriter
{
    /**
     * @param  array{user_id:string, client_id:string, tenant_id:string, unit_id:string|null, resource:string, capabilities:list<string>}  $context
     */
    public function createForAuthCode(string $authCodeId, array $context, string $userId, string $clientId): OAuthGrant
    {
        if ($context['user_id'] !== $userId || $context['client_id'] !== $clientId) {
            throw new \LogicException('The OAuth context does not match the authorization code.');
        }

        return OAuthGrant::query()->create([
            'id' => (string) Str::uuid(),
            'auth_code_id' => $authCodeId,
            'client_id' => $clientId,
            'user_id' => $userId,
            'tenant_id' => $context['tenant_id'],
            'unit_id' => $context['unit_id'],
            'resource' => $context['resource'],
            'capabilities' => $context['capabilities'],
            'expires_at' => now()->addMinutes((int) config('integration.oauth.access_token_ttl_minutes', 15)),
        ]);
    }

    public function attachAccessTokenToAuthCode(string $authCodeId, string $tokenId, string $userId, string $clientId): OAuthGrant
    {
        $grant = OAuthGrant::query()
            ->where('auth_code_id', $authCodeId)
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->whereNull('passport_token_id')
            ->firstOrFail();

        $this->attachToken($grant, $tokenId);

        return $grant->fresh();
    }

    public function attachRefreshTokenToAuthCode(string $authCodeId, string $refreshTokenId, CarbonInterface $expiresAt): void
    {
        OAuthGrant::query()
            ->where('auth_code_id', $authCodeId)
            ->whereNull('passport_refresh_token_id')
            ->update([
                'passport_refresh_token_id' => $refreshTokenId,
                'expires_at' => $expiresAt,
            ]);
    }

    public function rotateAccessTokenFromRefreshToken(string $refreshTokenId, string $tokenId): OAuthGrant
    {
        $grant = OAuthGrant::query()
            ->where('passport_refresh_token_id', $refreshTokenId)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $this->attachToken($grant, $tokenId);

        return $grant->fresh();
    }

    public function rotateRefreshToken(string $oldRefreshTokenId, string $newRefreshTokenId, CarbonInterface $expiresAt): void
    {
        OAuthGrant::query()
            ->where('passport_refresh_token_id', $oldRefreshTokenId)
            ->whereNull('revoked_at')
            ->update([
                'passport_refresh_token_id' => $newRefreshTokenId,
                'expires_at' => $expiresAt,
            ]);
    }

    public function revoke(OAuthGrant $grant): void
    {
        $grant->forceFill(['revoked_at' => now()])->save();

        if ($grant->auth_code_id !== null) {
            Passport::authCode()->newQuery()->whereKey($grant->auth_code_id)->update(['revoked' => true]);
        }

        if ($grant->passportToken !== null && ! $grant->passportToken->revoked) {
            $grant->passportToken->revoke();
        }

        if ($grant->passportRefreshToken !== null && ! $grant->passportRefreshToken->revoked) {
            $grant->passportRefreshToken->revoke();
        }
    }

    private function attachToken(OAuthGrant $grant, string $tokenId): void
    {
        $token = Token::query()->findOrFail($tokenId);

        $grant->forceFill([
            'passport_token_id' => $tokenId,
        ])->save();
    }
}
