<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageTemplate;
use App\Models\PackageUsage;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Schema;
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

it('includes packages sold to the customer in a fresh profile response', function () {
    [$owner, $tenant, $unit] = customerHistoryTestWorkspace();

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Edson Larosa',
    ]);
    $otherCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Pacote Edson',
    ]);

    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'name_snapshot' => 'Pacote Edson',
        'total_sessions' => 5,
        'remaining_sessions' => 5,
        'status' => 'active',
    ]);
    CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $otherCustomer->getKey(),
    ]);

    $this->actingAs($owner)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/show')
            ->where('customer.id', $customer->getKey())
            ->has('customer.customer_packages', 1)
            ->where('customer.customer_packages.0.id', $package->getKey())
            ->where('customer.customer_packages.0.package_template.name', 'Pacote Edson')
            ->where('customer.customer_packages.0.remaining_sessions', 5)
        );
});

it('keeps the customer package history available before the finance link migration is applied', function () {
    [$owner, $tenant, $unit] = customerHistoryTestWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'status' => 'active',
    ]);

    Schema::shouldReceive('hasColumn')
        ->once()
        ->with('financial_obligations', 'customer_package_id')
        ->andReturnFalse();

    $this->actingAs($owner)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/show')
            ->where('customer.customer_packages.0.id', $package->getKey())
            ->where('customer.customer_packages.0.status', 'active')
        );
});

it('reports service balance drift using the complete ledger regardless of history filters', function () {
    [$owner, $tenant, $unit] = customerHistoryTestWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $firstService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte',
    ]);
    $secondService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barba',
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'total_sessions' => 4,
        'remaining_sessions' => 2,
        'eligible_services_snapshot' => [
            ['id' => $firstService->getKey(), 'name' => 'Corte', 'quantity' => 2],
            ['id' => $secondService->getKey(), 'name' => 'Barba', 'quantity' => 2],
        ],
        'status' => 'active',
    ]);

    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $firstService->getKey(),
        'allocated_quantity' => 2,
        'remaining_quantity' => 2,
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $secondService->getKey(),
        'allocated_quantity' => 2,
        'remaining_quantity' => 0,
    ]);
    PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $firstService->getKey(),
        'sessions_consumed' => 2,
        'user_id' => $owner->getKey(),
    ]);

    $this->actingAs($owner)
        ->get(route('customer-packages.history', [
            'customer_package' => $package,
            'from' => now()->addDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer-packages/history')
            ->where('totals.records', 0)
            ->where('customerPackage.balance_integrity.status', 'review')
            ->where('customerPackage.balance_integrity.services.0.name', 'Barba')
            ->where('customerPackage.balance_integrity.services.0.expected_remaining', 2)
            ->where('customerPackage.balance_integrity.services.0.recorded_remaining', 0)
            ->where('customerPackage.balance_integrity.services.1.name', 'Corte')
            ->where('customerPackage.balance_integrity.services.1.expected_remaining', 0)
            ->where('customerPackage.balance_integrity.services.1.recorded_remaining', 2)
        );
});
