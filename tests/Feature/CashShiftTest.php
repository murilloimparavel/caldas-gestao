<?php

use App\Actions\Finance\Cash\CloseCashShift;
use App\Actions\Finance\Cash\OpenCashShift;
use App\Actions\Finance\Cash\RecordCashMovement;
use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function cashTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace Cash '.Str::random(8),
        'slug' => 'workspace-cash-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('opens a cash shift and records audit event and initial amounts', function () {
    [$owner, $tenant, $unit] = cashTestWorkspace();

    $response = $this->actingAs($owner)->post(route('cash_shifts.store'), [
        'initial_amount_cents' => 15000,
        'notes' => 'Fundo inicial de troco',
    ]);

    $response->assertRedirect(route('cash_shifts.index'));

    /** @var CashShift $shift */
    $shift = CashShift::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    expect($shift->unit_id)->toBe($unit->getKey())
        ->and($shift->opened_by_user_id)->toBe($owner->getKey())
        ->and($shift->closed_by_user_id)->toBeNull()
        ->and($shift->initial_amount_cents)->toBe(15000)
        ->and($shift->expected_amount_cents)->toBe(15000)
        ->and($shift->final_amount_cents)->toBeNull()
        ->and($shift->difference_cents)->toBeNull()
        ->and($shift->status)->toBe('open')
        ->and($shift->notes)->toBe('Fundo inicial de troco')
        ->and($shift->lock_version)->toBe(1);

    expect(AuditEvent::query()->where('action', 'cash_shift.opened')->where('resource_id', $shift->getKey())->exists())->toBeTrue();
});

it('returns to sales after opening a shift from the payment flow', function () {
    [$owner] = cashTestWorkspace();

    $this->actingAs($owner)->post(route('cash_shifts.store'), [
        'initial_amount_cents' => 0,
        'return_to' => 'sales',
    ])->assertRedirect(route('sales.index'));
});

it('prevents opening a second cash shift when operator already has an active open shift in unit', function () {
    [$owner, $tenant, $unit, $context] = cashTestWorkspace();

    (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    expect(fn () => (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 5000,
    ]))->toThrow(ValidationException::class);
});

it('records cash supply and increments expected amount and lock version', function () {
    [$owner, $tenant, $unit, $context] = cashTestWorkspace();

    $shift = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    $response = $this->actingAs($owner)->post(route('cash_shifts.move', $shift), [
        'type' => 'supply',
        'amount_cents' => 5000,
        'reason' => 'Reforço de moedas para troco',
        'lock_version' => $shift->lock_version,
    ]);

    $response->assertRedirect(route('cash_shifts.index'));

    $shift->refresh();
    expect($shift->expected_amount_cents)->toBe(15000)
        ->and($shift->lock_version)->toBe(2);

    /** @var CashMovement $movement */
    $movement = CashMovement::query()->where('cash_shift_id', $shift->getKey())->firstOrFail();

    expect($movement->type)->toBe('supply')
        ->and($movement->amount_cents)->toBe(5000)
        ->and($movement->reason)->toBe('Reforço de moedas para troco')
        ->and($movement->user_id)->toBe($owner->getKey());

    expect(AuditEvent::query()->where('action', 'cash_shift.moved')->where('resource_id', $shift->getKey())->exists())->toBeTrue();
});

it('records cash bleed and decrements expected amount', function () {
    [$owner, $tenant, $unit, $context] = cashTestWorkspace();

    $shift = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 20000,
    ]);

    $response = $this->actingAs($owner)->post(route('cash_shifts.move', $shift), [
        'type' => 'bleed',
        'amount_cents' => 8000,
        'reason' => 'Retirada para depósito bancário',
        'lock_version' => $shift->lock_version,
    ]);

    $response->assertRedirect(route('cash_shifts.index'));

    $shift->refresh();
    expect($shift->expected_amount_cents)->toBe(12000)
        ->and($shift->lock_version)->toBe(2);
});

it('rejects cash bleed when amount exceeds available expected amount in shift', function () {
    [$owner, $tenant, $unit, $context] = cashTestWorkspace();

    $shift = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 5000,
    ]);

    expect(fn () => (new RecordCashMovement)->handle($owner, $context, $shift, [
        'type' => 'bleed',
        'amount_cents' => 10000,
        'reason' => 'Sangria excessiva',
    ]))->toThrow(ValidationException::class);
});

it('closes cash shift with exact count, surplus or shortage and records audit', function () {
    [$owner, $tenant, $unit, $context] = cashTestWorkspace();

    // Case 1: Exact closure
    $shift1 = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    (new RecordCashMovement)->handle($owner, $context, $shift1, [
        'type' => 'supply',
        'amount_cents' => 2500,
        'reason' => 'Suprimento',
    ]);
    $shift1->refresh();

    $response = $this->actingAs($owner)->post(route('cash_shifts.close', $shift1), [
        'final_amount_cents' => 12500,
        'notes' => 'Fechamento perfeito',
        'lock_version' => $shift1->lock_version,
    ]);

    $response->assertRedirect(route('cash_shifts.index'));

    $shift1->refresh();
    expect($shift1->status)->toBe('closed')
        ->and($shift1->closed_by_user_id)->toBe($owner->getKey())
        ->and($shift1->final_amount_cents)->toBe(12500)
        ->and($shift1->difference_cents)->toBe(0)
        ->and($shift1->closed_at)->not->toBeNull();

    expect(AuditEvent::query()->where('action', 'cash_shift.closed')->where('resource_id', $shift1->getKey())->exists())->toBeTrue();

    // Case 2: Shortage (Quebra)
    $shift2 = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    (new CloseCashShift)->handle($owner, $context, $shift2, [
        'final_amount_cents' => 9500,
    ]);

    $shift2->refresh();
    expect($shift2->status)->toBe('closed')
        ->and($shift2->difference_cents)->toBe(-500);

    // Case 3: Surplus (Sobra)
    $shift3 = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    (new CloseCashShift)->handle($owner, $context, $shift3, [
        'final_amount_cents' => 10700,
    ]);

    $shift3->refresh();
    expect($shift3->status)->toBe('closed')
        ->and($shift3->difference_cents)->toBe(700);
});

it('prevents movements on already closed cash shift', function () {
    [$owner, $tenant, $unit, $context] = cashTestWorkspace();

    $shift = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    (new CloseCashShift)->handle($owner, $context, $shift, [
        'final_amount_cents' => 10000,
    ]);
    $shift->refresh();

    expect(fn () => (new RecordCashMovement)->handle($owner, $context, $shift, [
        'type' => 'supply',
        'amount_cents' => 1000,
        'reason' => 'Tentativa inválida em caixa fechado',
    ]))->toThrow(ValidationException::class);
});

it('protects cash shift from concurrent modifications via lock_version', function () {
    [$owner, $tenant, $unit, $context] = cashTestWorkspace();

    $shift = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    // Pass stale lock_version 99
    expect(fn () => (new RecordCashMovement)->handle($owner, $context, $shift, [
        'type' => 'supply',
        'amount_cents' => 1000,
        'reason' => 'Movimento concorrente',
        'lock_version' => 99,
    ]))->toThrow(ConflictHttpException::class);

    expect(fn () => (new CloseCashShift)->handle($owner, $context, $shift, [
        'final_amount_cents' => 10000,
        'lock_version' => 99,
    ]))->toThrow(ConflictHttpException::class);
});

it('renders cash index, show, and history pages correctly', function () {
    [$owner, $tenant, $unit, $context] = cashTestWorkspace();

    // 1. Index without open shift
    $this->actingAs($owner)->get(route('cash_shifts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('finance/cash/index')->where('active_shift', null));

    // 2. Open shift and check index
    $shift = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    $this->actingAs($owner)->get(route('cash_shifts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('finance/cash/index')->where('active_shift.id', $shift->getKey()));

    // 3. Show page
    $this->actingAs($owner)->get(route('cash_shifts.show', $shift))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('finance/cash/show')->where('shift.id', $shift->getKey()));

    // 4. Close shift and check history
    (new CloseCashShift)->handle($owner, $context, $shift, [
        'final_amount_cents' => 10000,
    ]);

    $this->actingAs($owner)->get(route('cash_shifts.history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('finance/cash/history')->has('shifts.data', 1));
});
