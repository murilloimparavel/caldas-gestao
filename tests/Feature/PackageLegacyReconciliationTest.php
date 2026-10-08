<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Packages\ConsumePackageSession;
use App\Actions\Marketing\Packages\ReversePackageUsage;
use App\Actions\Sales\AddSaleItem;
use App\Actions\Sales\RemoveSaleItem;
use App\Models\AuditEvent;
use App\Models\ClosingSession;
use App\Models\ClosingSessionPayment;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\FinancialObligation;
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
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @return array{User, Tenant, Unit, Customer, PackageTemplate, Service} */
function legacyPackageReconciliationWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Legacy package reconciliation '.Str::random(8),
        'slug' => 'legacy-package-reconciliation-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template->services()->attach($service, [
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'included_quantity' => 4,
    ]);

    return [$owner, $tenant, $unit, $customer, $template, $service];
}

/** @return array{CustomerPackage, CustomerPackageService} */
function createLegacyPackageRecord(Customer $customer, PackageTemplate $template, Service $service, array $overrides = []): array
{
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'unit_id' => $customer->unit_id,
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name, 'quantity' => 4]],
        'total_sessions' => 4,
        'remaining_sessions' => 4,
        'status' => 'active',
        ...$overrides,
    ]);
    $balance = CustomerPackageService::query()->create([
        'tenant_id' => $customer->tenant_id,
        'unit_id' => $customer->unit_id,
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 4,
        'remaining_quantity' => 4,
    ]);

    return [$package, $balance];
}

function attachPaidSaleEvidenceToLegacyPackage(User $owner, Customer $customer, PackageTemplate $template, CustomerPackage $package): void
{
    $category = SaleCategory::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'unit_id' => $customer->unit_id,
        'type' => 'mixed',
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'unit_id' => $customer->unit_id,
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
        'final_amount_cents' => 10000,
    ]);
    $package->forceFill(['sale_id' => $sale->getKey()])->save();
    SaleItem::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'unit_id' => $customer->unit_id,
        'sale_id' => $sale->getKey(),
        'item_type' => 'package',
        'package_template_id' => $template->getKey(),
        'customer_package_id' => $package->getKey(),
        'total_cents' => 10000,
    ]);
    $closing = ClosingSession::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'unit_id' => $customer->unit_id,
        'status' => 'completed',
        'expected_total_cents' => 10000,
        'final_total_cents' => 10000,
    ]);
    $closing->sales()->attach($sale->getKey());
    ClosingSessionPayment::query()->create([
        'tenant_id' => $customer->tenant_id,
        'unit_id' => $customer->unit_id,
        'closing_session_id' => $closing->getKey(),
        'payment_method' => 'pix',
        'amount_cents' => 10000,
        'change_cents' => 0,
        'recorded_by_user_id' => $owner->getKey(),
        'recorded_at' => now(),
    ]);
}

it('dry runs and safely reconciles legacy active packages without changing financial or usage history', function (): void {
    [$owner, $tenant, $unit, $customer, $template, $service] = legacyPackageReconciliationWorkspace();
    [$withoutSale] = createLegacyPackageRecord($customer, $template, $service);

    $openSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'status' => 'open',
        'final_amount_cents' => 10000,
    ]);
    [$withOpenSale] = createLegacyPackageRecord($customer, $template, $service, ['sale_id' => $openSale->getKey()]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $openSale->getKey(),
        'item_type' => 'package',
        'package_template_id' => $template->getKey(),
        'customer_package_id' => $withOpenSale->getKey(),
        'total_cents' => 10000,
    ]);

    $paidSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'status' => 'finalized',
        'final_amount_cents' => 10000,
    ]);
    [$withPaidClosing] = createLegacyPackageRecord($customer, $template, $service, ['sale_id' => $paidSale->getKey()]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $paidSale->getKey(),
        'item_type' => 'package',
        'package_template_id' => $template->getKey(),
        'customer_package_id' => $withPaidClosing->getKey(),
        'total_cents' => 10000,
    ]);
    $closingSession = ClosingSession::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'completed',
        'expected_total_cents' => 10000,
        'final_total_cents' => 10000,
    ]);
    $closingSession->sales()->attach($paidSale->getKey());
    ClosingSessionPayment::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'closing_session_id' => $closingSession->getKey(),
        'payment_method' => 'pix',
        'amount_cents' => 10000,
        'change_cents' => 0,
        'recorded_by_user_id' => $owner->getKey(),
        'recorded_at' => now(),
    ]);

    [$withAmbiguousFinance] = createLegacyPackageRecord($customer, $template, $service);
    $obligation = FinancialObligation::factory()->receivable()->paid()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'customer_package_id' => $withAmbiguousFinance->getKey(),
        'amount_cents' => 10000,
    ]);

    $packageCount = CustomerPackage::query()->count();
    $usageCount = PackageUsage::query()->count();
    $financialObligationCount = FinancialObligation::query()->count();

    $this->artisan('packages:reconcile-legacy', ['--tenant' => $tenant->getKey()])
        ->expectsOutputToContain('Pending: 2')
        ->expectsOutputToContain('Review required: 1')
        ->expectsOutputToContain('Mantidos ativos: 1')
        ->assertSuccessful();

    expect($withoutSale->fresh()->status)->toBe('active')
        ->and($withOpenSale->fresh()->status)->toBe('active')
        ->and($withPaidClosing->fresh()->status)->toBe('active')
        ->and($withAmbiguousFinance->fresh()->status)->toBe('active')
        ->and(CustomerPackage::query()->count())->toBe($packageCount)
        ->and(PackageUsage::query()->count())->toBe($usageCount)
        ->and(FinancialObligation::query()->count())->toBe($financialObligationCount);

    $this->artisan('packages:reconcile-legacy', ['--tenant' => $tenant->getKey(), '--apply' => true])
        ->expectsOutputToContain('A opção --actor é obrigatória')
        ->assertExitCode(2);

    $this->artisan('packages:reconcile-legacy', [
        '--tenant' => $tenant->getKey(), '--actor' => $owner->getKey(), '--backup-reference' => 'snapshot-test-2026-10-08', '--apply' => true,
    ])
        ->expectsOutputToContain('Atualizados: 3')
        ->assertSuccessful();

    expect($withoutSale->fresh()->status)->toBe('pending')
        ->and($withOpenSale->fresh()->status)->toBe('pending')
        ->and($withPaidClosing->fresh()->status)->toBe('active')
        ->and($withAmbiguousFinance->fresh()->status)->toBe('review_required')
        ->and($withAmbiguousFinance->fresh()->remaining_sessions)->toBe(4)
        ->and($obligation->fresh()->status)->toBe('paid')
        ->and(AuditEvent::query()->where('action', 'customer_package.legacy_reconciled')->count())->toBe(3);
    expect(AuditEvent::query()->where('action', 'customer_package.legacy_reconciled')->firstOrFail()->actor_user_id)
        ->toBe($owner->getKey())
        ->and(AuditEvent::query()->where('action', 'customer_package.legacy_reconciled')->firstOrFail()->metadata['backup_reference'])
        ->toBe('snapshot-test-2026-10-08');

    $this->artisan('packages:reconcile-legacy', [
        '--tenant' => $tenant->getKey(), '--actor' => $owner->getKey(), '--backup-reference' => 'snapshot-test-2026-10-08', '--apply' => true,
    ])
        ->expectsOutputToContain('Atualizados: 0')
        ->assertSuccessful();
});

it('keeps a reviewed package in review after reversing a prior usage', function (): void {
    [$owner, $tenant, $unit, $customer, $template, $service] = legacyPackageReconciliationWorkspace();
    [$package] = createLegacyPackageRecord($customer, $template, $service);
    attachPaidSaleEvidenceToLegacyPackage($owner, $customer, $template, $package);
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    (new ConsumePackageSession)->handle($owner, $context, $package, ['service_id' => $service->getKey()]);
    $usage = $package->usages()->latest()->firstOrFail();
    $package->forceFill(['status' => 'review_required'])->save();

    (new ReversePackageUsage)->handle($owner, $context, $usage, ['reason' => 'Auditoria de legado']);

    expect($package->fresh()->status)->toBe('review_required')
        ->and($package->fresh()->remaining_sessions)->toBe(4);
});

it('keeps a reviewed package in review when a reserved comanda item is removed', function (): void {
    [$owner, $tenant, $unit, $customer, $template, $service] = legacyPackageReconciliationWorkspace();
    [$package] = createLegacyPackageRecord($customer, $template, $service);
    attachPaidSaleEvidenceToLegacyPackage($owner, $customer, $template, $package);
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'service',
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open',
    ]);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
    ]);
    $reservation = PackageUsageReservation::query()->where('sale_item_id', $item->getKey())->firstOrFail();
    $usage = PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'service_id' => $service->getKey(),
        'user_id' => $owner->getKey(),
        'sessions_consumed' => 1,
    ]);
    $reservation->forceFill(['status' => 'consumed', 'package_usage_id' => $usage->getKey()])->save();
    $package->forceFill(['status' => 'review_required', 'remaining_sessions' => 3])->save();
    $package->serviceBalances()->where('service_id', $service->getKey())->update(['remaining_quantity' => 3]);

    (new RemoveSaleItem)->handle($owner, $context, $sale, $item);

    expect($package->fresh()->status)->toBe('review_required')
        ->and($package->fresh()->remaining_sessions)->toBe(4)
        ->and($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(4)
        ->and($usage->fresh()->reversed_at)->not->toBeNull();
});

it('requires review for cross-customer sales, duplicate package lines, balance conflicts, and reversed payments', function (): void {
    [$owner, $tenant, $unit, $customer, $template, $service] = legacyPackageReconciliationWorkspace();
    $packages = collect();

    foreach (['cross_customer', 'duplicate_line', 'balance_conflict', 'reversed_payment', 'pending_reservation'] as $case) {
        $saleCustomer = $case === 'cross_customer'
            ? Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
            : $customer;
        $sale = Sale::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'customer_id' => $saleCustomer->getKey(),
            'status' => 'finalized',
            'final_amount_cents' => 10000,
        ]);
        [$package] = createLegacyPackageRecord($customer, $template, $service, [
            'sale_id' => $sale->getKey(),
            ...($case === 'balance_conflict' ? ['remaining_sessions' => 3] : []),
        ]);

        $saleLine = [
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'sale_id' => $sale->getKey(),
            'item_type' => 'package',
            'package_template_id' => $template->getKey(),
            'customer_package_id' => $package->getKey(),
            'total_cents' => 10000,
        ];
        SaleItem::factory()->create($saleLine);
        if ($case === 'duplicate_line') {
            SaleItem::factory()->create($saleLine);
        }

        $closing = ClosingSession::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'status' => 'completed',
            'expected_total_cents' => 10000,
            'final_total_cents' => 10000,
        ]);
        $closing->sales()->attach($sale->getKey());
        $originalPayment = ClosingSessionPayment::query()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'closing_session_id' => $closing->getKey(),
            'payment_method' => 'pix',
            'amount_cents' => 10000,
            'change_cents' => 0,
            'recorded_by_user_id' => $owner->getKey(),
            'recorded_at' => now(),
        ]);

        if ($case === 'reversed_payment') {
            ClosingSessionPayment::query()->create([
                'tenant_id' => $tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'closing_session_id' => $closing->getKey(),
                'payment_method' => 'pix',
                'amount_cents' => 10000,
                'change_cents' => 0,
                'is_reversal' => true,
                'reversal_of_id' => $originalPayment->getKey(),
                'reversal_reason' => 'Pagamento estornado no cenário de teste',
                'recorded_by_user_id' => $owner->getKey(),
                'recorded_at' => now(),
            ]);
        }

        if ($case === 'pending_reservation') {
            $openSale = Sale::factory()->create([
                'tenant_id' => $tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'customer_id' => $customer->getKey(),
                'status' => 'open',
            ]);
            $serviceItem = SaleItem::factory()->create([
                'tenant_id' => $tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'sale_id' => $openSale->getKey(),
                'item_type' => 'service',
                'service_id' => $service->getKey(),
                'customer_package_id' => $package->getKey(),
                'covered_quantity' => 1,
                'total_cents' => 0,
            ]);
            PackageUsageReservation::query()->create([
                'tenant_id' => $tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'customer_package_id' => $package->getKey(),
                'sale_id' => $openSale->getKey(),
                'sale_item_id' => $serviceItem->getKey(),
                'service_id' => $service->getKey(),
                'user_id' => $owner->getKey(),
                'sessions_reserved' => 1,
                'status' => 'reserved',
            ]);
        }
        $packages->push($package);
    }

    $this->artisan('packages:reconcile-legacy', ['--tenant' => $tenant->getKey()])
        ->expectsOutputToContain('Review required: 5')
        ->assertSuccessful();

    $this->artisan('packages:reconcile-legacy', [
        '--tenant' => $tenant->getKey(), '--actor' => $owner->getKey(), '--backup-reference' => 'snapshot-test-2026-10-08', '--apply' => true,
    ])
        ->expectsOutputToContain('Review required: 5')
        ->assertSuccessful();

    expect($packages->every(fn (CustomerPackage $package): bool => $package->fresh()->status === 'review_required'))->toBeTrue();
});

it('blocks manual and comanda consumption for packages that need review', function (): void {
    [$owner, $tenant, $unit, $customer, $template, $service] = legacyPackageReconciliationWorkspace();
    [$package] = createLegacyPackageRecord($customer, $template, $service, ['status' => 'review_required']);
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());

    expect(fn () => (new ConsumePackageSession)->handle($owner, $context, $package, [
        'service_id' => $service->getKey(),
    ]))->toThrow(ConflictHttpException::class);

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
    ]);

    expect(fn () => (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
    ]))->toThrow(ValidationException::class);

    expect($package->fresh()->remaining_sessions)->toBe(4)
        ->and(PackageUsage::query()->where('customer_package_id', $package->getKey())->exists())->toBeFalse();
});

it('blocks manual consumption of a legacy active package without paid sale evidence', function (): void {
    [$owner, $tenant, $unit, $customer, $template, $service] = legacyPackageReconciliationWorkspace();
    [$package] = createLegacyPackageRecord($customer, $template, $service);
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());

    expect(fn () => (new ConsumePackageSession)->handle($owner, $context, $package, [
        'service_id' => $service->getKey(),
    ]))->toThrow(ConflictHttpException::class, 'O pacote não tem uma comanda paga válida e precisa de revisão.');

    expect($package->fresh()->status)->toBe('active')
        ->and($package->fresh()->remaining_sessions)->toBe(4)
        ->and($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(4)
        ->and(PackageUsage::query()->where('customer_package_id', $package->getKey())->exists())->toBeFalse();
});

it('blocks adding a package-covered service for a legacy active package without paid sale evidence', function (): void {
    [$owner, $tenant, $unit, $customer, $template, $service] = legacyPackageReconciliationWorkspace();
    [$package] = createLegacyPackageRecord($customer, $template, $service);
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'service',
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open',
    ]);

    expect(fn () => (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'service', 'service_id' => $service->getKey(), 'customer_package_id' => $package->getKey(),
    ]))->toThrow(ValidationException::class, 'comprovação válida de venda paga');

    expect($package->fresh()->status)->toBe('active')
        ->and($package->fresh()->remaining_sessions)->toBe(4)
        ->and($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(4)
        ->and($sale->items()->exists())->toBeFalse()
        ->and(PackageUsageReservation::query()->where('customer_package_id', $package->getKey())->exists())->toBeFalse();
});
