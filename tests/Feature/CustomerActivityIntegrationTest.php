<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Retention\RecordCustomerActivity;
use App\Models\Customer;
use App\Models\CustomerRetentionEvent;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit} */
function activityWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Activity '.Str::random(8), 'slug' => 'activity-'.Str::lower(Str::random(8))]);

    return [$owner, $tenant, $tenant->units()->firstOrFail()];
}

it('records the latest real activity and reactivates an at-risk customer', function () {
    [$owner, $tenant, $unit] = activityWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'last_activity_at' => CarbonImmutable::parse('2026-01-01 10:00:00'),
        'retention_status' => 'at_risk',
    ]);
    $occurredAt = CarbonImmutable::parse('2026-02-01 10:00:00');

    app(RecordCustomerActivity::class)->handle($owner, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()), $customer->getKey(), 'appointment.completed', $occurredAt);

    $customer->refresh();
    expect($customer->last_activity_at->equalTo($occurredAt))->toBeTrue()
        ->and($customer->retention_status)->toBe('reactivated')
        ->and(CustomerRetentionEvent::query()->where('customer_id', $customer->getKey())->where('event_type', 'reactivated')->value('metadata'))->toMatchArray(['source' => 'appointment.completed']);
});

it('does not move activity backwards, touch another tenant, or reactivate anonymized customers', function () {
    [$owner, $tenant, $unit] = activityWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'last_activity_at' => CarbonImmutable::parse('2026-03-01 10:00:00'),
        'retention_status' => 'at_risk',
    ]);
    $anonymized = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'last_activity_at' => CarbonImmutable::parse('2026-01-01 10:00:00'),
        'retention_status' => 'anonymized',
        'anonymized_at' => now(),
    ]);
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());

    app(RecordCustomerActivity::class)->handle($owner, $context, $customer->getKey(), 'sale.finalized', CarbonImmutable::parse('2026-02-01 10:00:00'));
    app(RecordCustomerActivity::class)->handle($owner, $context, $anonymized->getKey(), 'sale.finalized', CarbonImmutable::parse('2026-04-01 10:00:00'));

    expect($customer->refresh()->last_activity_at->toDateString())->toBe('2026-03-01')
        ->and($customer->retention_status)->toBe('reactivated')
        ->and($anonymized->refresh()->retention_status)->toBe('anonymized')
        ->and($anonymized->last_activity_at->toDateString())->toBe('2026-01-01');
});

it('ignores activities without a customer', function () {
    [$owner, $tenant, $unit] = activityWorkspace();

    expect(app(RecordCustomerActivity::class)->handle($owner, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()), null, 'sale.finalized'))->toBeNull();
});
