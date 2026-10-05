<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Integrations\IntegrationCredential;
use App\Support\Integrations\IntegrationCredentialCatalog;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Passport\Token;
use Symfony\Component\HttpFoundation\Response;

class IntegrationCredentialController extends Controller
{
    public function index(Request $request, IntegrationCredentialCatalog $catalog): JsonResponse
    {
        $context = $this->context($request);

        return response()
            ->json(['data' => $catalog->forContext($context)])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function store(Request $request, string $purpose, IntegrationCredentialCatalog $catalog): JsonResponse
    {
        abort_unless($purpose === 'credentials.issue', 404);

        $validated = $request->validate([
            'label' => ['required', 'string', 'min:1', 'max:80'],
            'capability_set' => ['sometimes', 'string', Rule::in(array_keys($catalog->capabilitySets()))],
            'tenant_id' => ['prohibited'],
            'unit_id' => ['prohibited'],
        ]);
        $capabilities = $catalog->capabilitySets()[$validated['capability_set'] ?? 'context:read'];
        $context = $this->context($request);
        $issued = $context->user->createToken($validated['label'], $capabilities);
        $token = $issued->getToken();

        if (! $token instanceof Token || $issued->accessToken === '') {
            abort(500, 'Unable to issue integration credential.');
        }

        try {
            $credential = DB::transaction(fn (): IntegrationCredential => IntegrationCredential::query()->create([
                'id' => (string) Str::uuid(),
                'passport_token_id' => $token->getKey(),
                'user_id' => $context->user->getKey(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $context->unit?->getKey(),
                'label' => $validated['label'],
                'capabilities' => $capabilities,
                'expires_at' => $token->expires_at,
            ]));
        } catch (\Throwable $exception) {
            $token->revoke();
            throw $exception;
        }

        return response()
            ->json([
                'data' => $catalog->metadata($credential),
                'secret' => $issued->accessToken,
            ], 201)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    public function destroy(Request $request, string $purpose, string $credential): Response
    {
        abort_unless($purpose === 'credentials.revoke', 404);
        $context = $this->context($request);

        $record = IntegrationCredential::query()
            ->whereKey($credential)
            ->where('user_id', $context->user->getKey())
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->whereNull('revoked_at')
            ->firstOrFail();

        DB::transaction(function () use ($record): void {
            $record->passportToken()->first()?->revoke();
            $record->forceFill(['revoked_at' => now()])->save();
        });

        return response()->noContent();
    }

    private function context(Request $request): TenantContext
    {
        $context = $request->attributes->get(TenantContext::class);

        abort_unless($context instanceof TenantContext, 403);

        return $context;
    }
}
