<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\AuthorizationService;
use App\Support\GoogleCalendarNotConfigured;
use App\Support\GoogleCalendarOAuth;
use App\Support\GoogleCalendarOAuthException;
use App\Support\GoogleCalendarOAuthReturnUrl;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class GoogleCalendarController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarOAuth $oauth,
        private readonly AuthorizationService $authorization,
        private readonly GoogleCalendarOAuthReturnUrl $returnUrl,
    ) {}

    public function status(TenantContext $context): JsonResponse
    {
        $this->authorizeView($context);

        return response()->json($this->oauth->status($context));
    }

    public function connect(Request $request, TenantContext $context): RedirectResponse|JsonResponse
    {
        $this->authorizeConfiguration($context);

        try {
            return redirect()->away($this->oauth->authorizationUrl($context, $request));
        } catch (GoogleCalendarNotConfigured $exception) {
            return response()->json(['status' => 'not_configured', 'message' => $exception->getMessage()], 503);
        }
    }

    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->returnUrl->isOfficialHost($request->getHost()), 404, 'O retorno do Google Agenda deve usar o domínio oficial.');

        $stateValue = (string) $request->query('state');
        $returnUrl = $this->oauth->returnUrl($stateValue);

        try {
            $this->oauth->complete(
                $stateValue,
                $request->query('code'),
                $request->query('error'),
            );
        } catch (AuthorizationException $exception) {
            return $this->callbackError($request, $exception->getMessage(), 403, $returnUrl);
        } catch (GoogleCalendarOAuthException $exception) {
            return $this->callbackError($request, $exception->getMessage(), 422, $returnUrl);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'connected']);
        }

        return $returnUrl === null
            ? to_route('calendar.index')->with('success', 'Google Agenda conectado.')
            : redirect()->away($this->withResult($returnUrl, 'connected'));
    }

    public function disconnect(TenantContext $context): RedirectResponse|JsonResponse
    {
        $this->authorizeConfiguration($context);
        $this->oauth->disconnect($context);

        if (request()->expectsJson()) {
            return response()->json(['status' => 'disconnected']);
        }

        return to_route('calendar.index')->with('success', 'Google Agenda desconectado.');
    }

    private function authorizeView(TenantContext $context): void
    {
        Gate::authorize('viewAny', Appointment::class);
        abort_unless($context->unit !== null, 403, 'É necessário selecionar uma unidade ativa para usar o Google Agenda.');
    }

    private function authorizeConfiguration(TenantContext $context): void
    {
        abort_unless($context->unit !== null, 403, 'É necessário selecionar uma unidade ativa para usar o Google Agenda.');
        abort_unless($this->authorization->can($context->user, $context, 'calendar.configure', $context->unit), 403, 'Você não tem permissão para configurar o Google Agenda.');
    }

    private function callbackError(Request $request, string $message, int $status, ?string $returnUrl = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return $returnUrl === null
            ? to_route('calendar.index')->with('error', $message)
            : redirect()->away($this->withResult($returnUrl, 'error'))->with('error', $message);
    }

    private function withResult(string $url, string $result): string
    {
        return $url.'?'.http_build_query(['google' => $result], '', '&', PHP_QUERY_RFC3986);
    }
}
