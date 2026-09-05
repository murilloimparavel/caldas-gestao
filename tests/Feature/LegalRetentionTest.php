<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Privacy\AnonymizeExpiredCustomers;
use App\Actions\Privacy\CreateLegalHold;
use App\Actions\Privacy\ReleaseLegalHold;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\DataRetentionPolicy;
use App\Models\LegalHold;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit} */
function legalRetentionWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Legal retention '.Str::random(8),
        'slug' => 'legal-retention-'.Str::lower(Str::random(8)),
    ]);

    return [$owner, $tenant, $tenant->units()->firstOrFail()];
}

function legalRetentionPolicy(Tenant $tenant, ?Unit $unit = null, int $days = 30): DataRetentionPolicy
{
    return DataRetentionPolicy::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit?->getKey(),
        'data_class' => 'customer',
        'retention_days' => $days,
        'anchor' => 'last_activity_at',
        'enabled' => true,
    ]);
}

it('reports a dry run and does not mutate customer PII', function () {
    [$owner, $tenant, $unit] = legalRetentionWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'email' => 'customer@example.test',
        'phone' => '11999999999',
        'last_activity_at' => now()->subDays(31),
    ]);
    legalRetentionPolicy($tenant, $unit);

    $summary = app(AnonymizeExpiredCustomers::class)->handle(
        $owner,
        TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()),
        dryRun: true,
    );

    $customer->refresh();
    expect($summary)->toMatchArray(['eligible' => 1, 'anonymized' => 0, 'held' => 0, 'dry_run' => true])
        ->and($customer->email)->toBe('customer@example.test')
        ->and($customer->anonymized_at)->toBeNull();
});

it('keeps a held customer intact then anonymizes after the hold is released with audit and outbox evidence', function () {
    [$owner, $tenant, $unit] = legalRetentionWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'email' => 'customer@example.test',
        'phone' => '11999999999',
        'notes' => 'Sensitive operational note',
        'birth_date' => now()->subYears(30)->toDateString(),
        'last_activity_at' => now()->subDays(31),
    ]);
    legalRetentionPolicy($tenant, $unit);
    $hold = app(CreateLegalHold::class)->handle($owner, $context, $customer, ['reason' => 'Open dispute']);

    $held = app(AnonymizeExpiredCustomers::class)->handle($owner, $context);
    $customer->refresh();
    expect($held)->toMatchArray(['eligible' => 1, 'anonymized' => 0, 'held' => 1])
        ->and($customer->anonymized_at)->toBeNull();

    app(ReleaseLegalHold::class)->handle($owner, $context, $hold);
    $completed = app(AnonymizeExpiredCustomers::class)->handle($owner, $context);
    $customer->refresh();

    expect($completed['anonymized'])->toBe(1)
        ->and($customer->retention_status)->toBe('anonymized')
        ->and($customer->email)->toContain('@deleted.local')
        ->and($customer->phone)->toBeNull()
        ->and($customer->notes)->toBeNull()
        ->and($customer->birth_date)->toBeNull();
    expect(AuditEvent::query()->where('tenant_id', $tenant->getKey())->where('action', 'privacy.customer.anonymized')->exists())->toBeTrue()
        ->and(OutboxEvent::query()->where('tenant_id', $tenant->getKey())->where('event_type', 'privacy.customer.anonymized')->exists())->toBeTrue();
});

it('isolates policies and customers to the authenticated tenant', function () {
    [$owner, $tenant, $unit] = legalRetentionWorkspace();
    [$otherOwner, $otherTenant, $otherUnit] = legalRetentionWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'last_activity_at' => now()->subDays(31)]);
    $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->getKey(), 'unit_id' => $otherUnit->getKey(), 'last_activity_at' => now()->subDays(31)]);
    legalRetentionPolicy($tenant, $unit);
    legalRetentionPolicy($otherTenant, $otherUnit);

    $summary = app(AnonymizeExpiredCustomers::class)->handle($owner, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()));

    $customer->refresh();
    $otherCustomer->refresh();
    expect($summary['anonymized'])->toBe(1)
        ->and($customer->anonymized_at)->not->toBeNull()
        ->and($otherCustomer->anonymized_at)->toBeNull();

    $this->actingAs($owner)
        ->postJson(route('legal_holds.store', $otherCustomer), ['reason' => 'Cross-workspace'])
        ->assertForbidden();
});

it('makes legal hold creation idempotent and records exactly one audit and outbox event', function () {
    [$owner, $tenant, $unit] = legalRetentionWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $headers = ['X-Idempotency-Key' => 'legal-hold-'.Str::uuid()];

    $first = $this->actingAs($owner)->withHeaders($headers)->postJson(route('legal_holds.store', $customer), ['reason' => 'Open dispute']);
    $first->assertCreated();
    $this->actingAs($owner)->withHeaders($headers)->postJson(route('legal_holds.store', $customer), ['reason' => 'Open dispute'])->assertCreated();

    expect(LegalHold::query()->where('tenant_id', $tenant->getKey())->where('customer_id', $customer->getKey())->whereNull('released_at')->count())->toBe(1)
        ->and(AuditEvent::query()->where('tenant_id', $tenant->getKey())->where('action', 'privacy.legal_hold.placed')->count())->toBe(1)
        ->and(OutboxEvent::query()->where('tenant_id', $tenant->getKey())->where('event_type', 'privacy.legal_hold.placed')->count())->toBe(1);
});

it('validates supported policy dimensions and protects the tenant default policy from duplicates', function () {
    [, $tenant] = legalRetentionWorkspace();
    legalRetentionPolicy($tenant);

    expect(fn () => DataRetentionPolicy::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $tenant->getKey(),
        'data_class' => 'customer',
        'retention_days' => 30,
        'anchor' => 'last_activity_at',
    ]))->toThrow(QueryException::class)
        ->and(fn () => DataRetentionPolicy::query()->create([
            'id' => (string) Str::uuid7(),
            'tenant_id' => $tenant->getKey(),
            'data_class' => 'unsupported',
            'retention_days' => 30,
            'anchor' => 'last_activity_at',
        ]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DataRetentionPolicy::query()->create([
            'id' => (string) Str::uuid7(),
            'tenant_id' => $tenant->getKey(),
            'data_class' => 'customer',
            'retention_days' => 30,
            'anchor' => 'unsupported',
        ]))->toThrow(InvalidArgumentException::class);
});
