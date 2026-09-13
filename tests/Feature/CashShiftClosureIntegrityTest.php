<?php

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

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function cashShiftIntegrityWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Cash Workspace '.Str::random(8),
        'slug' => 'cash-ws-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('executes a complete cash shift lifecycle: open (R$ 100), supply (R$ 50), bleed (R$ 20), and exact close (R$ 130)', function () {
    [$owner, $tenant, $unit] = cashShiftIntegrityWorkspace();

    // 1. Abertura de caixa com fundo inicial de R$ 100,00 (10000 centavos)
    $openResponse = $this->actingAs($owner)->post(route('cash_shifts.store'), [
        'initial_amount_cents' => 10000,
        'notes' => 'Abertura do turno matutino',
    ]);

    $openResponse->assertRedirect(route('cash_shifts.index'));

    /** @var CashShift $shift */
    $shift = CashShift::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    expect($shift->status)->toBe('open')
        ->and($shift->unit_id)->toBe($unit->getKey())
        ->and($shift->opened_by_user_id)->toBe($owner->getKey())
        ->and($shift->closed_by_user_id)->toBeNull()
        ->and($shift->initial_amount_cents)->toBe(10000)
        ->and($shift->expected_amount_cents)->toBe(10000)
        ->and($shift->final_amount_cents)->toBeNull()
        ->and($shift->difference_cents)->toBeNull()
        ->and($shift->notes)->toBe('Abertura do turno matutino')
        ->and($shift->lock_version)->toBe(1);

    expect(AuditEvent::query()->where('action', 'cash_shift.opened')->where('resource_id', $shift->getKey())->exists())->toBeTrue();

    // 2. Suprimento de R$ 50,00 (5000 centavos)
    $supplyResponse = $this->actingAs($owner)->post(route('cash_shifts.move', $shift), [
        'type' => 'supply',
        'amount_cents' => 5000,
        'reason' => 'Reforço de moedas para troco',
        'lock_version' => $shift->lock_version,
    ]);

    $supplyResponse->assertRedirect(route('cash_shifts.index'));

    $shift->refresh();
    expect($shift->expected_amount_cents)->toBe(15000) // 10000 + 5000 = 15000
        ->and($shift->lock_version)->toBe(2);

    $supplyMovement = CashMovement::query()
        ->where('cash_shift_id', $shift->getKey())
        ->where('type', 'supply')
        ->firstOrFail();

    expect($supplyMovement->amount_cents)->toBe(5000)
        ->and($supplyMovement->reason)->toBe('Reforço de moedas para troco')
        ->and($supplyMovement->user_id)->toBe($owner->getKey());

    // 3. Sangria de R$ 20,00 (2000 centavos)
    $bleedResponse = $this->actingAs($owner)->post(route('cash_shifts.move', $shift), [
        'type' => 'bleed',
        'amount_cents' => 2000,
        'reason' => 'Sangria preventiva para cofre',
        'lock_version' => $shift->lock_version,
    ]);

    $bleedResponse->assertRedirect(route('cash_shifts.index'));

    $shift->refresh();
    expect($shift->expected_amount_cents)->toBe(13000) // 15000 - 2000 = 13000
        ->and($shift->lock_version)->toBe(3);

    $bleedMovement = CashMovement::query()
        ->where('cash_shift_id', $shift->getKey())
        ->where('type', 'bleed')
        ->firstOrFail();

    expect($bleedMovement->amount_cents)->toBe(2000)
        ->and($bleedMovement->reason)->toBe('Sangria preventiva para cofre')
        ->and($bleedMovement->user_id)->toBe($owner->getKey());

    // 4. Fechamento com valor contado de R$ 130,00 (13000 centavos) - Fechamento Exato
    $closeResponse = $this->actingAs($owner)->post(route('cash_shifts.close', $shift), [
        'final_amount_cents' => 13000,
        'notes' => 'Conferência física perfeita, sem divergências',
        'lock_version' => $shift->lock_version,
    ]);

    $closeResponse->assertRedirect(route('cash_shifts.index'));

    $shift->refresh();
    expect($shift->status)->toBe('closed')
        ->and($shift->expected_amount_cents)->toBe(13000)
        ->and($shift->final_amount_cents)->toBe(13000)
        ->and($shift->difference_cents)->toBe(0) // Fechamento exato: 13000 - 13000 = 0
        ->and($shift->closed_by_user_id)->toBe($owner->getKey())
        ->and($shift->closed_at)->not->toBeNull()
        ->and($shift->lock_version)->toBe(4);

    // Auditoria e Rastreabilidade Completa
    $closeAudit = AuditEvent::query()
        ->where('action', 'cash_shift.closed')
        ->where('resource_id', $shift->getKey())
        ->firstOrFail();

    expect($closeAudit->actor_user_id)->toBe($owner->getKey())
        ->and($closeAudit->tenant_id)->toBe($tenant->getKey())
        ->and($closeAudit->unit_id)->toBe($unit->getKey());

    expect(AuditEvent::query()->where('action', 'cash_shift.moved')->where('resource_id', $shift->getKey())->count())->toBe(2);
    expect($shift->movements()->count())->toBe(2);

    // 5. Garantir que caixa fechado não aceita novas movimentações
    $this->actingAs($owner)->post(route('cash_shifts.move', $shift), [
        'type' => 'supply',
        'amount_cents' => 1000,
        'reason' => 'Tentativa em caixa fechado',
        'lock_version' => $shift->lock_version,
    ])->assertSessionHasErrors();
});

it('calculates shortage and surplus accurately on cash shift closure', function () {
    [$owner, $tenant, $unit, $context] = cashShiftIntegrityWorkspace();

    // Caso A: Quebra de Caixa (Shortage: final < expected)
    $shiftA = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    (new RecordCashMovement)->handle($owner, $context, $shiftA, [
        'type' => 'supply',
        'amount_cents' => 3000,
        'reason' => 'Suprimento',
    ]);
    $shiftA->refresh(); // expected is 13000

    $this->actingAs($owner)->post(route('cash_shifts.close', $shiftA), [
        'final_amount_cents' => 12500, // Contado 125,00 contra esperado 130,00 -> Falta 5,00 (-500)
        'notes' => 'Falta de troco em moedas',
        'lock_version' => $shiftA->lock_version,
    ])->assertRedirect(route('cash_shifts.index'));

    $shiftA->refresh();
    expect($shiftA->status)->toBe('closed')
        ->and($shiftA->expected_amount_cents)->toBe(13000)
        ->and($shiftA->final_amount_cents)->toBe(12500)
        ->and($shiftA->difference_cents)->toBe(-500);

    // Caso B: Sobra de Caixa (Surplus: final > expected)
    $shiftB = (new OpenCashShift)->handle($owner, $context, [
        'initial_amount_cents' => 10000,
    ]);

    $this->actingAs($owner)->post(route('cash_shifts.close', $shiftB), [
        'final_amount_cents' => 10750, // Contado 107,50 contra esperado 100,00 -> Sobra 7,50 (+750)
        'notes' => 'Sobra identificada no fechamento',
        'lock_version' => $shiftB->lock_version,
    ])->assertRedirect(route('cash_shifts.index'));

    $shiftB->refresh();
    expect($shiftB->status)->toBe('closed')
        ->and($shiftB->expected_amount_cents)->toBe(10000)
        ->and($shiftB->final_amount_cents)->toBe(10750)
        ->and($shiftB->difference_cents)->toBe(750);
});

it('rejects concurrent cash movement with outdated lock_version', function () {
    [$owner, $tenant, $unit] = cashShiftIntegrityWorkspace();

    $this->actingAs($owner)->post(route('cash_shifts.store'), [
        'initial_amount_cents' => 10000,
    ]);

    $shift = CashShift::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    // Advance shift to lock_version = 2
    $this->actingAs($owner)->post(route('cash_shifts.move', $shift), [
        'type' => 'supply',
        'amount_cents' => 1000,
        'reason' => 'Primeiro suprimento legítimo',
        'lock_version' => 1,
    ])->assertRedirect(route('cash_shifts.index'));

    expect($shift->fresh()->lock_version)->toBe(2);

    // Concurrently attempt movement using outdated lock_version = 1 (current is 2)
    $this->actingAs($owner)->post(route('cash_shifts.move', $shift), [
        'type' => 'supply',
        'amount_cents' => 2000,
        'reason' => 'Tentativa concorrente com versão obsoleta',
        'lock_version' => 1,
    ])->assertStatus(409);

    expect($shift->fresh()->expected_amount_cents)->toBe(11000)
        ->and($shift->fresh()->lock_version)->toBe(2);
});
