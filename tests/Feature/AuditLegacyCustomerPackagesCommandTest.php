<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\ClosingSession;
use App\Models\ClosingSessionPayment;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\FinancialObligation;
use App\Models\PackageTemplate;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

it('audits package command and payment evidence without changing database records', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package audit '.Str::random(8),
        'slug' => 'package-audit-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Cliente Com Pagamento',
    ]);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte mensal',
    ]);
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
        'final_amount_cents' => 10000,
    ]);
    $packageWithPaidClosing = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'sale_id' => $sale->getKey(),
        'status' => 'active',
        'total_sessions' => 4,
        'remaining_sessions' => 4,
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'package',
        'service_id' => null,
        'package_template_id' => $template->getKey(),
        'customer_package_id' => $packageWithPaidClosing->getKey(),
        'name_snapshot' => 'Corte mensal',
        'unit_price_cents' => 10000,
        'total_cents' => 10000,
    ]);
    $closingSession = ClosingSession::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'completed',
        'expected_total_cents' => 10000,
        'final_total_cents' => 10000,
    ]);
    $closingSession->sales()->attach($sale->getKey());
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

    $packageWithoutSale = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'status' => 'active',
        'total_sessions' => 5,
        'remaining_sessions' => 2,
    ]);
    FinancialObligation::factory()->receivable()->paid()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'customer_package_id' => $packageWithoutSale->getKey(),
        'amount_cents' => 30000,
    ]);

    $orphanSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
    ]);
    $packageWithOrphanItem = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'status' => 'active',
        'total_sessions' => 4,
        'remaining_sessions' => 4,
    ]);
    $orphanItem = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $orphanSale->getKey(),
        'item_type' => 'package',
        'service_id' => null,
        'package_template_id' => $template->getKey(),
        'customer_package_id' => $packageWithOrphanItem->getKey(),
        'name_snapshot' => 'Corte mensal',
        'unit_price_cents' => 30000,
        'total_cents' => 30000,
    ]);

    $wrongLinkedSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
    ]);
    $packageWithWrongSaleLink = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_id' => $wrongLinkedSale->getKey(),
        'status' => 'active',
        'total_sessions' => 2,
        'remaining_sessions' => 2,
    ]);
    $otherCandidateSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
    ]);
    $otherCandidateItem = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $otherCandidateSale->getKey(),
        'item_type' => 'package',
        'service_id' => null,
        'package_template_id' => $template->getKey(),
        'customer_package_id' => $packageWithWrongSaleLink->getKey(),
        'name_snapshot' => 'Corte mensal',
        'unit_price_cents' => 30000,
        'total_cents' => 30000,
    ]);

    $packageCountBefore = CustomerPackage::query()->count();
    $obligationCountBefore = FinancialObligation::query()->count();
    $saleCountBefore = Sale::query()->count();

    Artisan::call('packages:audit-legacy', ['--format' => 'json', '--tenant' => $tenant->getKey()]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['read_only'])->toBeTrue()
        ->and($report['totals']['packages'])->toBe(4)
        ->and($report['totals']['active_packages'])->toBe(4)
        ->and($report['totals']['active_without_paid_closing_evidence'])->toBe(3)
        ->and($report['totals']['active_remaining_sessions'])->toBe(12)
        ->and(collect($report['packages'])->firstWhere('customer_package_id', $packageWithPaidClosing->getKey())['classification'])
        ->toBe('active_with_paid_closing')
        ->and(collect($report['packages'])->firstWhere('customer_package_id', $packageWithoutSale->getKey())['classification'])
        ->toBe('active_paid_obligation_without_sale')
        ->and(collect($report['packages'])->firstWhere('customer_package_id', $packageWithoutSale->getKey())['financial_obligation_status'])
        ->toBe('paid')
        ->and(collect($report['packages'])->firstWhere('customer_package_id', $packageWithOrphanItem->getKey())['classification'])
        ->toBe('active_item_without_sale_link')
        ->and(collect($report['packages'])->firstWhere('customer_package_id', $packageWithOrphanItem->getKey())['candidate_sale_ids'])
        ->toBe([$orphanSale->getKey()])
        ->and(collect($report['packages'])->firstWhere('customer_package_id', $packageWithOrphanItem->getKey())['candidate_sale_item_ids'])
        ->toBe([$orphanItem->getKey()])
        ->and(collect($report['packages'])->firstWhere('customer_package_id', $packageWithWrongSaleLink->getKey())['classification'])
        ->toBe('active_item_on_different_sale')
        ->and(collect($report['packages'])->firstWhere('customer_package_id', $packageWithWrongSaleLink->getKey())['candidate_sale_ids'])
        ->toBe([$otherCandidateSale->getKey()])
        ->and(collect($report['packages'])->firstWhere('customer_package_id', $packageWithWrongSaleLink->getKey())['candidate_sale_item_ids'])
        ->toBe([$otherCandidateItem->getKey()])
        ->and(CustomerPackage::query()->count())->toBe($packageCountBefore)
        ->and(FinancialObligation::query()->count())->toBe($obligationCountBefore)
        ->and(Sale::query()->count())->toBe($saleCountBefore);
});

it('supports csv output and rejects unknown report formats', function (): void {
    $this->artisan('packages:audit-legacy', ['--format' => 'csv'])
        ->expectsOutputToContain('customer_package_id')
        ->assertSuccessful();

    $this->artisan('packages:audit-legacy', ['--format' => 'xml'])
        ->expectsOutputToContain('Formato inválido')
        ->assertFailed();
});
