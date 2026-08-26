<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit} */
function globalQuickCreateTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace Global Quick Create '.Str::random(8),
        'slug' => 'workspace-global-quick-create-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit];
}

it('allows quick creation of a supplier returning JSON response with id and name', function () {
    [$owner, $tenant, $unit] = globalQuickCreateTestWorkspace();

    $response = $this
        ->actingAs($owner)
        ->postJson(route('suppliers.store', [
            'tenant' => $tenant->slug,
            'unit' => $unit->slug,
        ]), [
            'name' => 'Fornecedor Rapido',
            'trade_name' => 'Fornecedor LTDA',
            'phone' => '11977776666',
        ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Fornecedor Rapido')
        ->assertJsonStructure(['id', 'name', 'supplier']);

    $this->assertDatabaseHas('suppliers', [
        'tenant_id' => $tenant->id,
        'name' => 'Fornecedor Rapido',
    ]);
});

it('allows quick creation of a product category returning JSON response with id and name', function () {
    [$owner, $tenant, $unit] = globalQuickCreateTestWorkspace();

    $response = $this
        ->actingAs($owner)
        ->postJson(route('categories.store', [
            'tenant' => $tenant->slug,
            'unit' => $unit->slug,
        ]), [
            'name' => 'Categoria Rapida',
            'type' => 'product',
        ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Categoria Rapida')
        ->assertJsonStructure(['id', 'name', 'category']);

    $this->assertDatabaseHas('categories', [
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'name' => 'Categoria Rapida',
    ]);
});

it('allows quick creation of a sale category returning JSON response with id and name', function () {
    [$owner, $tenant, $unit] = globalQuickCreateTestWorkspace();

    $response = $this
        ->actingAs($owner)
        ->postJson(route('sale-categories.store', [
            'tenant' => $tenant->slug,
            'unit' => $unit->slug,
        ]), [
            'name' => 'Categoria Comanda Rapida',
            'type' => 'mixed',
            'uniqueness_scope' => 'none',
        ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Categoria Comanda Rapida')
        ->assertJsonStructure(['id', 'name', 'category']);

    $this->assertDatabaseHas('sale_categories', [
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'name' => 'Categoria Comanda Rapida',
    ]);
});
