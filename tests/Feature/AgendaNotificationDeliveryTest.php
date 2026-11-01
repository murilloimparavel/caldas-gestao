<?php

use App\Actions\Appointments\UpdateAppointment;
use App\Actions\Identity\OnboardTenant;
use App\Models\AgendaNotification;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\NotificationPreference;
use App\Models\Professional;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Service;
use App\Models\User;
use App\Support\AgendaNotifications;
use App\Support\AuthorizationService;
use App\Support\CalendarAvailability;
use App\Support\TenantContext;
use App\Support\WebPushSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function notificationAppointment(): Appointment
{
    $user = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($user, ['name' => 'Push '.Str::random(8)]);
    $unit = $tenant->units()->firstOrFail();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'notification_membership_id' => $tenant->memberships()->where('user_id', $user->id)->firstOrFail()->id]);

    return Appointment::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'professional_id' => $professional->id, 'starts_at' => now()->addMinutes(15)]);
}

it('deduplicates reminders by scheduled start and ignores unrelated versions', function () {
    $appointment = notificationAppointment();
    $service = app(AgendaNotifications::class);
    $service->dispatchReminders();
    $appointment->forceFill(['lock_version' => 1])->save();
    $service->dispatchReminders();
    expect(AgendaNotification::query()->count())->toBe(1);
});

it('suppresses self alerts and retains disabled categories in the inbox', function () {
    $appointment = notificationAppointment();
    $userId = Membership::query()->findOrFail($appointment->professional->notification_membership_id)->user_id;
    $service = app(AgendaNotifications::class);
    $service->record($appointment, 'new_appointments', $userId);
    NotificationPreference::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $userId, 'changes' => false]);
    $service->record($appointment, 'changes');
    expect(AgendaNotification::query()->count())->toBe(1)
        ->and(AgendaNotification::query()->firstOrFail()->category)->toBe('changes');
});

it('rechecks cancellations and membership revocation before push', function () {
    config(['webpush.enabled' => true]);
    $appointment = notificationAppointment();
    $service = app(AgendaNotifications::class);
    $service->record($appointment, 'reminders');
    $notification = AgendaNotification::query()->firstOrFail();
    PushSubscription::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $notification->user_id, 'endpoint_hash' => hash('sha256', 'endpoint'), 'endpoint' => 'https://fcm.googleapis.com/example', 'public_key' => 'key', 'auth_token' => 'token', 'origin' => 'https://example.test', 'session_hash' => hash('sha256', 'session'), 'device_name' => 'Phone']);
    $appointment->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldNotReceive('send');
    (new AgendaNotifications(app(AuthorizationService::class), $sender))->dispatchPush();
    expect(PushDelivery::query()->firstOrFail()->status)->toBe('skipped');
});

it('does not replay stale reminders after scheduler downtime', function () {
    $appointment = notificationAppointment();
    $appointment->forceFill(['starts_at' => now()->addMinutes(10)])->save();
    app(AgendaNotifications::class)->dispatchReminders();
    expect(AgendaNotification::query()->count())->toBe(0);
});

it('retries only failed devices and honors preference changes while queued', function () {
    config(['webpush.enabled' => true]);
    $appointment = notificationAppointment();
    app(AgendaNotifications::class)->record($appointment, 'new_appointments');
    $notification = AgendaNotification::query()->firstOrFail();
    foreach (['first', 'second'] as $device) {
        PushSubscription::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $notification->user_id, 'endpoint_hash' => hash('sha256', $device), 'endpoint' => 'https://fcm.googleapis.com/'.$device, 'public_key' => 'key', 'auth_token' => 'token', 'origin' => 'https://example.test', 'session_hash' => hash('sha256', 'session'), 'device_name' => $device]);
    }
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldReceive('send')->twice()->andReturn(true, false);
    $service = new AgendaNotifications(app(AuthorizationService::class), $sender);
    $service->dispatchPush();
    expect(PushDelivery::query()->where('status', 'sent')->count())->toBe(1)
        ->and(PushDelivery::query()->where('status', 'pending')->count())->toBe(1);
    NotificationPreference::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $notification->user_id, 'new_appointments' => false]);
    $this->travel(2)->minutes();
    $service->dispatchPush();
    expect(PushDelivery::query()->where('status', 'sent')->count())->toBe(1)
        ->and(PushDelivery::query()->where('status', 'skipped')->count())->toBe(1);
});

it('holds push during cross midnight quiet hours and never sends revoked memberships', function () {
    config(['webpush.enabled' => true]);
    $this->travelTo(now()->setTime(2, 0));
    $appointment = notificationAppointment();
    $appointment->forceFill(['timezone' => 'UTC'])->save();
    app(AgendaNotifications::class)->record($appointment, 'new_appointments');
    $notification = AgendaNotification::query()->firstOrFail();
    NotificationPreference::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $notification->user_id, 'quiet_start' => '22:00', 'quiet_end' => '07:00']);
    PushSubscription::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $notification->user_id, 'endpoint_hash' => hash('sha256', 'endpoint'), 'endpoint' => 'https://fcm.googleapis.com/example', 'public_key' => 'key', 'auth_token' => 'token', 'origin' => 'https://example.test', 'session_hash' => hash('sha256', 'session'), 'device_name' => 'Phone']);
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldNotReceive('send');
    $service = new AgendaNotifications(app(AuthorizationService::class), $sender);
    $service->dispatchPush();
    expect(PushDelivery::query()->firstOrFail()->status)->toBe('pending')
        ->and(PushDelivery::query()->firstOrFail()->attempts)->toBe(0);
    Membership::query()->where('user_id', $notification->user_id)->update(['status' => 'revoked', 'revoked_at' => now()]);
    $this->travel(2)->minutes();
    $service->dispatchPush();
    expect(PushDelivery::query()->firstOrFail()->status)->toBe('skipped');
});

it('keeps notification intents inside the appointment transaction', function () {
    $appointment = notificationAppointment();
    try {
        DB::transaction(function () use ($appointment): void {
            app(AgendaNotifications::class)->record($appointment, 'new_appointments');
            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }
    expect(AgendaNotification::query()->count())->toBe(0);
});

it('records a reassignment for the former and new professional', function () {
    $appointment = notificationAppointment();
    $oldProfessionalId = $appointment->professional_id;
    $oldNotification = app(AgendaNotifications::class);
    $otherUser = User::factory()->create();
    $tenant = $appointment->tenant;
    $membership = Membership::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $otherUser->id, 'status' => 'active']);
    MembershipUnit::query()->create(['tenant_id' => $tenant->id, 'membership_id' => $membership->id, 'unit_id' => $appointment->unit_id, 'is_primary' => true]);
    $ownerMembership = $tenant->memberships()->where('user_id', '!=', $otherUser->id)->firstOrFail();
    $ownerRole = $ownerMembership->membershipRoles()->firstOrFail();
    MembershipRole::query()->create(['tenant_id' => $tenant->id, 'membership_id' => $membership->id, 'role_id' => $ownerRole->role_id, 'scope_kind' => $ownerRole->scope_kind, 'assignment_scope' => $ownerRole->assignment_scope, 'unit_id' => $ownerRole->unit_id]);
    $newProfessional = Professional::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $appointment->unit_id, 'notification_membership_id' => $membership->id]);
    $appointment->forceFill(['professional_id' => $newProfessional->id, 'lock_version' => 1])->save();
    $oldNotification->record($appointment, 'changes', null, $oldProfessionalId);
    expect(AgendaNotification::query()->count())->toBe(2)
        ->and(AgendaNotification::query()->pluck('professional_id')->all())->toContain($oldProfessionalId, $newProfessional->id);
    config(['webpush.enabled' => true]);
    foreach (AgendaNotification::query()->get() as $notification) {
        queuedNotificationSubscription($notification);
    }
    $appointment->forceFill(['notes' => 'Internal note after reassignment', 'lock_version' => 2])->save();
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldReceive('send')->twice()->andReturnTrue();
    (new AgendaNotifications(app(AuthorizationService::class), $sender))->dispatchPush();
    expect(PushDelivery::query()->where('status', 'sent')->count())->toBe(2);
});

it('calls the update hook only for meaningful appointment edits', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
    $appointment = notificationAppointment();
    $appointment->unit->update(['timezone' => 'UTC']);
    $appointment->forceFill(['timezone' => 'UTC'])->save();
    $professional = $appointment->professional;
    $owner = Membership::query()->findOrFail($professional->notification_membership_id)->user;
    $service = Service::factory()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'duration_minutes' => 60]);
    $professional->services()->attach($service, ['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id]);
    $appointment->items()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'service_id' => $service->id, 'professional_id' => $professional->id, 'service_name_snapshot' => $service->name, 'duration_minutes' => 60, 'price_cents' => 100, 'currency' => 'BRL', 'position' => 1]);
    $appointment->forceFill(['ends_at' => $appointment->starts_at->copy()->addHour()])->save();
    AvailabilityRule::factory()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'professional_id' => $professional->id, 'weekday' => $appointment->starts_at->dayOfWeek, 'starts_at' => '00:00', 'ends_at' => '23:59', 'timezone' => 'UTC']);
    $secondService = Service::factory()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'duration_minutes' => 30]);
    $professional->services()->attach($secondService, ['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id]);
    $notifications = Mockery::mock(AgendaNotifications::class);
    $notifications->shouldReceive('record')->twice()->withArgs(fn (Appointment $changed, string $category, string $actor, string $previous): bool => $category === 'changes' && $previous === $professional->id);
    $action = new UpdateAppointment(app(CalendarAvailability::class), notifications: $notifications);
    $context = TenantContext::forUser($owner, $appointment->tenant_id, $appointment->unit_id);
    $data = ['lock_version' => 0, 'customer_id' => $appointment->customer_id, 'professional_id' => $professional->id, 'service_id' => $service->id, 'starts_at' => $appointment->starts_at->format('Y-m-d H:i:s'), 'notes' => 'Internal note'];
    $updated = $action->handle($owner, $context, $appointment, $data);
    $updated = $action->handle($owner, $context, $updated, [...$data, 'lock_version' => 1, 'starts_at' => $appointment->starts_at->copy()->addMinutes(10)->format('Y-m-d H:i:s')]);
    $action->handle($owner, $context, $updated, [...$data, 'lock_version' => 2, 'service_ids' => [$service->id, $secondService->id]]);
});

it('retries a discovered reminder beyond its discovery window until delivery', function () {
    config(['webpush.enabled' => true]);
    $appointment = notificationAppointment();
    app(AgendaNotifications::class)->dispatchReminders();
    $notification = AgendaNotification::query()->firstOrFail();
    PushSubscription::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $notification->user_id, 'endpoint_hash' => hash('sha256', 'retry-reminder'), 'endpoint' => 'https://fcm.googleapis.com/retry', 'public_key' => 'key', 'auth_token' => 'token', 'origin' => 'https://example.test', 'session_hash' => hash('sha256', 'session'), 'device_name' => 'Phone']);
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldReceive('send')->times(3)->andReturn(false, false, true);
    $service = new AgendaNotifications(app(AuthorizationService::class), $sender);
    $service->dispatchPush();
    $this->travel(1)->minutes();
    $service->dispatchPush();
    $this->travel(2)->minutes();
    $service->dispatchPush();
    expect(PushDelivery::query()->firstOrFail()->status)->toBe('sent')
        ->and(PushDelivery::query()->firstOrFail()->attempts)->toBe(3);
});

it('skips an expired reminder that is waiting for a retry', function () {
    config(['webpush.enabled' => true]);
    $appointment = notificationAppointment();
    app(AgendaNotifications::class)->dispatchReminders();
    $notification = AgendaNotification::query()->firstOrFail();
    PushSubscription::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $notification->user_id, 'endpoint_hash' => hash('sha256', 'expired-reminder'), 'endpoint' => 'https://fcm.googleapis.com/expired', 'public_key' => 'key', 'auth_token' => 'token', 'origin' => 'https://example.test', 'session_hash' => hash('sha256', 'session'), 'device_name' => 'Phone']);
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldReceive('send')->once()->andReturnFalse();
    $service = new AgendaNotifications(app(AuthorizationService::class), $sender);
    $service->dispatchPush();
    $this->travelTo($notification->expires_at);
    $service->dispatchPush();
    expect(PushDelivery::query()->firstOrFail()->status)->toBe('skipped')
        ->and(PushDelivery::query()->firstOrFail()->attempts)->toBe(1);
});

function queuedNotificationSubscription(AgendaNotification $notification): PushSubscription
{
    return PushSubscription::query()->create(['tenant_id' => $notification->tenant_id, 'user_id' => $notification->user_id, 'endpoint_hash' => hash('sha256', Str::random()), 'endpoint' => 'https://fcm.googleapis.com/queued', 'public_key' => 'key', 'auth_token' => 'token', 'origin' => 'https://example.test', 'session_hash' => hash('sha256', 'session'), 'device_name' => 'Phone']);
}

it('keeps queued appointment alerts after a notes edit and confirmation', function (string $category) {
    config(['webpush.enabled' => true]);
    $appointment = notificationAppointment();
    $appointment->unit->update(['timezone' => 'UTC']);
    $appointment->forceFill(['timezone' => 'UTC', 'status' => 'scheduled', 'ends_at' => $appointment->starts_at->copy()->addHour()])->save();
    $professional = $appointment->professional;
    $owner = Membership::query()->findOrFail($professional->notification_membership_id)->user;
    $service = Service::factory()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'duration_minutes' => 60]);
    $professional->services()->attach($service, ['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id]);
    $appointment->items()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'service_id' => $service->id, 'professional_id' => $professional->id, 'service_name_snapshot' => $service->name, 'duration_minutes' => 60, 'price_cents' => 100, 'currency' => 'BRL', 'position' => 1]);
    AvailabilityRule::factory()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'professional_id' => $professional->id, 'weekday' => $appointment->starts_at->dayOfWeek, 'starts_at' => '00:00', 'ends_at' => '23:59', 'timezone' => 'UTC']);
    app(AgendaNotifications::class)->record($appointment, $category);
    queuedNotificationSubscription(AgendaNotification::query()->firstOrFail());
    app(UpdateAppointment::class)->handle($owner, TenantContext::forUser($owner, $appointment->tenant_id, $appointment->unit_id), $appointment, ['lock_version' => 0, 'customer_id' => $appointment->customer_id, 'professional_id' => $professional->id, 'service_id' => $service->id, 'starts_at' => $appointment->starts_at->format('Y-m-d H:i:s'), 'notes' => 'New private note', 'status' => 'confirmed']);
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldReceive('send')->once()->andReturnTrue();
    (new AgendaNotifications(app(AuthorizationService::class), $sender))->dispatchPush();
    expect(PushDelivery::query()->firstOrFail()->status)->toBe('sent')
        ->and(AgendaNotification::query()->count())->toBe(1);
})->with(['new_appointments', 'changes']);

it('suppresses obsolete queued appointment alerts', function (string $change) {
    config(['webpush.enabled' => true]);
    $appointment = notificationAppointment();
    app(AgendaNotifications::class)->record($appointment, 'new_appointments');
    queuedNotificationSubscription(AgendaNotification::query()->firstOrFail());
    $attributes = match ($change) {
        'cancelled' => ['status' => 'cancelled', 'cancelled_at' => now()],
        'completed' => ['status' => 'completed'],
        'starts_at' => ['starts_at' => $appointment->starts_at->copy()->addHour(), 'ends_at' => $appointment->ends_at->copy()->addHour()],
        'ends_at' => ['ends_at' => $appointment->ends_at->copy()->addMinutes(10)],
        'professional' => ['professional_id' => Professional::factory()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id])->id],
    };
    $appointment->forceFill([...$attributes, 'lock_version' => 1])->save();
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldNotReceive('send');
    (new AgendaNotifications(app(AuthorizationService::class), $sender))->dispatchPush();
    expect(PushDelivery::query()->firstOrFail()->status)->toBe('skipped');
})->with(['cancelled', 'completed', 'starts_at', 'ends_at', 'professional']);

it('defers a queued reminder until its newly selected lead time', function () {
    config(['webpush.enabled' => true]);
    $appointment = notificationAppointment();
    app(AgendaNotifications::class)->dispatchReminders();
    $notification = AgendaNotification::query()->firstOrFail();
    queuedNotificationSubscription($notification);
    NotificationPreference::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $notification->user_id, 'reminder_minutes' => 5]);
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldReceive('send')->once()->andReturnTrue();
    $service = new AgendaNotifications(app(AuthorizationService::class), $sender);
    $service->dispatchPush();
    $delivery = PushDelivery::query()->firstOrFail();
    expect($delivery->status)->toBe('pending')->and($delivery->attempts)->toBe(0)
        ->and($delivery->available_at->eq($appointment->starts_at->copy()->subMinutes(5)))->toBeTrue();
    $this->travelTo($delivery->available_at->copy()->subSecond());
    $service->dispatchPush();
    expect($delivery->fresh()->status)->toBe('pending');
    $this->travel(1)->seconds();
    $service->dispatchReminders();
    $service->dispatchPush();
    expect($delivery->fresh()->status)->toBe('sent')->and(AgendaNotification::query()->count())->toBe(1);
});

it('keeps disabled categories in the inbox without sending push', function (string $category) {
    config(['webpush.enabled' => true]);
    $appointment = notificationAppointment();
    $userId = Membership::query()->findOrFail($appointment->professional->notification_membership_id)->user_id;
    NotificationPreference::query()->create(['tenant_id' => $appointment->tenant_id, 'user_id' => $userId, $category => false]);
    app(AgendaNotifications::class)->record($appointment, $category);
    $notification = AgendaNotification::query()->firstOrFail();
    queuedNotificationSubscription($notification);
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldNotReceive('send');
    (new AgendaNotifications(app(AuthorizationService::class), $sender))->dispatchPush();
    expect(AgendaNotification::query()->count())->toBe(1)->and(PushDelivery::query()->firstOrFail()->status)->toBe('skipped');
})->with(['new_appointments', 'changes', 'cancellations', 'reminders']);

it('suppresses a queued alert when its service is replaced', function () {
    config(['webpush.enabled' => true]);
    $appointment = notificationAppointment();
    $service = Service::factory()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id]);
    $item = $appointment->items()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id, 'service_id' => $service->id, 'professional_id' => $appointment->professional_id, 'service_name_snapshot' => $service->name, 'duration_minutes' => 60, 'price_cents' => 100, 'currency' => 'BRL', 'position' => 1]);
    app(AgendaNotifications::class)->record($appointment, 'changes');
    queuedNotificationSubscription(AgendaNotification::query()->firstOrFail());
    $replacement = Service::factory()->create(['tenant_id' => $appointment->tenant_id, 'unit_id' => $appointment->unit_id]);
    $item->update(['service_id' => $replacement->id]);
    $sender = Mockery::mock(WebPushSender::class);
    $sender->shouldNotReceive('send');
    (new AgendaNotifications(app(AuthorizationService::class), $sender))->dispatchPush();
    expect(PushDelivery::query()->firstOrFail()->status)->toBe('skipped');
});
