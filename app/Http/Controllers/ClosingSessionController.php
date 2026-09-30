<?php

namespace App\Http\Controllers;

use App\Actions\Closing\FinalizeClosingSession;
use App\Actions\Closing\ReverseClosingSessionPayment;
use App\Http\Requests\ClosingSessionRequest;
use App\Http\Requests\ReverseClosingSessionPaymentRequest;
use App\Models\ClosingSession;
use App\Models\ClosingSessionPayment;
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
            'payments.recordedBy',
            'payments.reversalOf',
            'payments.reversal',
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

    public function reversePayment(ReverseClosingSessionPaymentRequest $request, TenantContext $context, ClosingSession $closingSession, ClosingSessionPayment $payment, ReverseClosingSessionPayment $reversePayment): RedirectResponse
    {
        abort_unless($payment->closing_session_id === $closingSession->getKey(), 404);
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reversePayment, $request, $context, $payment, $data): array {
            $reversal = $reversePayment->handle($request->user(), $context, $payment, $data);

            return ['resource_id' => $reversal->getKey(), 'resource_type' => 'closing-session-payment-reversal'];
        });

        return to_route('closing-sessions.show', $closingSession)->with('success', 'Estorno registrado com sucesso.');
    }
}
