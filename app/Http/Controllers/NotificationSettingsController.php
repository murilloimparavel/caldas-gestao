<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationPreferenceRequest;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Support\TenantContext;
use App\Support\WebPushSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class NotificationSettingsController extends Controller
{
    public function dismissInvitation(TenantContext $context): Response
    {
        NotificationPreference::query()->updateOrCreate(
            ['tenant_id' => $context->tenant->id, 'user_id' => $context->user->id],
            ['push_invitation_dismissed_at' => now()],
        );

        return response()->noContent();
    }

    public function edit(TenantContext $context): InertiaResponse
    {
        $preferences = NotificationPreference::query()->firstOrNew(['tenant_id' => $context->tenant->id, 'user_id' => $context->user->id]);

        return Inertia::render('settings/notifications', [
            'preferences' => [...$preferences->only(['new_appointments', 'changes', 'cancellations', 'reminders', 'reminder_minutes']), 'quiet_start' => $preferences->quiet_start, 'quiet_end' => $preferences->quiet_end],
            'push' => [
                'available' => app(WebPushSender::class)->available(),
                'publicKey' => config('webpush.vapid.public_key'),
            ],
            'subscriptions' => PushSubscription::query()->where('tenant_id', $context->tenant->id)->where('user_id', $context->user->id)->latest()->get()->map(fn (PushSubscription $subscription): array => [
                'id' => $subscription->id, 'device_name' => $subscription->device_name, 'endpoint_hash' => $subscription->endpoint_hash, 'created_at' => $subscription->created_at->toISOString(),
            ]),
        ]);
    }

    public function update(NotificationPreferenceRequest $request, TenantContext $context): RedirectResponse
    {
        NotificationPreference::query()->updateOrCreate(['tenant_id' => $context->tenant->id, 'user_id' => $context->user->id], $request->validated());

        return back()->with('success', 'Preferências de notificações atualizadas.');
    }
}
