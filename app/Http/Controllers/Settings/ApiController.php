<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Integrations\OAuthGrant;
use App\Support\Integrations\IntegrationCredentialCatalog;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\PasskeyUser;

class ApiController extends Controller
{
    /**
     * Show API credentials and authorized integration grants.
     */
    public function edit(Request $request, IntegrationCredentialCatalog $credentialCatalog): Response
    {
        $context = $request->attributes->get(TenantContext::class);
        abort_unless($context instanceof TenantContext, 403);

        return Inertia::render('settings/api', [
            'canManageIntegrations' => true,
            'hasIntegrationPasskey' => $request->user() instanceof PasskeyUser
                && $request->user()->hasPasskeysEnabled(),
            'integrationCredentials' => $credentialCatalog->forContext($context),
            'integrationOAuthGrants' => OAuthGrant::query()
                ->with(['client', 'passportToken', 'passportRefreshToken'])
                ->where('user_id', $context->user->getKey())
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->latest('created_at')
                ->get()
                ->map(fn (OAuthGrant $grant): array => [
                    'id' => (string) $grant->getKey(),
                    'client_name' => $grant->client->name,
                    'capabilities' => $grant->capabilities,
                    'created_at' => $grant->created_at?->toISOString(),
                    'expires_at' => $grant->expires_at->toISOString(),
                    'last_used_at' => $grant->last_used_at?->toISOString(),
                    'status' => $grant->revoked_at !== null
                        || $grant->passportToken?->revoked
                        || $grant->passportRefreshToken?->revoked
                            ? 'revoked'
                            : ($grant->expires_at->isPast() ? 'expired' : 'active'),
                ])
                ->all(),
        ]);
    }
}
