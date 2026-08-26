<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\ClosingSession;
use App\Models\CommissionAccrual;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function commissionTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('automatically accrues percentage and fixed commissions upon closing session finalization', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Geral',
        'key' => 'geral',
        'type' => 'mixed',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Cliente Teste',
    ]);

    $profA = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Profissional Silva',
    ]);

    $profB = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Profissional Santos',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte Premium',
        'price_cents' => 10000,
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Pomada Modeladora',
        'sale_price_cents' => 5000,
        'cost_price_cents' => 2000,
        'current_stock' => 10,
    ]);

    // Rule 1: Prof A gets 30% on Corte Premium
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $profA->getKey(),
        'service_id' => $service->getKey(),
        'type' => 'percentage',
        'value_rate' => 30,
        'is_active' => true,
    ]);

    // Rule 2: Prof B gets R$ 15,00 fixed (1500 cents) on Pomada Modeladora
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $profB->getKey(),
        'product_id' => $product->getKey(),
        'type' => 'fixed',
        'value_rate' => 1500,
        'is_active' => true,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 15000,
        'final_amount_cents' => 15000,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $profA->getKey(),
        'item_type' => 'service',
        'name_snapshot' => 'Corte Premium',
        'unit_price_cents' => 10000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 10000,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'product_id' => $product->getKey(),
        'professional_id' => $profB->getKey(),
        'item_type' => 'product',
        'name_snapshot' => 'Pomada Modeladora',
        'unit_price_cents' => 5000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 5000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 15000,
    ]);

    $response->assertSessionHasNoErrors();
    $session = ClosingSession::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $response->assertRedirect(route('closing-sessions.show', $session));

    // Verify accruals created
    $accrualsA = CommissionAccrual::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('professional_id', $profA->getKey())
        ->get();

    expect($accrualsA)->toHaveCount(1)
        ->and($accrualsA->first()->commission_amount_cents)->toBe(3000) // 30% of 10000
        ->and($accrualsA->first()->rate_type)->toBe('percentage')
        ->and($accrualsA->first()->rate_value)->toBe(30)
        ->and($accrualsA->first()->status)->toBe('accrued');

    $accrualsB = CommissionAccrual::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('professional_id', $profB->getKey())
        ->get();

    expect($accrualsB)->toHaveCount(1)
        ->and($accrualsB->first()->commission_amount_cents)->toBe(1500) // R$ 15,00 fixed
        ->and($accrualsB->first()->rate_type)->toBe('fixed')
        ->and($accrualsB->first()->rate_value)->toBe(1500)
        ->and($accrualsB->first()->status)->toBe('accrued');

    expect(AuditEvent::query()->where('action', 'commission_accrual.created')->count())->toBe(2);
});

it('prioritizes specific rule over generic rule during commission accrual', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Prof Especialista',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Coloração',
        'price_cents' => 20000,
    ]);

    // Generic rule for this professional: 10%
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $prof->getKey(),
        'service_id' => null,
        'type' => 'percentage',
        'value_rate' => 10,
    ]);

    // Specific rule for this professional on Coloração: 40%
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $prof->getKey(),
        'service_id' => $service->getKey(),
        'type' => 'percentage',
        'value_rate' => 40,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 20000,
        'final_amount_cents' => 20000,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $prof->getKey(),
        'item_type' => 'service',
        'name_snapshot' => 'Coloração',
        'unit_price_cents' => 20000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 20000,
    ]);

    $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 20000,
    ]);

    $accrual = CommissionAccrual::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('professional_id', $prof->getKey())
        ->firstOrFail();

    expect($accrual->rate_value)->toBe(40)
        ->and($accrual->commission_amount_cents)->toBe(8000); // 40% of 20000
});

it('settles accrued commissions generating a settlement and updating accrual statuses', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Profissional Liquidação',
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $item1 = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
    ]);

    $item2 = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
    ]);

    $accrual1 = CommissionAccrual::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $prof->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item1->getKey(),
        'commission_amount_cents' => 3500,
        'status' => 'accrued',
    ]);

    $accrual2 = CommissionAccrual::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $prof->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item2->getKey(),
        'commission_amount_cents' => 1500,
        'status' => 'accrued',
    ]);

    $response = $this->actingAs($owner)->post(route('commissions.settle'), [
        'professional_id' => $prof->getKey(),
        'paid_at' => '2026-08-25',
        'notes' => 'Pagamento quinzenal via PIX',
    ]);

    $response->assertSessionHasNoErrors();

    $settlement = CommissionSettlement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('professional_id', $prof->getKey())
        ->firstOrFail();

    expect($settlement->total_amount_cents)->toBe(5000)
        ->and($settlement->user_id)->toBe($owner->getKey())
        ->and($settlement->notes)->toBe('Pagamento quinzenal via PIX');

    $accrual1->refresh();
    $accrual2->refresh();

    expect($accrual1->status)->toBe('settled')
        ->and($accrual1->settlement_id)->toBe($settlement->getKey())
        ->and($accrual1->settled_at)->not->toBeNull();

    expect($accrual2->status)->toBe('settled')
        ->and($accrual2->settlement_id)->toBe($settlement->getKey())
        ->and($accrual2->settled_at)->not->toBeNull();

    expect(AuditEvent::query()->where('action', 'commission_settlement.completed')->exists())->toBeTrue();
});

it('manages commission rules lifecycle via controller (create, update, delete)', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    // Create Rule
    $createResponse = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'professional_id' => $prof->getKey(),
        'type' => 'percentage',
        'value_rate' => 25,
        'is_active' => true,
    ]);

    $createResponse->assertSessionHasNoErrors();
    $rule = CommissionRule::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    expect($rule->value_rate)->toBe(25)
        ->and($rule->professional_id)->toBe($prof->getKey());

    // Update Rule
    $updateResponse = $this->actingAs($owner)->put(route('commissions.rules.update', $rule), [
        'professional_id' => $prof->getKey(),
        'type' => 'percentage',
        'value_rate' => 35,
        'is_active' => true,
        'lock_version' => $rule->lock_version,
    ]);

    $updateResponse->assertSessionHasNoErrors();
    expect($rule->fresh()->value_rate)->toBe(35)
        ->and($rule->fresh()->lock_version)->toBe(2);

    // Delete Rule
    $deleteResponse = $this->actingAs($owner)->delete(route('commissions.rules.destroy', $rule));
    $deleteResponse->assertSessionHasNoErrors();
    expect(CommissionRule::query()->whereKey($rule->getKey())->exists())->toBeFalse();
});

it('renders commissions index and professional show pages with Inertia', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Profissional Apresentação',
    ]);

    // Index page
    $indexResponse = $this->actingAs($owner)->get(route('commissions.index'));
    $indexResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finance/commissions/index')
            ->has('professionals')
            ->has('rules')
            ->has('metrics')
        );

    // Show page
    $showResponse = $this->actingAs($owner)->get(route('commissions.show', $prof));
    $showResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finance/commissions/show')
            ->has('professional')
            ->has('accruals')
            ->has('settlements')
            ->has('metrics')
        );
});

it('enforces RBAC permissions for commission viewing, management and settlement', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $restrictedUser = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $restrictedUser->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);

    $role = Role::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'key' => 'viewer-'.Str::random(6),
    ]);
    $viewPermission = Permission::query()->where('key', 'commission.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $viewPermission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    // Can view
    $this->actingAs($restrictedUser)->get(route('commissions.index'))->assertOk();

    // Cannot create rule (needs commission.manage)
    $this->actingAs($restrictedUser)->post(route('commissions.rules.store'), [
        'professional_id' => $prof->getKey(),
        'type' => 'percentage',
        'value_rate' => 20,
    ])->assertForbidden();

    // Cannot settle (needs commission.settle)
    $this->actingAs($restrictedUser)->post(route('commissions.settle'), [
        'professional_id' => $prof->getKey(),
    ])->assertForbidden();
});
