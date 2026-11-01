<?php

namespace App\Support;

use App\Models\AgendaNotification;
use App\Models\Appointment;
use App\Models\Membership;
use App\Models\NotificationPreference;
use App\Models\Professional;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;

class AgendaNotifications
{
    public function __construct(private readonly AuthorizationService $authorization = new AuthorizationService, private readonly WebPushSender $sender = new WebPushSender) {}

    public function record(Appointment $appointment, string $category, ?string $actorId = null, ?string $previousProfessionalId = null): void
    {
        foreach (array_unique(array_filter([$appointment->professional_id, $previousProfessionalId])) as $professionalId) {
            $professional = Professional::query()->whereKey($professionalId)->where('tenant_id', $appointment->tenant_id)->where('unit_id', $appointment->unit_id)->where('status', 'active')->first();
            $membership = $professional?->notification_membership_id ? Membership::query()->whereKey($professional->notification_membership_id)->where('tenant_id', $appointment->tenant_id)->first() : null;
            if ($membership === null || $membership->user_id === $actorId || ! $this->authorized($membership->user_id, $appointment->tenant_id, $appointment->unit_id)) {
                continue;
            }
            $identity = $category === 'reminders' ? $appointment->starts_at->getTimestamp() : $appointment->lock_version;
            AgendaNotification::query()->firstOrCreate(['deduplication_key' => hash('sha256', implode(':', [$appointment->getKey(), $category, $identity, $professionalId, $membership->user_id]))], [
                'tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'user_id' => $membership->user_id,
                'professional_id' => $professionalId, 'appointment_id' => $appointment->getKey(), 'category' => $category,
                'title' => match ($category) {
                    'new_appointments' => 'Novo agendamento', 'cancellations' => 'Agendamento cancelado', 'reminders' => 'Seu atendimento está próximo', default => 'Agendamento alterado'
                },
                'body' => 'Confira a atualização na sua agenda.', 'appointment_starts_at' => $appointment->starts_at,
                'appointment_version' => $appointment->lock_version,
                'appointment_snapshot' => $this->appointmentSnapshot($appointment),
                'expires_at' => $category === 'reminders' ? $appointment->starts_at : now()->addDay(),
            ]);
        }
    }

    public function dispatchReminders(): void
    {
        Appointment::query()->whereIn('status', ['scheduled', 'confirmed'])->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHour())->orderBy('id')->chunkById(100, function ($appointments): void {
            foreach ($appointments as $appointment) {
                $professional = Professional::query()->find($appointment->professional_id);
                $membership = $professional?->notification_membership_id ? Membership::query()->find($professional->notification_membership_id) : null;
                if ($membership === null) {
                    continue;
                }
                $minutes = $this->preference($appointment->tenant_id, $membership->user_id)->reminder_minutes;
                $due = $appointment->starts_at->copy()->subMinutes($minutes);
                if ($due->lte(now()) && $due->gt(now()->subMinutes(2))) {
                    $this->record($appointment, 'reminders');
                }
            }
        });
    }

    public function dispatchPush(): void
    {
        if (! config('webpush.enabled')) {
            return;
        }
        AgendaNotification::query()->whereNull('push_processed_at')->where('expires_at', '>', now())->orderBy('id')->limit(200)->get()->each(function (AgendaNotification $notification): void {
            PushSubscription::query()->where('tenant_id', $notification->tenant_id)->where('user_id', $notification->user_id)->each(function (PushSubscription $subscription) use ($notification): void {
                PushDelivery::query()->firstOrCreate(['agenda_notification_id' => $notification->getKey(), 'push_subscription_id' => $subscription->getKey()], ['available_at' => now()]);
            });
            $notification->update(['push_processed_at' => now()]);
        });
        $deadline = microtime(true) + 40;
        PushDelivery::query()->where('status', 'pending')->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))->orderBy('id')->limit(200)->get()->each(function (PushDelivery $delivery) use ($deadline): bool {
            if (microtime(true) >= $deadline) {
                return false;
            }
            Cache::lock('agenda-push:'.$delivery->getKey(), 60)->get(function () use ($delivery): void {
                $delivery->refresh();
                if ($delivery->status !== 'pending') {
                    return;
                }
                $notification = AgendaNotification::query()->find($delivery->agenda_notification_id);
                $subscription = PushSubscription::query()->find($delivery->push_subscription_id);
                if ($notification === null || $subscription === null || ! $this->deliverable($notification, $subscription)) {
                    $delivery->update(['status' => 'skipped']);

                    return;
                }
                $preference = $this->preference($notification->tenant_id, $notification->user_id);
                if ($notification->category === 'reminders') {
                    $due = $notification->appointment_starts_at->copy()->subMinutes($preference->reminder_minutes);
                    if ($due->gt(now())) {
                        $delivery->update(['available_at' => $due]);

                        return;
                    }
                }
                if ($this->quiet($preference, $notification)) {
                    $delivery->update(['available_at' => now()->addMinute()]);

                    return;
                }
                $success = $this->sender->send($subscription, ['title' => $notification->title, 'body' => $notification->body, 'url' => route('notifications.open', $notification->getKey(), false), 'tag' => $notification->getKey()], $notification->expires_at);
                if (! $delivery->exists || ! PushDelivery::query()->whereKey($delivery->getKey())->exists()) {
                    return;
                }
                $attempts = $delivery->attempts + 1;
                $delivery->update(['attempts' => $attempts, 'status' => $success ? 'sent' : ($attempts >= 5 ? 'failed' : 'pending'), 'sent_at' => $success ? now() : null, 'available_at' => now()->addSeconds(min(900, 30 * (2 ** $attempts))), 'last_error' => $success ? null : 'push_delivery_failed']);
            });

            return true;
        });
    }

    private function deliverable(AgendaNotification $notification, PushSubscription $subscription): bool
    {
        if ($subscription->tenant_id !== $notification->tenant_id || $subscription->user_id !== $notification->user_id || $notification->expires_at->lte(now()) || ! $this->authorized($notification->user_id, $notification->tenant_id, $notification->unit_id)) {
            return false;
        }
        if ($notification->category === 'test') {
            return true;
        }
        if (! $this->preference($notification->tenant_id, $notification->user_id)->getAttribute($notification->category)) {
            return false;
        }
        $professional = Professional::query()->whereKey($notification->professional_id)->where('tenant_id', $notification->tenant_id)->where('unit_id', $notification->unit_id)->where('status', 'active')->first();
        $membership = $professional?->notification_membership_id ? Membership::query()->find($professional->notification_membership_id) : null;
        $appointment = Appointment::query()->whereKey($notification->appointment_id)->where('tenant_id', $notification->tenant_id)->where('unit_id', $notification->unit_id)->first();
        if ($membership?->user_id !== $notification->user_id || $appointment === null) {
            return false;
        }
        if ($notification->category === 'reminders') {
            return in_array($appointment->status, ['scheduled', 'confirmed'], true) && $appointment->professional_id === $professional->getKey() && $appointment->starts_at->eq($notification->appointment_starts_at) && $appointment->starts_at->gt(now());
        }

        if ($notification->category === 'cancellations' ? $appointment->status !== 'cancelled' : ! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
            return false;
        }

        return $notification->appointment_snapshot === null
            ? $appointment->lock_version === $notification->appointment_version
            : $this->appointmentSnapshot($appointment) == $notification->appointment_snapshot;
    }

    /** @return array{professional_id: string, starts_at: int, ends_at: int, service_ids: list<string>} */
    private function appointmentSnapshot(Appointment $appointment): array
    {
        return [
            'professional_id' => (string) $appointment->professional_id,
            'starts_at' => $appointment->starts_at->getTimestamp(),
            'ends_at' => $appointment->ends_at->getTimestamp(),
            'service_ids' => array_values($appointment->items()->orderBy('position')->pluck('service_id')->map(fn (mixed $serviceId): string => (string) $serviceId)->all()),
        ];
    }

    private function authorized(string $userId, string $tenantId, string $unitId): bool
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return false;
        }
        try {
            $context = TenantContext::forUser($user, $tenantId, $unitId);

            return $this->authorization->can($user, $context, 'calendar.view', $context->unit);
        } catch (AuthorizationException) {
            return false;
        }
    }

    private function preference(string $tenantId, string $userId): NotificationPreference
    {
        return NotificationPreference::query()->firstOrNew(['tenant_id' => $tenantId, 'user_id' => $userId]);
    }

    private function quiet(NotificationPreference $preference, AgendaNotification $notification): bool
    {
        if (! $preference->quiet_start || ! $preference->quiet_end || $preference->quiet_start === $preference->quiet_end) {
            return false;
        }
        $timezone = Appointment::query()->whereKey($notification->appointment_id)->value('timezone') ?? config('app.timezone');
        $time = now()->setTimezone($timezone)->format('H:i');

        return $preference->quiet_start < $preference->quiet_end ? $time >= $preference->quiet_start && $time < $preference->quiet_end : $time >= $preference->quiet_start || $time < $preference->quiet_end;
    }
}
