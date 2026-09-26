<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Packages\ConsumePackageSession;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\IdempotencyKey;
use App\Models\PackageTemplate;
use App\Models\PackageUsage;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function packageIntegrityWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package integrity '.Str::random(8),
        'slug' => 'package-integrity-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('snapshots package terms and rejects a source sale for another customer', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $otherCustomer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Corte']);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte mensal',
        'price_cents' => 24900,
        'total_sessions' => 4,
        'validity_days' => 30,
    ]);
    $template->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $foreignCustomerSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $otherCustomer->getKey(),
    ]);

    $this->actingAs($owner)
        ->post(route('customer-packages.store'), [
            'customer_id' => $customer->getKey(),
            'package_template_id' => $template->getKey(),
            'sale_id' => $foreignCustomerSale->getKey(),
        ])
        ->assertSessionHasErrors('sale_id');

    $this->actingAs($owner)
        ->post(route('customer-packages.store'), [
            'customer_id' => $customer->getKey(),
            'package_template_id' => $template->getKey(),
        ])
        ->assertSessionHasNoErrors();

    $package = CustomerPackage::query()->where('customer_id', $customer->getKey())->firstOrFail();
    expect($package->name_snapshot)->toBe('Corte mensal')
        ->and($package->price_cents_snapshot)->toBe(24900)
        ->and($package->total_sessions_snapshot)->toBe(4)
        ->and($package->validity_days_snapshot)->toBe(30)
        ->and($package->eligible_services_snapshot)->toBe([['id' => $service->getKey(), 'name' => 'Corte', 'quantity' => 1]])
        ->and($package->serviceBalances()->first()->remaining_quantity)->toBe(1);

    $template->update(['name' => 'Novo nome', 'price_cents' => 29900]);
    $package->refresh();

    expect($package->name_snapshot)->toBe('Corte mensal')
        ->and($package->price_cents_snapshot)->toBe(24900);
});

it('consumes only an eligible service item from the selected customer sale', function (): void {
    [$owner, $tenant, $unit, $context] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $eligibleService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $ineligibleService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'eligible_services_snapshot' => [['id' => $eligibleService->getKey(), 'name' => $eligibleService->name]],
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
    ]);
    $eligibleItem = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $eligibleService->getKey(),
    ]);
    $ineligibleItem = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $ineligibleService->getKey(),
    ]);

    (new ConsumePackageSession)->handle($owner, $context, $package, [
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $eligibleItem->getKey(),
    ]);

    expect($package->fresh()->remaining_sessions)->toBe(4)
        ->and(PackageUsage::query()->where('sale_item_id', $eligibleItem->getKey())->exists())->toBeTrue();

    expect(fn () => (new ConsumePackageSession)->handle($owner, $context, $package->fresh(), [
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $ineligibleItem->getKey(),
    ]))->toThrow(InvalidArgumentException::class, 'not eligible');
});

it('tracks quantities and consumption independently for each package service', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $beard = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Barba']);
    $botox = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Botox']);

    $this->actingAs($owner)->post(route('packages.store'), [
        'name' => 'Combo personalizado',
        'price_cents' => 30000,
        'total_sessions' => 5,
        'validity_days' => 90,
        'service_ids' => [$beard->getKey(), $botox->getKey()],
        'service_quantities' => [$beard->getKey() => 3, $botox->getKey() => 2],
    ])->assertSessionHasNoErrors();

    $template = PackageTemplate::query()->where('name', 'Combo personalizado')->firstOrFail();

    $this->actingAs($owner)->post(route('customer-packages.store'), [
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
    ])->assertSessionHasNoErrors();

    $package = CustomerPackage::query()->where('customer_id', $customer->getKey())->firstOrFail();
    expect($package->serviceBalances()->where('service_id', $beard->getKey())->value('allocated_quantity'))->toBe(3)
        ->and($package->serviceBalances()->where('service_id', $botox->getKey())->value('allocated_quantity'))->toBe(2);

    $this->actingAs($owner)->post(route('customer-packages.consume', $package), [
        'service_id' => $beard->getKey(),
        'sessions_consumed' => 2,
    ])->assertSessionHasNoErrors();

    expect($package->serviceBalances()->where('service_id', $beard->getKey())->value('remaining_quantity'))->toBe(1)
        ->and($package->serviceBalances()->where('service_id', $botox->getKey())->value('remaining_quantity'))->toBe(2)
        ->and(PackageUsage::query()->where('customer_package_id', $package->getKey())->value('service_id'))->toBe($beard->getKey());
});

it('scopes package idempotency to the customer package route resource', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $firstPackage = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
    ]);
    $secondPackage = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
    ]);

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'package-consume-scope')
        ->post(route('customer-packages.consume', $firstPackage), [])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'package-consume-scope')
        ->post(route('customer-packages.consume', $secondPackage), [])
        ->assertStatus(409);

    expect($firstPackage->fresh()->remaining_sessions)->toBe(4)
        ->and($secondPackage->fresh()->remaining_sessions)->toBe(5)
        ->and(IdempotencyKey::query()->where('key', 'package-consume-scope')->value('resource_id'))
        ->toBe($firstPackage->getKey());
});

it('reverses a usage once, restores the balance, and records governance events', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'total_sessions' => 2,
        'remaining_sessions' => 1,
    ]);
    $usage = PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sessions_consumed' => 1,
        'user_id' => $owner->getKey(),
    ]);

    $response = $this->actingAs($owner)
        ->post(route('customer-packages.usages.reverse', [$package, $usage]), ['reason' => 'Lançamento em duplicidade']);
    $response->assertSessionHasNoErrors();

    expect($package->fresh()->remaining_sessions)->toBe(2)
        ->and($usage->fresh()->reversed_at)->not->toBeNull()
        ->and($usage->fresh()->reversed_by_user_id)->toBe($owner->getKey());

    $this->assertDatabaseHas('audit_events', ['action' => 'package_usage.reversed', 'resource_id' => $usage->getKey()]);
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'package_usage.reversed', 'aggregate_id' => $usage->getKey()]);

    $this->actingAs($owner)
        ->post(route('customer-packages.usages.reverse', [$package, $usage]), ['reason' => 'Tentativa repetida'])
        ->assertStatus(409);
});

it('expires packages in an idempotent batch with audit and outbox records', function (): void {
    [, $tenant, $unit] = packageIntegrityWorkspace();
    $expired = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->subDay()->toDateString(),
        'status' => 'active',
    ]);
    $current = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay()->toDateString(),
        'status' => 'active',
    ]);

    $this->artisan('app:expire-customer-packages')->assertSuccessful();

    expect($expired->fresh()->status)->toBe('expired')
        ->and($current->fresh()->status)->toBe('active');
    $this->assertDatabaseHas('audit_events', ['action' => 'customer_package.expired', 'resource_id' => $expired->getKey()]);
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'customer_package.expired', 'aggregate_id' => $expired->getKey()]);

    $this->artisan('app:expire-customer-packages')->assertSuccessful();
    expect(CustomerPackage::query()->where('status', 'expired')->count())->toBe(1);
});
