<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

/** @param list<array<string,mixed>> $products */
function productImportFile(array $products): string
{
    $file = tempnam(sys_get_temp_dir(), 'products-');
    file_put_contents($file, json_encode([
        'source' => 'sanitized-test-fixture',
        'entity' => 'products',
        'products' => $products,
    ], JSON_THROW_ON_ERROR));

    return $file;
}

/** @return array{0:string,1:string,2:User} */
function productImportDestination(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Product import '.Str::random(8),
        'slug' => 'product-import-'.Str::lower(Str::random(8)),
    ]);

    return [$tenant->getKey(), $tenant->units()->firstOrFail()->getKey(), $owner];
}

it('previews products without writing and requires source ids', function (): void {
    [$tenantId, $unitId] = productImportDestination();
    $file = productImportFile([
        ['source_id' => 'product-preview-1', 'name' => 'Pomada', 'description' => null, 'price_cents' => 3500, 'status' => 'active', 'category_name' => 'Geral'],
        ['id' => 'not-a-source-id', 'name' => 'Sem origem', 'price_cents' => 1000, 'status' => 'active'],
    ]);

    $this->artisan('app:import-products', [
        '--tenant-id' => $tenantId,
        '--unit-id' => $unitId,
        '--file' => $file,
    ])->assertSuccessful()->expectsOutputToContain('1 pending');

    expect(Product::query()->where('tenant_id', $tenantId)->count())->toBe(0);
    unlink($file);
});

it('writes products idempotently by tenant unit and source id', function (): void {
    [$tenantId, $unitId] = productImportDestination();
    $category = Category::factory()->create([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'name' => 'Bebidas',
        'type' => 'product',
    ]);
    $file = productImportFile([[
        'source_id' => 'product-replay-1',
        'name' => 'Cerveja',
        'description' => '350 ml',
        'price_cents' => 1000,
        'status' => 'active',
        'category_name' => 'bebidas',
    ]]);

    $arguments = ['--tenant-id' => $tenantId, '--unit-id' => $unitId, '--file' => $file, '--write' => true];
    $this->artisan('app:import-products', $arguments)->assertSuccessful()->expectsOutputToContain('1 created');

    $product = Product::query()->where('tenant_id', $tenantId)->where('source_id', 'product-replay-1')->firstOrFail();
    expect($product->category_id)->toBe($category->getKey())
        ->and($product->sale_price_cents)->toBe(1000)
        ->and($product->is_active)->toBeTrue();

    file_put_contents($file, json_encode(['products' => [[
        'source_id' => 'product-replay-1',
        'name' => 'Cerveja premium',
        'description' => null,
        'price_cents' => 1200,
        'status' => 'inactive',
        'category_name' => 'BEBIDAS',
    ]]], JSON_THROW_ON_ERROR));
    $this->artisan('app:import-products', $arguments)->assertSuccessful()->expectsOutputToContain('1 updated');

    $product->refresh();
    expect(Product::query()->where('tenant_id', $tenantId)->where('source_id', 'product-replay-1')->count())->toBe(1)
        ->and($product->name)->toBe('Cerveja premium')
        ->and($product->sale_price_cents)->toBe(1200)
        ->and($product->is_active)->toBeFalse()
        ->and($product->lock_version)->toBe(2);
    unlink($file);
});

it('does not infer an ambiguous category', function (): void {
    [$tenantId, $unitId] = productImportDestination();
    Category::factory()->count(2)->create([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'name' => 'Geral',
        'type' => 'product',
    ]);
    $file = productImportFile([[
        'source_id' => 'product-ambiguous-category',
        'name' => 'Produto sem categoria inferida',
        'price_cents' => 500,
        'status' => 'active',
        'category_name' => 'geral',
    ]]);

    $this->artisan('app:import-products', [
        '--tenant-id' => $tenantId,
        '--unit-id' => $unitId,
        '--file' => $file,
        '--write' => true,
    ])->assertSuccessful()->expectsOutputToContain('1 pending');

    expect(Product::query()->where('source_id', 'product-ambiguous-category')->value('category_id'))->toBeNull();
    unlink($file);
});
