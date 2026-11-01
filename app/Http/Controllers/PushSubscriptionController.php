<?php

namespace App\Http\Controllers;

use App\Http\Requests\PushSubscriptionRequest;
use App\Models\AgendaNotification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Support\AuthorizationService;
use App\Support\TenantContext;
use App\Support\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PushSubscriptionController extends Controller
{
    public function store(PushSubscriptionRequest $request, TenantContext $context): JsonResponse
    {
        abort_unless(app(WebPushSender::class)->available(), 503, 'Notificações push ainda não estão configuradas.');
        $data = $request->validated();
        if (! $request->session()->has('push_session_key')) {
            $request->session()->put('push_session_key', Str::random(64));
        }
        $subscription = DB::transaction(function () use ($data, $request, $context): PushSubscription {
            $hash = hash('sha256', $data['endpoint']);
            PushSubscription::query()->where('endpoint_hash', $hash)->where('user_id', '!=', $context->user->id)->delete();

            return PushSubscription::query()->updateOrCreate(['tenant_id' => $context->tenant->id, 'endpoint_hash' => $hash], [
                'user_id' => $context->user->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'origin' => $request->getSchemeAndHttpHost(),
                'session_hash' => hash('sha256', $request->session()->get('push_session_key')),
                'device_name' => $data['device_name'],
            ]);
        });

        return response()->json(['id' => $subscription->id], 201);
    }

    public function destroy(PushSubscription $subscription, TenantContext $context): JsonResponse
    {
        $this->owned($subscription, $context);
        $subscription->delete();

        return response()->json(null, 204);
    }

    public function test(Request $request, TenantContext $context, AuthorizationService $authorization): JsonResponse
    {
        abort_unless(app(WebPushSender::class)->available(), 503, 'Notificações push ainda não estão configuradas.');
        abort_unless($context->unit !== null && $authorization->can($context->user, $context, 'calendar.view', $context->unit), 403);
        $subscription = PushSubscription::query()->whereKey($request->input('subscription_id'))->where('tenant_id', $context->tenant->id)->where('user_id', $context->user->id)->firstOrFail();
        $this->owned($subscription, $context);
        abort_unless($subscription->origin === $request->getSchemeAndHttpHost(), 403);
        DB::transaction(function () use ($context, $subscription): void {
            $notification = AgendaNotification::query()->create([
                'tenant_id' => $context->tenant->id, 'unit_id' => $context->unit->id, 'user_id' => $context->user->id,
                'category' => 'test', 'deduplication_key' => 'test:'.Str::uuid7(),
                'title' => 'Notificações do Caldas Gestão', 'body' => 'Este aparelho está pronto para receber avisos.', 'expires_at' => now()->addMinutes(5), 'push_processed_at' => now(),
            ]);
            PushDelivery::query()->create(['agenda_notification_id' => $notification->id, 'push_subscription_id' => $subscription->id, 'available_at' => now()]);
        });

        return response()->json(['message' => 'Teste agendado para envio.'], 202);
    }

    private function owned(PushSubscription $subscription, TenantContext $context): void
    {
        abort_unless($subscription->tenant_id === $context->tenant->id && $subscription->user_id === $context->user->id, 404);
    }
}
