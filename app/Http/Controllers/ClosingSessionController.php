<?php

namespace App\Http\Controllers;

use App\Actions\Closing\FinalizeClosingSession;
use App\Http\Requests\ClosingSessionRequest;
use App\Models\ClosingSession;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ClosingSessionController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function show(ClosingSession $closingSession, TenantContext $context): Response
    {
        Gate::authorize('view', $closingSession);

        $closingSession->load([
            'sales.items.service',
            'sales.items.product',
            'sales.items.professional',
            'sales.customer',
            'sales.category',
            'closedBy',
            'unit',
            'tenant',
        ]);

        return Inertia::render('closing-sessions/show', [
            'session' => $closingSession,
        ]);
    }

    public function store(ClosingSessionRequest $request, TenantContext $context, FinalizeClosingSession $finalizeClosingSession): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($finalizeClosingSession, $request, $context, $data): array {
            $session = $finalizeClosingSession->handle($request->user(), $context, $data);

            return ['resource_id' => $session->getKey(), 'resource_type' => 'closing-session'];
        });

        $session = ClosingSession::query()->findOrFail($reference['resource_id']);

        return to_route('closing-sessions.show', $session)->with('success', 'Fechamento consolidado realizado com sucesso.');
    }
}
