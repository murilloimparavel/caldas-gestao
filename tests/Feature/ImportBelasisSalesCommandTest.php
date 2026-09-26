<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Str;

/** @return array{0:string,1:string,2:SaleCategory} */
function salesImportDestination(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Sales import '.Str::random(8),
        'slug' => 'sales-import-'.Str::lower(Str::random(8)),
    ]);
    $unitId = $tenant->units()->firstOrFail()->getKey();
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unitId,
        'type' => 'mixed',
        'is_active' => true,
    ]);

    return [$tenant->getKey(), $unitId, $category];
}

/** @param list<array<string,mixed>> $records */
function salesImportFile(array $records): string
{
    $file = tempnam(sys_get_temp_dir(), 'sales-');
    file_put_contents($file, json_encode([
        'source' => 'sanitized-test-fixture',
        'entity' => 'sale',
        'records' => $records,
    ], JSON_THROW_ON_ERROR));

    return $file;
}

/** @return array{0:Customer,1:Service,2:Product,3:Professional} */
function salesImportFixtures(string $tenantId, string $unitId): array
{
    return [
        Customer::factory()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'source_id' => 'client-synthetic-1',
        ]),
        Service::factory()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'source_id' => 'catalog-service-1',
        ]),
        Product::factory()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'source_id' => 'catalog-product-1',
        ]),
        Professional::factory()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'source_id' => 'professional-synthetic-1',
        ]),
    ];
}

/** @param array<string,mixed> $overrides */
function belasisSanitizedSale(array $overrides = []): array
{
    return array_replace_recursive([
        'source_id' => 'sale-synthetic-1',
        'code' => 'code-synthetic-1',
        'date' => '2024-01-02',
        'finished' => true,
        'total_cents' => 4500,
        'discount_cents' => 500,
        'client_source_id' => 'client-synthetic-1',
        'items' => [[
            'source_id' => 'item-service-1',
            'quantity' => 1,
            'value_cents' => 5000,
            'sum_cents' => 5000,
            'product_source_id' => 'catalog-service-1',
            'product_name' => 'Sanitized service',
            'is_service' => true,
            'professional_source_id' => 'professional-synthetic-1',
            'discount_cents' => 0,
            'discount_type' => 'value',
        ]],
        'payments' => [[
            'source_id' => 'payment-synthetic-1',
            'date' => '2024-01-02',
            'due_date' => '2024-01-02',
            'value_cents' => 4500,
            'status' => '3',
            'payment' => [],
        ]],
        'professional_source_ids' => ['professional-synthetic-1'],
        'detail_status' => 'complete',
        'detail_error' => null,
        'detail_updated_at' => '2024-01-02T12:00:00-03:00',
    ], $overrides);
}

it('previews the real sanitized structure without writing', function (): void {
    [$tenantId, $unitId, $category] = salesImportDestination();
    salesImportFixtures($tenantId, $unitId);
    $file = salesImportFile([belasisSanitizedSale()]);

    $this->artisan('app:import-belasis-sales', [
        '--tenant-id' => $tenantId,
        '--unit-id' => $unitId,
        '--file' => $file,
        '--sale-category-id' => $category->getKey(),
        '--dry-run' => true,
    ])->assertSuccessful()->expectsOutputToContain('1 found');

    expect(Sale::query()->where('source_id', 'sale-synthetic-1')->exists())->toBeFalse()
        ->and(SaleItem::query()->where('source_id', 'item-service-1')->exists())->toBeFalse();
    unlink($file);
});

it('imports real-shape sales with source-id crosswalks and payment scope', function (): void {
    [$tenantId, $unitId, $category] = salesImportDestination();
    [$customer, $service, , $professional] = salesImportFixtures($tenantId, $unitId);
    $file = salesImportFile([belasisSanitizedSale()]);
    $report = tempnam(sys_get_temp_dir(), 'sales-report-');

    $this->artisan('app:import-belasis-sales', [
        '--tenant-id' => $tenantId,
        '--unit-id' => $unitId,
        '--file' => $file,
        '--sale-category-id' => $category->getKey(),
        '--report' => $report,
    ])->assertSuccessful()->expectsOutputToContain('1 payments pending scope');

    $sale = Sale::query()->where('source_id', 'sale-synthetic-1')->firstOrFail();
    $item = SaleItem::query()->where('source_id', 'item-service-1')->firstOrFail();
    expect($sale->customer_id)->toBe($customer->getKey())
        ->and($sale->sale_category_id)->toBe($category->getKey())
        ->and($sale->status)->toBe('finalized')
        ->and($sale->total_amount_cents)->toBe(5000)
        ->and($sale->discount_amount_cents)->toBe(500)
        ->and($sale->final_amount_cents)->toBe(4500)
        ->and($sale->created_at->toDateString())->toBe('2024-01-02')
        ->and($sale->source_metadata['payment_scope']['status'])->toBe('pending_unsupported_contract')
        ->and($sale->source_metadata['payment_scope']['records'][0]['source_id'])->toBe('payment-synthetic-1')
        ->and($item->service_id)->toBe($service->getKey())
        ->and($item->professional_id)->toBe($professional->getKey())
        ->and((string) file_get_contents($report))->not->toContain('payment-synthetic-1');
    unlink($file);
    unlink($report);
});

it('updates a sale and synchronizes items by source id on rerun', function (): void {
    [$tenantId, $unitId, $category] = salesImportDestination();
    salesImportFixtures($tenantId, $unitId);
    $file = salesImportFile([belasisSanitizedSale([
        'items' => [
            [
                'source_id' => 'item-service-1',
                'quantity' => 1,
                'value_cents' => 5000,
                'sum_cents' => 5000,
                'product_source_id' => 'catalog-service-1',
                'product_name' => 'Sanitized service',
                'is_service' => true,
                'professional_source_id' => 'professional-synthetic-1',
                'discount_cents' => 0,
            ],
            [
                'source_id' => 'item-product-1',
                'quantity' => 1,
                'value_cents' => 2000,
                'sum_cents' => 2000,
                'product_source_id' => 'catalog-product-1',
                'product_name' => 'Sanitized product',
                'is_service' => false,
                'professional_source_id' => null,
                'discount_cents' => 0,
            ],
        ],
        'total_cents' => 6500,
        'discount_cents' => 500,
    ])]);
    $arguments = [
        '--tenant-id' => $tenantId,
        '--unit-id' => $unitId,
        '--file' => $file,
        '--sale-category-id' => $category->getKey(),
    ];

    $this->artisan('app:import-belasis-sales', $arguments)->assertSuccessful();
    file_put_contents($file, json_encode([
        'source' => 'sanitized-test-fixture',
        'entity' => 'sale',
        'records' => [belasisSanitizedSale([
            'date' => '2024-01-03',
            'finished' => false,
            'total_cents' => 5000,
            'discount_cents' => 400,
            'detail_updated_at' => '2024-01-03T14:00:00-03:00',
            'items' => [[
                'source_id' => 'item-service-1',
                'quantity' => 1,
                'value_cents' => 6000,
                'sum_cents' => 5400,
                'product_source_id' => 'catalog-service-1',
                'product_name' => 'Sanitized service updated',
                'is_service' => true,
                'professional_source_id' => 'professional-synthetic-1',
                'discount_cents' => 600,
            ]],
            'payments' => [],
        ])],
    ], JSON_THROW_ON_ERROR));

    $this->artisan('app:import-belasis-sales', $arguments)->assertSuccessful()->expectsOutputToContain('1 updated');

    $sale = Sale::query()->where('source_id', 'sale-synthetic-1')->firstOrFail();
    $staleItem = SaleItem::withTrashed()->where('source_id', 'item-product-1')->firstOrFail();
    $item = SaleItem::query()->where('source_id', 'item-service-1')->firstOrFail();
    expect(Sale::query()->where('source_id', 'sale-synthetic-1')->count())->toBe(1)
        ->and(SaleItem::query()->where('sale_id', $sale->getKey())->count())->toBe(1)
        ->and($sale->status)->toBe('open')
        ->and($sale->total_amount_cents)->toBe(5400)
        ->and($sale->discount_amount_cents)->toBe(400)
        ->and($sale->final_amount_cents)->toBe(5000)
        ->and($sale->created_at->toDateString())->toBe('2024-01-03')
        ->and($item->quantity)->toBe(1)
        ->and($item->unit_price_cents)->toBe(6000)
        ->and($item->total_cents)->toBe(5400)
        ->and($item->name_snapshot)->toBe('Sanitized service updated')
        ->and($staleItem->deleted_at)->not->toBeNull();
    unlink($file);
});

it('keeps sales with missing source-id relations pending', function (): void {
    [$tenantId, $unitId, $category] = salesImportDestination();
    salesImportFixtures($tenantId, $unitId);
    $file = salesImportFile([belasisSanitizedSale(['client_source_id' => 'missing-client-synthetic'])]);
    $report = tempnam(sys_get_temp_dir(), 'sales-report-');

    $this->artisan('app:import-belasis-sales', [
        '--tenant-id' => $tenantId,
        '--unit-id' => $unitId,
        '--file' => $file,
        '--sale-category-id' => $category->getKey(),
        '--report' => $report,
    ])->assertSuccessful()->expectsOutputToContain('1 pending');

    expect(Sale::query()->where('source_id', 'sale-synthetic-1')->exists())->toBeFalse()
        ->and((string) file_get_contents($report))->not->toContain('missing-client-synthetic');
    unlink($file);
    unlink($report);
});

it('fails before processing an invalid file structure', function (): void {
    [$tenantId, $unitId, $category] = salesImportDestination();
    $file = tempnam(sys_get_temp_dir(), 'invalid-sales-');
    file_put_contents($file, '{"records":{"not":"a list"}}');

    $this->artisan('app:import-belasis-sales', [
        '--tenant-id' => $tenantId,
        '--unit-id' => $unitId,
        '--file' => $file,
        '--sale-category-id' => $category->getKey(),
        '--dry-run' => true,
    ])->assertFailed();

    expect(Sale::query()->where('tenant_id', $tenantId)->count())->toBe(0);
    unlink($file);
});
