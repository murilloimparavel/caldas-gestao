<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageTemplate;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: CustomerPackage, 4: Service} */
function balanceAuditWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Balance audit '.Str::random(8),
        'slug' => 'balance-audit-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Corte']);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'total_sessions' => 4]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'total_sessions' => 4,
        'remaining_sessions' => 3,
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name, 'quantity' => 4]],
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 4,
        'remaining_quantity' => 0,
    ]);
    PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'sessions_consumed' => 1,
        'user_id' => $owner->getKey(),
    ]);

    return [$owner, $tenant, $unit, $package, $service];
}

it('audits aggregate and service balance divergence without changing records by default', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $lockVersion = $package->lock_version;

    $this->artisan('packages:audit-balances', [
        '--tenant' => $tenant->getKey(),
        '--package' => $package->getKey(),
        '--format' => 'json',
    ])->expectsOutputToContain('"expected_remaining_sessions": 3')->assertSuccessful();

    expect($package->fresh()->remaining_sessions)->toBe(3)
        ->and($package->fresh()->lock_version)->toBe($lockVersion)
        ->and($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(0)
        ->and(AuditEvent::query()->where('action', 'customer_package.balance_reconciled')->exists())->toBeFalse();
});

it('applies only an evidence complete repair with a reason and audit event', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $lockVersion = $package->lock_version;

    $this->artisan('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--format' => 'table',
        '--apply' => true,
        '--actor' => $owner->getKey(),
        '--reason' => 'Auditoria aprovada do saldo do pacote',
    ])->expectsOutputToContain('Reparo aplicado com sucesso.')->assertSuccessful();

    expect($package->fresh()->remaining_sessions)->toBe(3)
        ->and($package->fresh()->lock_version)->toBe($lockVersion + 1)
        ->and($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(3)
        ->and(AuditEvent::query()->where('action', 'customer_package.balance_reconciled')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'customer_package.balance_reconciled')->firstOrFail()->reason)
        ->toBe('Auditoria aprovada do saldo do pacote');
});

it('updates every intended service balance by composite scope and leaves another package untouched', function (): void {
    [$owner, $tenant, $unit, $package, $firstService] = balanceAuditWorkspace();
    $secondService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $package->forceFill([
        'eligible_services_snapshot' => [
            ['id' => $firstService->getKey(), 'name' => $firstService->name, 'quantity' => 2],
            ['id' => $secondService->getKey(), 'name' => $secondService->name, 'quantity' => 2],
        ],
    ])->save();
    $package->serviceBalances()->where('service_id', $firstService->getKey())->update(['allocated_quantity' => 2]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $secondService->getKey(),
        'allocated_quantity' => 2,
        'remaining_quantity' => 0,
    ]);
    $otherPackage = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $package->customer_id,
        'total_sessions' => 4,
        'remaining_sessions' => 4,
        'eligible_services_snapshot' => [['id' => $firstService->getKey(), 'name' => $firstService->name, 'quantity' => 4]],
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $otherPackage->getKey(),
        'service_id' => $firstService->getKey(),
        'allocated_quantity' => 4,
        'remaining_quantity' => 0,
    ]);

    $this->artisan('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--apply' => true,
        '--actor' => $owner->getKey(),
        '--reason' => 'Auditoria de múltiplos serviços',
    ])->expectsOutputToContain('Reparo aplicado com sucesso.')->assertSuccessful();

    expect($package->serviceBalances()->where('service_id', $firstService->getKey())->value('remaining_quantity'))->toBe(1)
        ->and($package->serviceBalances()->where('service_id', $secondService->getKey())->value('remaining_quantity'))->toBe(2)
        ->and($otherPackage->serviceBalances()->where('service_id', $firstService->getKey())->value('remaining_quantity'))->toBe(0);
});

it('distinguishes an apply request that finds no divergence from an applied repair', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $package->serviceBalances()->where('service_id', $service->getKey())->update(['remaining_quantity' => 3]);
    $lockVersion = $package->lock_version;

    $exitCode = Artisan::call('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--format' => 'json',
        '--apply' => true,
        '--actor' => $owner->getKey(),
        '--reason' => 'Confirmação de saldo já consistente',
    ]);
    $output = Artisan::output();
    $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($output)->not->toBeEmpty()
        ->and($payload['apply_requested'])->toBeTrue()
        ->and($payload['applied'])->toBeFalse()
        ->and($payload['changed'])->toBeFalse();

    expect($package->fresh()->lock_version)->toBe($lockVersion)
        ->and(AuditEvent::query()->where('action', 'customer_package.balance_reconciled')->exists())->toBeFalse();
});

it('accepts a consistent legacy consumed reservation with a null sale item package link and warns', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $package->customer_id,
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => null,
        'covered_quantity' => 1,
    ]);
    $usage = PackageUsage::query()->where('customer_package_id', $package->getKey())->firstOrFail();
    $usage->forceFill(['sale_id' => $sale->getKey(), 'sale_item_id' => $item->getKey()])->save();
    PackageUsageReservation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'service_id' => $service->getKey(),
        'package_usage_id' => $usage->getKey(),
        'user_id' => $owner->getKey(),
        'sessions_reserved' => 1,
        'status' => 'consumed',
    ]);

    $this->artisan('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--format' => 'json',
    ])->expectsOutputToContain('legacy_sale_item_package_link_missing')->assertSuccessful();

    expect($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(0);
});

it('repairs balances when a released reservation matches its reversed usage and sale item', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $package->customer_id,
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
        'covered_quantity' => 1,
    ]);
    $usage = PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'service_id' => $service->getKey(),
        'sessions_consumed' => 1,
        'reversed_at' => now(),
    ]);
    PackageUsageReservation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'service_id' => $service->getKey(),
        'package_usage_id' => $usage->getKey(),
        'user_id' => $owner->getKey(),
        'sessions_reserved' => 1,
        'status' => 'released',
        'released_at' => now(),
    ]);

    $this->artisan('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--apply' => true,
        '--actor' => $owner->getKey(),
        '--reason' => 'Reconciliar uso estornado e reserva liberada',
    ])->expectsOutputToContain('Reparo aplicado com sucesso.')->assertSuccessful();

    expect($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(3);
});

it('preserves a package when a released reservation conflicts with its reversed usage', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $package->customer_id,
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
        'covered_quantity' => 1,
    ]);
    $usage = PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'service_id' => $service->getKey(),
        'sessions_consumed' => 2,
        'reversed_at' => now(),
    ]);
    PackageUsageReservation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'service_id' => $service->getKey(),
        'package_usage_id' => $usage->getKey(),
        'user_id' => $owner->getKey(),
        'sessions_reserved' => 1,
        'status' => 'released',
        'released_at' => now(),
    ]);
    $beforePackage = $package->fresh()->toArray();
    $beforeBalance = $package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity');

    $this->artisan('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--apply' => true,
        '--actor' => $owner->getKey(),
        '--reason' => 'Preservar vínculo de uso divergente',
    ])->expectsOutputToContain('preservado sem alterações.')->assertFailed();

    expect($package->fresh()->toArray())->toEqual($beforePackage)
        ->and($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe($beforeBalance)
        ->and(AuditEvent::query()->where('action', 'customer_package.balance_reconciled')->exists())->toBeFalse();
});

it('requires a release timestamp before accepting a released reservation', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $package->customer_id,
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
        'covered_quantity' => 1,
    ]);
    $usage = PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'service_id' => $service->getKey(),
        'sessions_consumed' => 1,
        'reversed_at' => now(),
    ]);
    PackageUsageReservation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'service_id' => $service->getKey(),
        'package_usage_id' => $usage->getKey(),
        'user_id' => $owner->getKey(),
        'sessions_reserved' => 1,
        'status' => 'released',
        'released_at' => null,
    ]);

    $this->artisan('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--apply' => true,
        '--actor' => $owner->getKey(),
        '--reason' => 'Preservar reserva sem data de liberação',
    ])->expectsOutputToContain('preservado sem alterações.')->assertFailed();
});

it('leaves a package ambiguous when a reservation belongs to another customer', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $otherCustomer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $otherCustomer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
        'covered_quantity' => 1,
    ]);
    PackageUsageReservation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'service_id' => $service->getKey(),
        'user_id' => $owner->getKey(),
        'sessions_reserved' => 1,
        'status' => 'reserved',
    ]);
    $before = $package->fresh()->toArray();

    $this->artisan('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--apply' => true,
        '--actor' => $owner->getKey(),
        '--reason' => 'Não corrigir reserva fora do cliente',
    ])->expectsOutputToContain('preservado sem alterações.')->assertFailed();

    expect($package->fresh()->toArray())->toEqual($before)
        ->and(AuditEvent::query()->where('action', 'customer_package.balance_reconciled')->exists())->toBeFalse();
});

it('leaves ambiguous packages untouched', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $package->forceFill(['eligible_services_snapshot' => null])->save();
    $before = $package->fresh()->toArray();

    $this->artisan('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--apply' => true,
        '--actor' => $owner->getKey(),
        '--reason' => 'Não deve reparar sem snapshot',
    ])->expectsOutputToContain('preservado sem alterações.')->assertFailed();

    expect($package->fresh()->toArray())->toEqual($before)
        ->and(AuditEvent::query()->where('action', 'customer_package.balance_reconciled')->exists())->toBeFalse();
});

it('reports malformed non-array snapshot entries as ambiguous without crashing', function (): void {
    [$owner, $tenant, $unit, $package, $service] = balanceAuditWorkspace();
    $package->forceFill([
        'eligible_services_snapshot' => [
            ['id' => $service->getKey(), 'name' => $service->name, 'quantity' => 4],
            'corrupted-entry',
        ],
    ])->save();

    $exitCode = Artisan::call('packages:audit-balances', [
        '--package' => $package->getKey(),
        '--format' => 'json',
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $report = $payload['packages'][0];

    expect($exitCode)->toBe(0)
        ->and($report['repairable'])->toBeFalse()
        ->and($report['issues'])->toContain('invalid_or_duplicate_service_snapshot')
        ->and($package->fresh()->remaining_sessions)->toBe(3)
        ->and(AuditEvent::query()->where('action', 'customer_package.balance_reconciled')->exists())->toBeFalse();
});

it('requires package and reason before any applied mutation', function (): void {
    $this->artisan('packages:audit-balances', ['--apply' => true])
        ->expectsOutputToContain('A opção --package é obrigatória')->assertExitCode(2);
});
