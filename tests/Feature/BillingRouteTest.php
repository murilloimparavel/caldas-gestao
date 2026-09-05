<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\BillingWebhookEvent;
use App\Models\Membership;
use App\Models\TenantBillingAccount;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\SaaSBillingService;
use Illuminate\Support\Str;

it('exposes the configured Lastlink checkout on the billing route', function (): void {
    config()->set('services.lastlink.checkout_url', 'https://lastlink.test/checkout');
    $user = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($user, ['name' => 'Billing '.Str::random(6), 'slug' => 'billing-'.Str::lower(Str::random(6))]);
    Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->update(['status' => 'active', 'joined_at' => now()]);

    $this->actingAs($user)->get(route('billing.index'))->assertInertia(fn ($page) => $page
        ->component('billing/index')
        ->where('checkoutUrl', 'https://lastlink.test/checkout')
        ->where('subscription.status', 'trial')
        ->where('subscription.plan.name', 'Free'));
});

it('creates one trial and does not recreate it after expiry', function (): void {
    $user = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($user, ['name' => 'Trial '.Str::random(6), 'slug' => 'trial-'.Str::lower(Str::random(6))]);
    $service = app(SaaSBillingService::class);
    $first = $service->ensureFreeTier($tenant);
    $first->update(['status' => 'expired', 'ends_at' => now()->subMinute()]);

    expect($service->ensureFreeTier($tenant)->getKey())->toBe($first->getKey())
        ->and(TenantSubscription::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1);
});

it('processes a Lastlink webhook idempotently', function (): void {
    $user = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($user, ['name' => 'Hook '.Str::random(6), 'slug' => 'hook-'.Str::lower(Str::random(6))]);
    Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->update(['status' => 'active', 'joined_at' => now()]);
    $service = app(SaaSBillingService::class);
    $subscription = $service->ensureFreeTier($tenant);
    TenantBillingAccount::factory()->create(['tenant_id' => $tenant->getKey(), 'email' => $user->email]);
    $payload = ['Id' => 'evt-'.Str::random(10), 'Event' => 'Purchase_Order_Confirmed', 'Data' => ['Buyer' => ['Email' => $user->email], 'Subscriptions' => [['Id' => 'sub-'.Str::random(10)]]]];

    $service->processLastlink($payload);
    $service->processLastlink($payload);

    expect(BillingWebhookEvent::query()->where('provider_event_id', $payload['Id'])->count())->toBe(1)
        ->and($subscription->fresh()->status)->toBe('active');
});

it('rejects Lastlink webhook requests without the configured secret', function (): void {
    config()->set('services.lastlink.webhook_secret', 'secret');
    $this->postJson(route('webhooks.lastlink'), [])->assertUnauthorized();
});

it('does not grant access to suspended subscriptions and reconciles expired trials', function (): void {
    $user = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($user, ['name' => 'Blocked '.Str::random(6), 'slug' => 'blocked-'.Str::lower(Str::random(6))]);
    $service = app(SaaSBillingService::class);
    $subscription = $service->ensureFreeTier($tenant);
    $subscription->update(['status' => 'trial', 'ends_at' => now()->subMinute()]);

    expect($subscription->fresh()->grantsAccess())->toBeFalse()
        ->and($service->expireDue())->toBeGreaterThanOrEqual(1)
        ->and($subscription->fresh()->status)->toBe('expired');
});
