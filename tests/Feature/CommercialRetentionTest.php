<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\CustomerCommunicationPreference;
use App\Models\CustomerRetentionEvent;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit} */
function retentionWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Retention '.Str::random(8), 'slug' => 'retention-'.Str::lower(Str::random(8))]);

    return [$owner, $tenant, $tenant->units()->firstOrFail()];
}

it('stores channel consent and revocation inside the customer workspace', function () {
    [$owner, $tenant, $unit] = retentionWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $this->actingAs($owner)->patchJson(route('customers.communication_preferences.update', $customer), ['channel' => 'whatsapp', 'opted_in' => true])->assertSuccessful();
    $this->actingAs($owner)->patchJson(route('customers.communication_preferences.update', $customer), ['channel' => 'whatsapp', 'opted_in' => false])->assertSuccessful();

    $preference = CustomerCommunicationPreference::query()->where('customer_id', $customer->getKey())->firstOrFail();
    expect($preference->opted_in)->toBeFalse()->and($preference->revoked_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_events', ['tenant_id' => $tenant->getKey(), 'action' => 'customer.communication_preference.updated']);
});

it('does not allow a tenant to update another tenant customer preference', function () {
    [$owner, $tenant, $unit] = retentionWorkspace();
    $otherOwner = User::factory()->create();
    $otherTenant = (new OnboardTenant)->handle($otherOwner, ['name' => 'Other '.Str::random(8), 'slug' => 'other-'.Str::lower(Str::random(8))]);
    $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->getKey(), 'unit_id' => $otherTenant->units()->firstOrFail()->getKey()]);

    $this->actingAs($owner)->patchJson(route('customers.communication_preferences.update', $otherCustomer), ['channel' => 'email', 'opted_in' => true])->assertForbidden();
});

it('segments inactive customers by the configured activity window and tenant unit', function () {
    [$owner, $tenant, $unit] = retentionWorkspace();
    $old = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'last_activity_at' => now()->subDays(91)]);
    $recent = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'last_activity_at' => now()->subDays(10)]);
    $otherOwner = User::factory()->create();
    $otherTenant = (new OnboardTenant)->handle($otherOwner, ['name' => 'Other segment '.Str::random(8), 'slug' => 'other-segment-'.Str::lower(Str::random(8))]);
    $otherCustomer = Customer::factory()->create([
        'tenant_id' => $otherTenant->getKey(),
        'unit_id' => $otherTenant->units()->firstOrFail()->getKey(),
        'last_activity_at' => now()->subDays(91),
    ]);

    $response = $this->actingAs($owner)->getJson(route('retention.customers.inactive', ['days' => 90]));
    $response
        ->assertSuccessful()
        ->assertJsonPath('days', 90)
        ->assertJsonFragment(['id' => $old->getKey()])
        ->assertJsonMissing(['id' => $recent->getKey()])
        ->assertJsonMissing(['id' => $otherCustomer->getKey()]);
});

it('renders inactive customers with their communication preferences for retention work', function () {
    [$owner, $tenant, $unit] = retentionWorkspace();
    $inactiveCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'last_activity_at' => now()->subDays(91),
    ]);
    CustomerCommunicationPreference::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $inactiveCustomer->getKey(),
        'channel' => 'whatsapp',
        'opted_in' => false,
    ]);

    $this->actingAs($owner)
        ->get(route('retention.inactive', ['days' => 90]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('retention/inactive')
            ->where('days', 90)
            ->has('customers', 1)
            ->where('customers.0.id', $inactiveCustomer->getKey())
            ->where('customers.0.communication_preferences.0.channel', 'whatsapp')
            ->where('customers.0.communication_preferences.0.opted_in', false));
});

it('marks and reactivates a customer with an auditable idempotent retention event', function () {
    [$owner, $tenant, $unit] = retentionWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'last_activity_at' => now()->subDays(120)]);
    $headers = ['X-Idempotency-Key' => 'retention-mark-'.Str::uuid()];

    $first = $this->actingAs($owner)->withHeaders($headers)->postJson(route('customers.retention.mark', $customer), ['days' => 90]);
    $first->assertSuccessful();
    $this->actingAs($owner)->withHeaders($headers)->postJson(route('customers.retention.mark', $customer), ['days' => 90])->assertSuccessful();
    expect(CustomerRetentionEvent::query()->where('customer_id', $customer->getKey())->where('event_type', 'marked_at_risk')->count())->toBe(1);

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'retention-reactivate-'.Str::uuid())
        ->postJson(route('customers.retention.reactivate', $customer))
        ->assertSuccessful();
    $customer->refresh();
    expect($customer->retention_status)->toBe('reactivated');
    expect(CustomerRetentionEvent::query()->where('customer_id', $customer->getKey())->where('event_type', 'reactivated')->exists())->toBeTrue();
    expect(AuditEvent::query()->where('tenant_id', $tenant->getKey())->where('action', 'customer.retention.reactivated')->exists())->toBeTrue();
});
