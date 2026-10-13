<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Category;
use App\Models\ClosingSession;
use App\Models\ClosingSessionPayment;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\FinancialObligation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function financialTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('allows creating a payable obligation with supplier and category', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();

    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Energia & Água',
    ]);

    $supplier = Supplier::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Companhia Elétrica',
    ]);

    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.store'), [
            'type' => 'payable',
            'description' => 'Conta de Luz do Mês',
            'amount_cents' => 45000,
            'due_date' => now()->addDays(5)->toDateString(),
            'category_id' => $category->getKey(),
            'supplier_id' => $supplier->getKey(),
            'notes' => 'Vencimento no 5º dia útil',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('financial_obligations', [
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'payable',
        'description' => 'Conta de Luz do Mês',
        'amount_cents' => 45000,
        'status' => 'pending',
        'category_id' => $category->getKey(),
        'supplier_id' => $supplier->getKey(),
        'lock_version' => 1,
    ]);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->getKey(),
        'action' => 'financial_obligation.created',
    ]);
});

it('allows creating a receivable obligation with customer', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Cliente VIP',
    ]);

    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.store'), [
            'type' => 'receivable',
            'description' => 'Pacote Mensal Noiva',
            'amount_cents' => 120000,
            'due_date' => now()->addDays(10)->toDateString(),
            'customer_id' => $customer->getKey(),
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('financial_obligations', [
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'receivable',
        'description' => 'Pacote Mensal Noiva',
        'amount_cents' => 120000,
        'status' => 'pending',
        'customer_id' => $customer->getKey(),
        'lock_version' => 1,
    ]);
});

it('validates financial obligation inputs', function () {
    [$owner] = financialTestWorkspace();

    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.store'), [
            'type' => 'invalid_type',
            'description' => '',
            'amount_cents' => -100,
            'due_date' => 'invalid_date',
        ]);

    $response->assertSessionHasErrors(['type', 'description', 'amount_cents', 'due_date']);
});

it('updates a pending financial obligation and increments lock version', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();

    $obligation = FinancialObligation::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'payable',
        'description' => 'Internet Fibra',
        'amount_cents' => 15000,
        'due_date' => now()->addDays(3)->toDateString(),
        'status' => 'pending',
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)
        ->put(route('financial_obligations.update', $obligation), [
            'type' => 'payable',
            'description' => 'Internet Fibra Dedicada 500MB',
            'amount_cents' => 18000,
            'due_date' => now()->addDays(4)->toDateString(),
            'lock_version' => 1,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $obligation->refresh();
    expect($obligation->description)->toBe('Internet Fibra Dedicada 500MB')
        ->and($obligation->amount_cents)->toBe(18000)
        ->and($obligation->lock_version)->toBe(2);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->getKey(),
        'action' => 'financial_obligation.updated',
    ]);
});

it('rejects updates with stale lock version (concurrency conflict)', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();

    $obligation = FinancialObligation::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'payable',
        'description' => 'Fornecedor de Esmaltes',
        'amount_cents' => 20000,
        'due_date' => now()->addDays(2)->toDateString(),
        'status' => 'pending',
        'lock_version' => 2,
    ]);

    $response = $this->actingAs($owner)
        ->put(route('financial_obligations.update', $obligation), [
            'type' => 'payable',
            'description' => 'Fornecedor de Esmaltes Atualizado',
            'amount_cents' => 25000,
            'due_date' => now()->addDays(2)->toDateString(),
            'lock_version' => 1, // Stale version
        ]);

    $response->assertStatus(409);
});

it('settles a financial obligation marking it as paid', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();

    $obligation = FinancialObligation::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'payable',
        'description' => 'Aluguel do Espaço',
        'amount_cents' => 350000,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.settle', $obligation), [
            'paid_date' => now()->toDateString(),
            'payment_method' => 'pix',
            'lock_version' => 1,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $obligation->refresh();
    expect($obligation->status)->toBe('paid')
        ->and($obligation->paid_date->toDateString())->toBe(now()->toDateString())
        ->and($obligation->payment_method)->toBe('pix')
        ->and($obligation->lock_version)->toBe(2);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->getKey(),
        'action' => 'financial_obligation.settled',
    ]);
});

it('cannot settle an already settled financial obligation', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();

    $obligation = FinancialObligation::factory()->paid()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.settle', $obligation), [
            'paid_date' => now()->toDateString(),
            'payment_method' => 'pix',
            'lock_version' => 1,
        ]);

    $response->assertSessionHasErrors(['status']);
});

it('cancels a pending financial obligation', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();

    $obligation = FinancialObligation::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'pending',
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.cancel', $obligation), [
            'lock_version' => 1,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $obligation->refresh();
    expect($obligation->status)->toBe('cancelled')
        ->and($obligation->lock_version)->toBe(2);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->getKey(),
        'action' => 'financial_obligation.cancelled',
    ]);
});

it('cannot cancel a paid financial obligation', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();

    $obligation = FinancialObligation::factory()->paid()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.cancel', $obligation), [
            'lock_version' => 1,
        ]);

    $response->assertSessionHasErrors(['status']);
});

it('strictly isolates financial obligations between tenants', function () {
    [$ownerA, $tenantA, $unitA] = financialTestWorkspace();
    [$ownerB, $tenantB, $unitB] = financialTestWorkspace();

    $obligationB = FinancialObligation::factory()->create([
        'tenant_id' => $tenantB->getKey(),
        'unit_id' => $unitB->getKey(),
        'description' => 'Segredo Tenant B',
        'status' => 'pending',
        'lock_version' => 1,
    ]);

    // Owner A cannot view/update/settle Tenant B's obligation
    $response = $this->actingAs($ownerA)
        ->get(route('financial_obligations.show', $obligationB));

    $response->assertStatus(403);

    $settleResponse = $this->actingAs($ownerA)
        ->post(route('financial_obligations.settle', $obligationB), [
            'paid_date' => now()->toDateString(),
            'payment_method' => 'pix',
            'lock_version' => 1,
        ]);

    $settleResponse->assertStatus(403);
});

it('renders the finance dashboard with consolidated metrics', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();

    // Create a payable due today
    FinancialObligation::factory()->payable()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'amount_cents' => 5000,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    // Create a receivable due today
    FinancialObligation::factory()->receivable()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'amount_cents' => 15000,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    // Create an overdue payable
    FinancialObligation::factory()->payable()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'amount_cents' => 8000,
        'due_date' => now()->subDays(3)->toDateString(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($owner)
        ->get(route('finance.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('finance/dashboard')
        ->has('metrics')
        ->where('metrics.payable_today_cents', 5000)
        ->where('metrics.receivable_today_cents', 15000)
        ->where('metrics.overdue_count', 1)
        ->where('metrics.overdue_amount_cents', 8000)
    );
});

it('deduplicates a package receivable only while its original closing payment is not reversed', function (): void {
    [$owner, $tenant, $unit] = financialTestWorkspace();
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'price_cents_snapshot' => 30000,
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $package->customer_id,
        'status' => 'finalized',
        'final_amount_cents' => 30000,
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'package',
        'customer_package_id' => $package->getKey(),
        'package_template_id' => $package->package_template_id,
        'unit_price_cents' => 30000,
        'total_cents' => 30000,
    ]);
    $session = ClosingSession::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'completed',
        'final_total_cents' => 30000,
        'closed_by_user_id' => $owner->getKey(),
    ]);
    $session->sales()->attach($sale->getKey());
    $payment = ClosingSessionPayment::create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'closing_session_id' => $session->getKey(),
        'payment_method' => 'pix',
        'amount_cents' => 30000,
        'tendered_cents' => null,
        'change_cents' => 0,
        'recorded_by_user_id' => $owner->getKey(),
        'recorded_at' => now(),
        'reversal_of_id' => null,
        'is_reversal' => false,
        'reversal_reason' => null,
    ]);
    $obligation = FinancialObligation::factory()->receivable()->paid()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $package->customer_id,
        'customer_package_id' => $package->getKey(),
        'amount_cents' => 30000,
        'paid_date' => now()->toDateString(),
    ]);

    $this->actingAs($owner)
        ->get(route('finance.dashboard', ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'source' => 'all']))
        ->assertInertia(fn ($page) => $page
            ->where('metrics.filtered_received_closings_cents', 30000)
            ->where('metrics.filtered_received_obligations_cents', 0)
            ->where('metrics.filtered_received_cents', 30000)
        );

    ClosingSessionPayment::create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'closing_session_id' => $session->getKey(),
        'payment_method' => 'pix',
        'amount_cents' => 30000,
        'tendered_cents' => null,
        'change_cents' => 0,
        'recorded_by_user_id' => $owner->getKey(),
        'recorded_at' => now(),
        'reversal_of_id' => $payment->getKey(),
        'is_reversal' => true,
        'reversal_reason' => 'Estorno de teste',
    ]);
    $this->actingAs($owner)
        ->get(route('finance.dashboard', ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'source' => 'all']))
        ->assertInertia(fn ($page) => $page
            ->where('metrics.filtered_received_closings_cents', 0)
            ->where('metrics.filtered_received_obligations_cents', 30000)
            ->where('metrics.filtered_received_cents', 30000)
        );

    $paidStatus = $this->actingAs($owner)->get(route('finance.dashboard', [
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
        'source' => 'closings',
        'status' => 'paid',
    ]));
    $paidStatus->assertInertia(fn ($page) => $page
        ->where('metrics.filtered_received_closings_cents', 0)
        ->where('metrics.filtered_received_cents', 0)
        ->where('movements', fn ($movements) => collect($movements)->isEmpty())
    );

    $reversedStatus = $this->actingAs($owner)->get(route('finance.dashboard', [
        'from' => now()->toDateString(),
        'to' => now()->toDateString(),
        'source' => 'closings',
        'status' => 'reversed',
    ]));
    $reversedStatus->assertInertia(fn ($page) => $page
        ->where('metrics.filtered_received_closings_cents', -30000)
        ->where('metrics.filtered_received_cents', -30000)
        ->where('movements', fn ($movements) => collect($movements)->contains(fn ($movement) => $movement['status'] === 'reversed'
            && $movement['signed_amount_cents'] === -30000))
    );
});

it('includes closed sale payments in filtered receipts without changing independent obligations', function () {
    [$owner, $tenant, $unit] = financialTestWorkspace();
    $session = ClosingSession::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'closed_by_user_id' => $owner->getKey(),
        'status' => 'completed',
        'receipt_number' => 'FIN-TEST-1',
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'finalized',
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'custom',
        'service_id' => null,
        'name_snapshot' => 'Pacote teste',
        'total_cents' => 50000,
    ]);
    $session->sales()->attach($sale->getKey());

    $payment = ClosingSessionPayment::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'closing_session_id' => $session->getKey(),
        'payment_method' => 'pix',
        'amount_cents' => 50000,
        'recorded_by_user_id' => $owner->getKey(),
        'recorded_at' => now(),
        'is_reversal' => false,
    ]);

    FinancialObligation::factory()->receivable()->paid()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'amount_cents' => 20000,
        'paid_date' => now()->toDateString(),
        'payment_method' => 'pix',
    ]);
    FinancialObligation::factory()->payable()->paid()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'amount_cents' => 7000,
        'paid_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);

    $response = $this->actingAs($owner)->get(route('finance.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('metrics.filtered_received_cents', 70000)
        ->where('metrics.filtered_received_closings_cents', 50000)
        ->where('metrics.filtered_received_obligations_cents', 20000)
        ->where('metrics.filtered_paid_cents', 7000)
        ->where('movements', fn ($movements) => collect($movements)->contains(fn ($movement) => $movement['source'] === 'closing' && $movement['payment_method'] === 'pix'))
    );

    $closingOnly = $this->actingAs($owner)->get(route('finance.dashboard', ['source' => 'closings']));
    $closingOnly->assertInertia(fn ($page) => $page
        ->where('metrics.filtered_received_cents', 50000)
        ->where('metrics.filtered_received_obligations_cents', 0)
        ->where('metrics.filtered_paid_cents', 0)
    );

    ClosingSessionPayment::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'closing_session_id' => $session->getKey(),
        'payment_method' => 'pix',
        'amount_cents' => $payment->amount_cents,
        'recorded_by_user_id' => $owner->getKey(),
        'recorded_at' => now(),
        'reversal_of_id' => $payment->getKey(),
        'is_reversal' => true,
        'reversal_reason' => 'Teste de estorno',
    ]);

    $afterReversal = $this->actingAs($owner)->get(route('finance.dashboard', ['source' => 'closings']));
    $afterReversal->assertInertia(fn ($page) => $page
        ->where('metrics.filtered_received_cents', 0)
        ->where('metrics.filtered_received_closings_cents', 0)
    );
});
