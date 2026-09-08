<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
});

/** @return array{0: User, 1: Tenant, 2: Unit} */
function catalogImageWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit];
}

it('uploads image successfully when creating a service and a product', function () {
    [$owner, $tenant] = catalogImageWorkspace();

    $serviceFile = UploadedFile::fake()->image('service.png', 400, 400);
    $responseService = $this->actingAs($owner)->post(route('services.store'), [
        'name' => 'Corte Masculino com Foto',
        'duration_minutes' => 30,
        'price_cents' => 5000,
        'image' => $serviceFile,
    ]);
    $responseService->assertRedirect();

    $service = Service::query()->where('name', 'Corte Masculino com Foto')->firstOrFail();
    expect($service->image_path)->not()->toBeNull()
        ->and($service->image_path)->toBe("{$tenant->getKey()}/services/{$service->getKey()}/".basename($service->image_path))
        ->and($service->image_path)->toEndWith('.webp')
        ->and($service->image_url)->toBe(Storage::disk('public')->url($service->image_path));
    Storage::disk('public')->assertExists($service->image_path);

    $productFile = UploadedFile::fake()->image('product.jpg', 600, 600);
    $responseProduct = $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Pomada Modeladora',
        'cost_price_cents' => 1500,
        'sale_price_cents' => 3500,
        'unit_of_measure' => 'un',
        'min_stock' => 5,
        'current_stock' => 20,
        'image' => $productFile,
    ]);
    $responseProduct->assertRedirect();

    $product = Product::query()->where('name', 'Pomada Modeladora')->firstOrFail();
    expect($product->image_path)->not()->toBeNull()
        ->and($product->image_path)->toContain("{$tenant->getKey()}/products/{$product->getKey()}/")
        ->and($product->image_url)->toBe(Storage::disk('public')->url($product->image_path));
    Storage::disk('public')->assertExists($product->image_path);
});

it('stores service images on the configured media disk', function () {
    config(['filesystems.media_disk' => 's3']);
    Storage::fake('s3');
    [$owner, $tenant] = catalogImageWorkspace();

    $this->actingAs($owner)->post(route('services.store'), [
        'name' => 'Serviço no MinIO',
        'duration_minutes' => 30,
        'price_cents' => 5000,
        'image' => UploadedFile::fake()->image('service.png', 400, 400),
    ])->assertRedirect();

    $service = Service::query()->where('name', 'Serviço no MinIO')->firstOrFail();
    expect($service->image_path)->toEndWith('.webp')
        ->and($service->image_url)->toBe(Storage::disk('s3')->url($service->image_path));
    Storage::disk('s3')->assertExists($service->image_path);
});

it('replaces photo on update and deletes old photo from storage', function () {
    [$owner, $tenant] = catalogImageWorkspace();

    $oldServiceFile = UploadedFile::fake()->image('old_service.jpg');
    $this->actingAs($owner)->post(route('services.store'), [
        'name' => 'Serviço Foto Antiga',
        'duration_minutes' => 45,
        'price_cents' => 6000,
        'image' => $oldServiceFile,
    ]);
    $service = Service::query()->where('name', 'Serviço Foto Antiga')->firstOrFail();
    $oldServicePath = $service->image_path;
    Storage::disk('public')->assertExists($oldServicePath);

    $newServiceFile = UploadedFile::fake()->image('new_service.webp');
    $this->actingAs($owner)->put(route('services.update', $service), [
        'name' => 'Serviço Foto Nova',
        'duration_minutes' => 45,
        'price_cents' => 6000,
        'lock_version' => $service->lock_version,
        'image' => $newServiceFile,
    ]);

    $service->refresh();
    expect($service->image_path)->not()->toBe($oldServicePath);
    Storage::disk('public')->assertMissing($oldServicePath);
    Storage::disk('public')->assertExists($service->image_path);

    $oldProductFile = UploadedFile::fake()->image('old_product.png');
    $this->actingAs($owner)->post(route('products.store'), [
        'name' => 'Produto Foto Antiga',
        'cost_price_cents' => 1000,
        'sale_price_cents' => 2000,
        'unit_of_measure' => 'un',
        'min_stock' => 2,
        'current_stock' => 10,
        'image' => $oldProductFile,
    ]);
    $product = Product::query()->where('name', 'Produto Foto Antiga')->firstOrFail();
    $oldProductPath = $product->image_path;
    Storage::disk('public')->assertExists($oldProductPath);

    $newProductFile = UploadedFile::fake()->image('new_product.png');
    $this->actingAs($owner)->put(route('products.update', $product), [
        'name' => 'Produto Foto Nova',
        'cost_price_cents' => 1000,
        'sale_price_cents' => 2000,
        'unit_of_measure' => 'un',
        'min_stock' => 2,
        'current_stock' => 10,
        'lock_version' => $product->lock_version,
        'image' => $newProductFile,
    ]);

    $product->refresh();
    expect($product->image_path)->not()->toBe($oldProductPath);
    Storage::disk('public')->assertMissing($oldProductPath);
    Storage::disk('public')->assertExists($product->image_path);
});

it('rejects files with invalid types or size exceeding 5MB', function () {
    [$owner] = catalogImageWorkspace();

    $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
    $this->actingAs($owner)
        ->post(route('services.store'), [
            'name' => 'Serviço PDF Inválido',
            'duration_minutes' => 30,
            'price_cents' => 4000,
            'image' => $pdfFile,
        ])
        ->assertSessionHasErrors(['image']);

    $largeFile = UploadedFile::fake()->image('huge.png')->size(5121);
    $this->actingAs($owner)
        ->post(route('products.store'), [
            'name' => 'Produto Imagem Grande',
            'cost_price_cents' => 1000,
            'sale_price_cents' => 2000,
            'unit_of_measure' => 'un',
            'min_stock' => 2,
            'current_stock' => 10,
            'image' => $largeFile,
        ])
        ->assertSessionHasErrors(['image']);
});
