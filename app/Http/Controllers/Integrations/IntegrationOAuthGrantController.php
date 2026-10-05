<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Integrations\OAuthGrant;
use App\Support\Integrations\OAuthGrantWriter;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IntegrationOAuthGrantController extends Controller
{
    public function destroy(Request $request, string $purpose, string $grant, OAuthGrantWriter $writer): Response
    {
        abort_unless($purpose === 'oauth.revoke', 404);
        $context = $request->attributes->get(TenantContext::class);

        abort_unless($context instanceof TenantContext, 403);

        DB::transaction(function () use ($context, $grant, $writer): void {
            $record = OAuthGrant::query()
                ->whereKey($grant)
                ->where('user_id', $context->user->getKey())
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->firstOrFail();

            $record->load(['passportToken', 'passportRefreshToken']);
            $writer->revoke($record);
        });

        return response()->noContent();
    }
}
