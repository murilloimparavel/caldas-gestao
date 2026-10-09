<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\CommissionAccrual;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

it('reports imported finalized sales missing accruals without changing commission data', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Commission reconciliation '.Str::random(8),
        'slug' => 'commission-reconciliation-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'source_id' => 'legacy-sale-1',
        'status' => 'finalized',
        'total_amount_cents' => 5000,
        'discount_amount_cents' => 500,
        'final_amount_cents' => 4500,
        'source_metadata' => ['payment_scope' => ['status' => 'pending_unsupported_contract']],
    ]);
    $activeItem = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'total_cents' => 5000,
    ]);
    $deletedItem = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'total_cents' => 9000,
    ]);
    $deletedItem->delete();

    $saleWithAccrual = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'source_id' => 'legacy-sale-with-accrual',
        'status' => 'finalized',
    ]);
    $accrualItem = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $saleWithAccrual->getKey(),
    ]);
    CommissionAccrual::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $saleWithAccrual->getKey(),
        'sale_item_id' => $accrualItem->getKey(),
    ]);

    $accrualCount = CommissionAccrual::query()->count();

    Artisan::call('commissions:reconcile-imported-sales', [
        '--format' => 'json',
        '--tenant' => $tenant->getKey(),
        '--unit' => $unit->getKey(),
    ]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['read_only'])->toBeTrue()
        ->and($report['payment_import_scope_supported'])->toBeFalse()
        ->and($report['warning'])->toContain('pagamentos')
        ->and($report['warning'])->toContain('não é suportado')
        ->and($report['totals']['imported_finalized_sales_without_commission_accruals'])->toBe(1)
        ->and($report['totals']['sale_total_mismatches'])->toBe(0)
        ->and($report['sales'][0]['source_id'])->toBe('legacy-sale-1')
        ->and($report['sales'][0]['active_items_total_cents'])->toBe(5000)
        ->and($report['sales'][0]['sale_total_mismatch'])->toBeFalse()
        ->and($report['sales'][0]['payment_scope_status'])->toBe('pending_unsupported_contract')
        ->and(CommissionAccrual::query()->count())->toBe($accrualCount);
});

it('flags mismatched imported sale totals and applies the unit filter', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Commission mismatch '.Str::random(8),
        'slug' => 'commission-mismatch-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $otherUnit = Unit::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Other unit',
        'slug' => 'other-unit-'.Str::lower(Str::random(8)),
    ]);
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
    ]);
    $otherCategory = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'type' => 'mixed',
    ]);

    $mismatchedSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'source_id' => 'legacy-sale-mismatch',
        'status' => 'finalized',
        'total_amount_cents' => 5000,
        'discount_amount_cents' => 9000,
        'final_amount_cents' => 100,
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $mismatchedSale->getKey(),
        'total_cents' => 5000,
    ]);

    $otherSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'sale_category_id' => $otherCategory->getKey(),
        'source_id' => 'legacy-sale-other-unit',
        'status' => 'finalized',
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'sale_id' => $otherSale->getKey(),
    ]);

    $exitCode = Artisan::call('commissions:reconcile-imported-sales', [
        '--format' => 'json',
        '--tenant' => $tenant->getKey(),
        '--unit' => $unit->getKey(),
    ]);
    expect($exitCode)->toBe(0);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['totals']['imported_finalized_sales_without_commission_accruals'])->toBe(1)
        ->and($report['totals']['sale_total_mismatches'])->toBe(1)
        ->and($report['sales'][0]['source_id'])->toBe('legacy-sale-mismatch')
        ->and($report['sales'][0]['expected_final_amount_cents'])->toBe(0)
        ->and($report['sales'][0]['total_amount_mismatch'])->toBeFalse()
        ->and($report['sales'][0]['final_amount_mismatch'])->toBeTrue()
        ->and($report['sales'][0]['sale_total_mismatch'])->toBeTrue();
});
