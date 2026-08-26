<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function customerHistoryTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace Cust History '.Str::random(8),
        'slug' => 'workspace-cust-history-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('loads customer sales history with items, category and calculates accumulated total spent and visits', function () {
    [$owner, $tenant, $unit] = customerHistoryTestWorkspace();

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Mariana Silveira',
    ]);

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Atendimento Geral',
    ]);

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Dra. Camila',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte Feminino',
    ]);

    // Create 2 appointments (1 completed, 1 confirmed)
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $professional->getKey(),
        'status' => 'completed',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDays(10)->addMinutes(45),
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $professional->getKey(),
        'status' => 'confirmed',
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addMinutes(45),
    ]);

    // Create 2 finalized sales with items and 1 draft sale
    $finalizedSale1 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'reference_label' => 'Comanda #001',
        'status' => 'finalized',
        'total_amount_cents' => 15000,
        'discount_amount_cents' => 1000,
        'final_amount_cents' => 14000,
        'created_at' => now()->subDays(10),
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $finalizedSale1->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'name_snapshot' => 'Corte Feminino',
        'unit_price_cents' => 15000,
        'quantity' => 1,
        'total_cents' => 15000,
    ]);

    $finalizedSale2 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'reference_label' => 'Comanda #002',
        'status' => 'finalized',
        'total_amount_cents' => 20000,
        'discount_amount_cents' => 0,
        'final_amount_cents' => 20000,
        'created_at' => now()->subDays(5),
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $finalizedSale2->getKey(),
        'item_type' => 'custom',
        'name_snapshot' => 'Tratamento Especial',
        'unit_price_cents' => 20000,
        'quantity' => 1,
        'total_cents' => 20000,
    ]);

    // Draft sale should not count towards accumulated total spent
    Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'reference_label' => 'Comanda #003',
        'status' => 'draft',
        'total_amount_cents' => 5000,
        'discount_amount_cents' => 0,
        'final_amount_cents' => 5000,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get(route('customers.show', $customer));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('customers/show')
        ->where('customer.id', $customer->getKey())
        ->has('customer.sales', 3)
        ->has('customer.appointments', 2)
        ->where('metrics.total_spent_cents', 34000)
        ->where('metrics.total_visits', 2)
        ->where('customer.sales.0.reference_label', 'Comanda #003')
        ->where('customer.sales.1.reference_label', 'Comanda #002')
        ->where('customer.sales.1.items.0.name_snapshot', 'Tratamento Especial')
        ->where('customer.sales.2.reference_label', 'Comanda #001')
        ->where('customer.sales.2.items.0.name_snapshot', 'Corte Feminino')
    );
});
