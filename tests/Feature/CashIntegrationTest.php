<?php

use App\Actions\Finance\Cash\OpenCashShift;
use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\FinancialObligation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function cashIntegrationTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace Cash Integration '.Str::random(8),
        'slug' => 'workspace-cash-integ-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('automatically creates an expense_outflow cash movement and updates expected_amount_cents when settling a payable obligation in cash with an open cash shift', function () {
    [$owner, $tenant, $unit, $context] = cashIntegrationTestWorkspace();

    // 1. Open cash shift with 500,00 (50000 cents)
    /** @var CashShift $shift */
    $shift = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 50000,
        'notes' => 'Abertura para testes',
    ]);

    expect($shift->expected_amount_cents)->toBe(50000)
        ->and($shift->lock_version)->toBe(1);

    // 2. Create a payable obligation of 120,00 (12000 cents)
    $obligation = FinancialObligation::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'payable',
        'description' => 'Compra de Material de Limpeza',
        'amount_cents' => 12000,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
        'lock_version' => 1,
    ]);

    // 3. Settle obligation with payment_method = cash
    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.settle', $obligation), [
            'paid_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'lock_version' => 1,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // 4. Verify obligation state
    $obligation->refresh();
    expect($obligation->status)->toBe('paid')
        ->and($obligation->payment_method)->toBe('cash')
        ->and($obligation->lock_version)->toBe(2);

    // 5. Verify CashMovement creation
    $movement = CashMovement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->where('cash_shift_id', $shift->getKey())
        ->where('reference_type', 'financial_obligation')
        ->where('reference_id', $obligation->getKey())
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('expense_outflow')
        ->and($movement->amount_cents)->toBe(12000)
        ->and($movement->reason)->toBe('Pagamento de despesa: Compra de Material de Limpeza')
        ->and($movement->user_id)->toBe($owner->getKey());

    // 6. Verify CashShift updated expected_amount_cents (50000 - 12000 = 38000)
    $shift->refresh();
    expect($shift->expected_amount_cents)->toBe(38000)
        ->and($shift->lock_version)->toBe(2);

    // 7. Verify AuditEvents recorded
    expect(AuditEvent::query()->where('action', 'financial_obligation.settled')->where('resource_id', $obligation->getKey())->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'cash_shift.moved')->where('resource_id', $shift->getKey())->exists())->toBeTrue();
});

it('automatically creates a supply cash movement and updates expected_amount_cents when settling a receivable obligation in cash with an open cash shift', function () {
    [$owner, $tenant, $unit, $context] = cashIntegrationTestWorkspace();

    // 1. Open cash shift with 300,00 (30000 cents)
    /** @var CashShift $shift */
    $shift = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 30000,
        'notes' => 'Abertura para recebimento',
    ]);

    // 2. Create a receivable obligation of 80,00 (8000 cents)
    $obligation = FinancialObligation::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'receivable',
        'description' => 'Recebimento de Mensalidade Antecipada',
        'amount_cents' => 8000,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
        'lock_version' => 1,
    ]);

    // 3. Settle obligation with payment_method = cash
    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.settle', $obligation), [
            'paid_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'lock_version' => 1,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // 4. Verify obligation state
    $obligation->refresh();
    expect($obligation->status)->toBe('paid')
        ->and($obligation->payment_method)->toBe('cash')
        ->and($obligation->lock_version)->toBe(2);

    // 5. Verify CashMovement creation
    $movement = CashMovement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->where('cash_shift_id', $shift->getKey())
        ->where('reference_type', 'financial_obligation')
        ->where('reference_id', $obligation->getKey())
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('supply')
        ->and($movement->amount_cents)->toBe(8000)
        ->and($movement->reason)->toBe('Recebimento de conta: Recebimento de Mensalidade Antecipada');

    // 6. Verify CashShift updated expected_amount_cents (30000 + 8000 = 38000)
    $shift->refresh();
    expect($shift->expected_amount_cents)->toBe(38000)
        ->and($shift->lock_version)->toBe(2);
});

it('does not create cash movement when settling with non-cash payment method even if a cash shift is open', function () {
    [$owner, $tenant, $unit, $context] = cashIntegrationTestWorkspace();

    $shift = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 50000,
    ]);

    $obligation = FinancialObligation::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'payable',
        'description' => 'Serviço de Nuvem AWS',
        'amount_cents' => 25000,
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
        ->and($obligation->payment_method)->toBe('pix');

    // Verify NO CashMovement was created
    $movementsCount = CashMovement::query()
        ->where('cash_shift_id', $shift->getKey())
        ->count();

    expect($movementsCount)->toBe(0);

    // Verify CashShift balance unchanged
    $shift->refresh();
    expect($shift->expected_amount_cents)->toBe(50000)
        ->and($shift->lock_version)->toBe(1);
});

it('settles obligation in cash successfully without error even when operator has no open cash shift', function () {
    [$owner, $tenant, $unit] = cashIntegrationTestWorkspace();

    $obligation = FinancialObligation::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'payable',
        'description' => 'Café e Insumos da Copa',
        'amount_cents' => 4500,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)
        ->post(route('financial_obligations.settle', $obligation), [
            'paid_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'lock_version' => 1,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $obligation->refresh();
    expect($obligation->status)->toBe('paid')
        ->and($obligation->payment_method)->toBe('cash')
        ->and($obligation->lock_version)->toBe(2);

    expect(CashMovement::query()->where('tenant_id', $tenant->getKey())->count())->toBe(0);
});
