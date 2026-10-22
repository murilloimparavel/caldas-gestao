<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Packages\CreatePackageTemplate;
use App\Actions\Marketing\Packages\MutateCustomerPackageBalance;
use App\Actions\Marketing\Packages\ReversePackageUsage;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageUsage;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

it('uses the package total for a single service when its quantity is omitted', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package allocation '.Str::random(8),
        'slug' => 'package-allocation-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $template = (new CreatePackageTemplate)->handle($owner, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()), [
        'name' => 'Barba mensal', 'price_cents' => 10000, 'total_sessions' => 8,
        'validity_days' => 45, 'service_ids' => [$service->getKey()],
    ]);

    expect($template->services()->whereKey($service->getKey())->firstOrFail()->pivot->included_quantity)->toBe(8);
});

it('rejects multiple service allocations that do not add up to the package total', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package allocation '.Str::random(8),
        'slug' => 'package-allocation-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $services = Service::factory()->count(2)->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    expect(fn (): mixed => (new CreatePackageTemplate)->handle($owner, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()), [
        'name' => 'Combo inválido', 'price_cents' => 10000, 'total_sessions' => 8,
        'validity_days' => 45, 'service_ids' => $services->modelKeys(),
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects balance mutations when aggregate and service balances diverge', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package balance '.Str::random(8),
        'slug' => 'package-balance-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $services = Service::factory()->count(2)->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(),
        'total_sessions' => 3, 'remaining_sessions' => 3,
    ]);

    CustomerPackageService::query()->insert([
        ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_package_id' => $package->getKey(), 'service_id' => $services[0]->getKey(), 'allocated_quantity' => 2, 'remaining_quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
        ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_package_id' => $package->getKey(), 'service_id' => $services[1]->getKey(), 'allocated_quantity' => 1, 'remaining_quantity' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(fn (): mixed => (new MutateCustomerPackageBalance)->consume($package, $tenant->getKey(), $unit->getKey(), [$services[0]->getKey() => 1]))
        ->toThrow(ConflictHttpException::class, 'aggregate and service balances are inconsistent');
});

it('returns friendly validation errors for incomplete or non-array service quantities', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package request '.Str::random(8),
        'slug' => 'package-request-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $services = Service::factory()->count(2)->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $payload = [
        'name' => 'Combo válido', 'price_cents' => 10000, 'total_sessions' => 4,
        'validity_days' => 45, 'service_ids' => $services->modelKeys(),
    ];

    $this->actingAs($owner)->post(route('packages.store'), $payload)
        ->assertSessionHasErrors('service_quantities');

    $this->actingAs($owner)->post(route('packages.store'), [
        ...$payload,
        'service_quantities' => 'not-an-array',
    ])->assertSessionHasErrors('service_quantities');
});

it('rejects consumption when service balances drift while aggregate balances still match', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package ledger '.Str::random(8),
        'slug' => 'package-ledger-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $services = Service::factory()->count(2)->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'total_sessions' => 3,
        'remaining_sessions' => 2,
        'eligible_services_snapshot' => [
            ['id' => (string) $services[0]->getKey(), 'name' => $services[0]->name, 'quantity' => 2],
            ['id' => (string) $services[1]->getKey(), 'name' => $services[1]->name, 'quantity' => 1],
        ],
    ]);

    CustomerPackageService::query()->insert([
        ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_package_id' => $package->getKey(), 'service_id' => $services[0]->getKey(), 'allocated_quantity' => 2, 'remaining_quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
        ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_package_id' => $package->getKey(), 'service_id' => $services[1]->getKey(), 'allocated_quantity' => 1, 'remaining_quantity' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);
    PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $services[0]->getKey(),
        'sessions_consumed' => 1,
        'user_id' => $owner->getKey(),
    ]);

    expect(fn (): mixed => (new MutateCustomerPackageBalance)->consume(
        $package,
        $tenant->getKey(),
        $unit->getKey(),
        [$services[1]->getKey() => 1],
    ))->toThrow(ConflictHttpException::class, 'snapshot and usage history');
});

it('rejects a populated package snapshot without valid allocations', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package snapshot '.Str::random(8),
        'slug' => 'package-snapshot-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'total_sessions' => 1,
        'remaining_sessions' => 1,
        'eligible_services_snapshot' => [['id' => (string) $service->getKey(), 'name' => $service->name]],
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 1,
        'remaining_quantity' => 1,
    ]);

    expect(fn (): mixed => (new MutateCustomerPackageBalance)->consume(
        $package,
        $tenant->getKey(),
        $unit->getKey(),
        [$service->getKey() => 1],
    ))->toThrow(ConflictHttpException::class, 'allocation snapshot is invalid');
});

it('rejects package balance mutations when the allocation snapshot is missing', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package missing snapshot '.Str::random(8),
        'slug' => 'package-missing-snapshot-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'total_sessions' => 1,
        'remaining_sessions' => 1,
        'eligible_services_snapshot' => null,
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 1,
        'remaining_quantity' => 1,
    ]);

    expect(fn (): mixed => (new MutateCustomerPackageBalance)->consume(
        $package,
        $tenant->getKey(),
        $unit->getKey(),
        [$service->getKey() => 1],
    ))->toThrow(ConflictHttpException::class, 'snapshot is missing');
});

it('validates a reversal against the post-restore service ledger', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package reversal '.Str::random(8),
        'slug' => 'package-reversal-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $services = Service::factory()->count(2)->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'total_sessions' => 3,
        'remaining_sessions' => 2,
        'status' => 'active',
        'eligible_services_snapshot' => [
            ['id' => (string) $services[0]->getKey(), 'name' => $services[0]->name, 'quantity' => 2],
            ['id' => (string) $services[1]->getKey(), 'name' => $services[1]->name, 'quantity' => 1],
        ],
    ]);

    CustomerPackageService::query()->insert([
        ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_package_id' => $package->getKey(), 'service_id' => $services[0]->getKey(), 'allocated_quantity' => 2, 'remaining_quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_package_id' => $package->getKey(), 'service_id' => $services[1]->getKey(), 'allocated_quantity' => 1, 'remaining_quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $usage = PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $services[0]->getKey(),
        'sessions_consumed' => 1,
        'user_id' => $owner->getKey(),
    ]);

    (new ReversePackageUsage)->handle(
        $owner,
        TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()),
        $usage,
        ['reason' => 'Correção de lançamento'],
    );

    expect($usage->fresh()->reversed_at)->not->toBeNull()
        ->and((int) $package->fresh()->remaining_sessions)->toBe(3)
        ->and((int) CustomerPackageService::query()
            ->where('customer_package_id', $package->getKey())
            ->where('service_id', $services[0]->getKey())
            ->value('remaining_quantity'))->toBe(2);
});
