<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\AuthorizationService;
use App\Support\GoogleCalendarNotConfigured;
use App\Support\GoogleCalendarOAuth;
use App\Support\GoogleCalendarOAuthException;
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
    ) {}

    public function status(TenantContext $context): JsonResponse
    {
        $this->authorizeView($context);

        return response()->json($this->oauth->status($context));
    }

    public function connect(TenantContext $context): RedirectResponse|JsonResponse
    {
        $this->authorizeConfiguration($context);

        try {
            return redirect()->away($this->oauth->authorizationUrl($context));
        } catch (GoogleCalendarNotConfigured $exception) {
            return response()->json(['status' => 'not_configured', 'message' => $exception->getMessage()], 503);
        }
    }

    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 401, 'Authentication is required to finish Google Calendar authorization.');

        try {
            $this->oauth->complete(
                $user,
                (string) $request->query('state'),
                $request->query('code'),
                $request->query('error'),
            );
        } catch (AuthorizationException $exception) {
            return $this->callbackError($request, $exception->getMessage(), 403);
        } catch (GoogleCalendarOAuthException $exception) {
            return $this->callbackError($request, $exception->getMessage(), 422);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'connected']);
        }

        return to_route('calendar.index')->with('success', 'Google Calendar conectado.');
    }

    public function disconnect(TenantContext $context): RedirectResponse|JsonResponse
    {
        $this->authorizeConfiguration($context);
        $this->oauth->disconnect($context);

        if (request()->expectsJson()) {
            return response()->json(['status' => 'disconnected']);
        }

        return to_route('calendar.index')->with('success', 'Google Calendar desconectado.');
    }

    private function authorizeView(TenantContext $context): void
    {
        Gate::authorize('viewAny', Appointment::class);
        abort_unless($context->unit !== null, 403, 'An active unit is required for Google Calendar.');
    }

    private function authorizeConfiguration(TenantContext $context): void
    {
        abort_unless($context->unit !== null, 403, 'An active unit is required for Google Calendar.');
        abort_unless($this->authorization->can($context->user, $context, 'calendar.configure', $context->unit), 403, 'You are not allowed to configure Google Calendar.');
    }

    private function callbackError(Request $request, string $message, int $status): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return to_route('calendar.index')->with('error', $message);
    }
}
