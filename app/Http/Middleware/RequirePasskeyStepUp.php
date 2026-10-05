<?php

namespace App\Http\Middleware;

use App\Models\Integrations\ProposedOperation;
use App\Support\Integrations\StepUpSession;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasskeyStepUp
{
    public function handle(Request $request, Closure $next, ?string $purpose = null): Response
    {
        $purpose ??= (string) $request->route('purpose');
        $proposal = $purpose === 'operations.confirm' ? $request->route('proposal') : null;
        if (! app(StepUpSession::class)->consumeProof(
            $request,
            $purpose,
            $proposal instanceof ProposedOperation ? $proposal : null,
        )) {
            throw new AuthorizationException('A recent passkey step-up is required.');
        }

        return $next($request);
    }
}
