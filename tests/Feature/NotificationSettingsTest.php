<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AgendaNotification;
use App\Models\Appointment;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\NotificationPreference;
use App\Models\Professional;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\WebPushSender;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

/** @return array{0: User, 1: Tenant, 2: Unit} */
function notificationWorkspace(): array
{
    $user = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($user, ['name' => 'Notificações '.Str::random(6)]);
    $unit = $tenant->units()->firstOrFail();

    return [$user, $tenant, $unit];
}

function notificationSubscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/test'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => rtrim(strtr(base64_encode(chr(4).str_repeat('a', 64)), '+/', '-_'), '='), 'auth' => rtrim(strtr(base64_encode(str_repeat('b', 16)), '+/', '-_'), '=')], 'device_name' => 'Meu celular'];
}

it('offers safe defaults and persists tenant specific notification preferences', function () {
    [$user, $tenant, $unit] = notificationWorkspace();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $this->get(route('notification-settings.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('settings/notifications')->where('preferences.reminder_minutes', 15)->where('preferences.cancellations', true)->where('push.available', false)->where('notificationInvitation.eligible', false));
    $data = ['new_appointments' => false, 'changes' => true, 'cancellations' => false, 'reminders' => true, 'reminder_minutes' => 30, 'quiet_start' => '22:00', 'quiet_end' => '07:00'];
    $this->patch(route('notification-settings.update'), $data)->assertRedirect();
    expect(NotificationPreference::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->firstOrFail()->new_appointments)->toBeFalse();
    $this->patch(route('notification-settings.update'), [...$data, 'quiet_end' => null])->assertSessionHasErrors('quiet_end');
    $this->patch(route('notification-settings.update'), [...$data, 'reminder_minutes' => 1000])->assertSessionHasErrors('reminder_minutes');
});

it('remembers dismissing the mobile push invitation for this user and tenant', function () {
    [$user, $tenant, $unit] = notificationWorkspace();
    $this->mock(WebPushSender::class)->shouldReceive('available')->andReturnTrue();
    config(['webpush.vapid.public_key' => 'public-key']);
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);

    $this->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('notificationInvitation.eligible', true)->where('notificationInvitation.dismissed', false));

    $this->post(route('notification-settings.invitation.dismiss'))->assertNoContent();

    expect(NotificationPreference::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->firstOrFail()->push_invitation_dismissed_at)->not->toBeNull();

    $this->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('notificationInvitation.eligible', false)->where('notificationInvitation.dismissed', true));

    $otherTenant = (new OnboardTenant)->handle($user, ['name' => 'Outra empresa '.Str::random(6)]);
    $otherUnit = $otherTenant->units()->firstOrFail();
    $this->withSession(['tenant_id' => $otherTenant->id, 'unit_id' => $otherUnit->id])
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('notificationInvitation.eligible', true)->where('notificationInvitation.dismissed', false));
});

it('does not invite a user who already has a push subscription in the active tenant', function () {
    [$user, $tenant, $unit] = notificationWorkspace();
    $this->mock(WebPushSender::class)->shouldReceive('available')->andReturnTrue();
    config(['webpush.vapid.public_key' => 'public-key']);
    PushSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/fcm/send/test'),
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
        'public_key' => 'encrypted-public-key',
        'auth_token' => 'encrypted-auth-token',
        'origin' => 'https://caldas.example.test',
        'session_hash' => hash('sha256', 'session'),
        'device_name' => 'Celular',
    ]);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id])
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('notificationInvitation.eligible', false)->where('notificationInvitation.dismissed', false)->where('notificationInvitation.available', true)->where('notificationInvitation.registeredEndpointHashes', [hash('sha256', 'https://fcm.googleapis.com/fcm/send/test')]));
});

it('isolates inbox and read actions by tenant user and accessible unit', function () {
    [$user, $tenant, $unit] = notificationWorkspace();
    [$otherUser, $otherTenant, $otherUnit] = notificationWorkspace();
    $attributes = ['category' => 'test', 'title' => 'Aviso', 'body' => 'Teste', 'expires_at' => now()->addHour()];
    $mine = AgendaNotification::query()->create([...$attributes, 'tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'user_id' => $user->id, 'deduplication_key' => 'mine']);
    $other = AgendaNotification::query()->create([...$attributes, 'tenant_id' => $otherTenant->id, 'unit_id' => $otherUnit->id, 'user_id' => $otherUser->id, 'deduplication_key' => 'other']);
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $this->get(route('notifications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('notifications/index')->has('notifications.data', 1)->where('unreadCount', 1));
    $this->post(route('notifications.read', $other))->assertNotFound();
    $this->get(route('notifications.open', $other))->assertNotFound();
    $this->post(route('notifications.read-all'))->assertRedirect();
    expect($mine->fresh()->read_at)->not->toBeNull()->and($other->fresh()->read_at)->toBeNull();
});

it('registers encrypted subscriptions rejects arbitrary endpoints and removes only the logout session', function () {
    [$user, $tenant, $unit] = notificationWorkspace();
    $this->mock(WebPushSender::class)->shouldReceive('available')->andReturnTrue();
    config(['webpush.enabled' => true, 'webpush.vapid.public_key' => 'public', 'webpush.vapid.private_key' => 'private']);
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $this->postJson(route('push-subscriptions.store'), notificationSubscriptionPayload('https://127.0.0.1/private'))->assertUnprocessable();
    $this->postJson(route('push-subscriptions.store'), notificationSubscriptionPayload('https://fcm.googleapis.com.evil.test/a'))->assertUnprocessable();
    $response = $this->postJson(route('push-subscriptions.store'), notificationSubscriptionPayload())->assertCreated();
    $subscription = PushSubscription::query()->findOrFail($response->json('id'));
    expect($subscription->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/test')->and($subscription->getRawOriginal('endpoint'))->not->toBe($subscription->endpoint)->and($subscription->toArray())->not->toHaveKeys(['endpoint', 'auth_token', 'public_key', 'session_hash']);
    $this->postJson(route('push-subscriptions.store'), notificationSubscriptionPayload())->assertCreated();
    expect(PushSubscription::query()->count())->toBe(1);
    $other = $subscription->replicate()->fill(['endpoint_hash' => hash('sha256', 'another'), 'session_hash' => hash('sha256', 'another-session')]);
    $other->save();
    $this->post(route('logout'))->assertRedirect();
    expect(PushSubscription::query()->whereKey($subscription->id)->exists())->toBeFalse()->and($other->fresh())->not->toBeNull();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $this->get(route('notifications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('notificationInvitation.available', true)->where('notificationInvitation.registeredEndpointHashes', [$other->endpoint_hash]));
});

it('queues a test for only the owned subscription and protects deleting another users device', function () {
    [$user, $tenant, $unit] = notificationWorkspace();
    [$otherUser, $otherTenant] = notificationWorkspace();
    $this->mock(WebPushSender::class)->shouldReceive('available')->andReturnTrue();
    $subscription = PushSubscription::query()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'endpoint_hash' => hash('sha256', 'test'), 'endpoint' => 'https://fcm.googleapis.com/fcm/send/test', 'public_key' => 'key', 'auth_token' => 'auth', 'origin' => rtrim(config('app.url'), '/'), 'session_hash' => hash('sha256', 'session'), 'device_name' => 'Celular']);
    $this->actingAs($otherUser)->withSession(['tenant_id' => $otherTenant->id]);
    $this->deleteJson(route('push-subscriptions.destroy', $subscription))->assertNotFound();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $this->postJson(route('push-subscriptions.test'), ['subscription_id' => $subscription->id])->assertAccepted();
    expect(PushDelivery::query()->where('push_subscription_id', $subscription->id)->count())->toBe(1);
});

it('links professionals explicitly only to active users in the same tenant and unit', function () {
    [$user, $tenant, $unit] = notificationWorkspace();
    [$otherUser, $otherTenant] = notificationWorkspace();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $membership = Membership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->firstOrFail();
    $otherMembership = Membership::query()->where('tenant_id', $otherTenant->id)->where('user_id', $otherUser->id)->firstOrFail();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $this->patch(route('professionals.notification-membership', $professional), ['membership_id' => $otherMembership->id])->assertSessionHasErrors('membership_id');
    $this->patch(route('professionals.notification-membership', $professional), ['membership_id' => $membership->id])->assertRedirect();
    expect($professional->fresh()->notification_membership_id)->toBe($membership->id);
    $this->patch(route('professionals.notification-membership', $professional), ['membership_id' => null])->assertRedirect();
    expect($professional->fresh()->notification_membership_id)->toBeNull();
});

it('denies professional notification linking without tenant management and reports duplicate links as validation', function () {
    [$owner, $tenant, $unit] = notificationWorkspace();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $second = Professional::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $ownerMembership = Membership::query()->where('user_id', $owner->id)->where('tenant_id', $tenant->id)->firstOrFail();
    $staff = User::factory()->create();
    $staffMembership = Membership::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'status' => 'active']);
    MembershipUnit::query()->create(['tenant_id' => $tenant->id, 'membership_id' => $staffMembership->id, 'unit_id' => $unit->id, 'is_primary' => true]);
    $this->actingAs($staff)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $this->patch(route('professionals.notification-membership', $professional), ['membership_id' => $ownerMembership->id])->assertForbidden();
    $this->actingAs($owner);
    $this->patch(route('professionals.notification-membership', $professional), ['membership_id' => $ownerMembership->id])->assertRedirect();
    $this->patch(route('professionals.notification-membership', $second), ['membership_id' => $ownerMembership->id])->assertSessionHasErrors('membership_id');
});

it('hides historical notifications when calendar permission has been revoked', function () {
    [$user, $tenant, $unit] = notificationWorkspace();
    AgendaNotification::query()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'user_id' => $user->id, 'category' => 'test', 'deduplication_key' => 'permission-revoked', 'title' => 'Aviso', 'body' => 'Teste', 'expires_at' => now()->addHour()]);
    $membership = Membership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->firstOrFail();
    $membership->membershipRoles()->update(['revoked_at' => now()]);
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $this->get(route('notifications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('notifications.data', 0)->where('unreadCount', 0)->where('notificationUnreadCount', 0));
});

it('opens an owned notification in its authorized destination tenant', function () {
    [$user, $tenant, $unit] = notificationWorkspace();
    $otherTenant = (new OnboardTenant)->handle($user, ['name' => 'Outro '.Str::random(6)]);
    $otherUnit = $otherTenant->units()->firstOrFail();
    $appointment = Appointment::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $notification = AgendaNotification::query()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'user_id' => $user->id, 'appointment_id' => $appointment->id, 'category' => 'changes', 'deduplication_key' => 'cross-tenant-open', 'title' => 'Aviso', 'body' => 'Teste', 'expires_at' => now()->addHour()]);
    $this->actingAs($user)->withSession(['tenant_id' => $otherTenant->id, 'unit_id' => $otherUnit->id]);
    $this->get(route('notifications.open', $notification))->assertRedirect(route('calendar.index', ['date' => $appointment->starts_at->timezone($appointment->timezone)->toDateString(), 'view' => 'day']))->assertSessionHas('tenant_id', $tenant->id)->assertSessionHas('unit_id', $unit->id);
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('does not change context or mark read when a destination notification is unauthorized', function (string $denial) {
    [$user, $tenant, $unit] = notificationWorkspace();
    $otherTenant = (new OnboardTenant)->handle($user, ['name' => 'Outro '.Str::random(6)]);
    $otherUnit = $otherTenant->units()->firstOrFail();
    $notification = AgendaNotification::query()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'user_id' => $denial === 'foreign' ? User::factory()->create()->id : $user->id, 'category' => 'test', 'deduplication_key' => 'denied-open', 'title' => 'Aviso', 'body' => 'Teste', 'expires_at' => now()->addHour()]);
    $membership = Membership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->firstOrFail();
    if ($denial === 'membership') {
        $membership->update(['status' => 'revoked', 'revoked_at' => now()]);
    } elseif ($denial === 'permission') {
        $membership->membershipRoles()->update(['revoked_at' => now()]);
    } elseif ($denial === 'unit') {
        $membership->membershipUnits()->delete();
    }
    $this->actingAs($user)->withSession(['tenant_id' => $otherTenant->id, 'unit_id' => $otherUnit->id]);
    $response = $this->get(route('notifications.open', $notification));
    if (in_array($denial, ['membership', 'unit'], true)) {
        $response->assertForbidden();
    } else {
        $response->assertNotFound();
    }
    $response->assertSessionHas('tenant_id', $otherTenant->id)->assertSessionHas('unit_id', $otherUnit->id);
    expect($notification->fresh()->read_at)->toBeNull();
})->with(['foreign', 'membership', 'permission', 'unit']);
