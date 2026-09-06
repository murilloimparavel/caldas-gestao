<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit} */
function quickCreateTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace Quick Create '.Str::random(8),
        'slug' => 'workspace-quick-create-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit];
}

it('allows quick creation of a customer returning JSON response with id and name', function () {
    [$owner, $tenant, $unit] = quickCreateTestWorkspace();

    $response = $this
        ->actingAs($owner)
        ->postJson(route('customers.store', [
            'tenant' => $tenant->slug,
            'unit' => $unit->slug,
        ]), [
            'name' => 'Cliente Rapido',
            'phone' => '11999998888',
            'email' => 'rapido@example.com',
            'notes' => 'Criado via modal',
        ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Cliente Rapido')
        ->assertJsonStructure(['id', 'name', 'customer']);

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'name' => 'Cliente Rapido',
    ]);
});

it('allows quick creation of a service returning JSON response with id and name', function () {
    [$owner, $tenant, $unit] = quickCreateTestWorkspace();

    $response = $this
        ->actingAs($owner)
        ->postJson(route('services.store', [
            'tenant' => $tenant->slug,
            'unit' => $unit->slug,
        ]), [
            'name' => 'Corte Express',
            'price_cents' => 4500,
            'duration_minutes' => 30,
            'commission_rate' => 50,
        ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Corte Express')
        ->assertJsonStructure(['id', 'name', 'service']);

    $this->assertDatabaseHas('services', [
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'name' => 'Corte Express',
    ]);
});

it('allows quick creation of a professional returning JSON response with id and name', function () {
    [$owner, $tenant, $unit] = quickCreateTestWorkspace();

    $response = $this
        ->actingAs($owner)
        ->postJson(route('professionals.store', [
            'tenant' => $tenant->slug,
            'unit' => $unit->slug,
        ]), [
            'name' => 'Barbeiro Rapido',
            'phone' => '11988887777',
            'commission_rate' => 60,
        ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'Barbeiro Rapido')
        ->assertJsonStructure(['id', 'name', 'professional']);

    $this->assertDatabaseHas('professionals', [
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'name' => 'Barbeiro Rapido',
    ]);
});
