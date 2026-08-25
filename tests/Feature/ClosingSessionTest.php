<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\ClosingSession;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\SaleStatusHistory;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function closingTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('finalizes a single sale closing session, generating receipt payload, status histories and audit events', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbearia',
        'key' => 'barbearia',
        'type' => 'mixed',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Renato Russo',
        'phone' => '(11) 98888-7777',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte Masculino',
        'price_cents' => 5000,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'category_name_snapshot' => 'Barbearia',
        'category_key_snapshot' => 'barbearia',
        'status' => 'open',
        'total_amount_cents' => 5000,
        'discount_amount_cents' => 500,
        'final_amount_cents' => 4500,
        'lock_version' => 1,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $service->getKey(),
        'item_type' => 'service',
        'name_snapshot' => 'Corte Masculino',
        'unit_price_cents' => 5000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 5000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 4500,
        'notes' => 'Fechamento rápido no caixa',
    ]);

    $response->assertSessionHasNoErrors();

    $session = ClosingSession::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $response->assertRedirect(route('closing-sessions.show', $session));

    expect($session->status)->toBe('completed')
        ->and($session->unit_id)->toBe($unit->getKey())
        ->and($session->closing_subject)->toBe("customer:{$customer->getKey()}")
        ->and($session->expected_total_cents)->toBe(4500)
        ->and($session->final_total_cents)->toBe(4500)
        ->and($session->closed_by_user_id)->toBe($owner->getKey())
        ->and($session->receipt_number)->not->toBeNull()
        ->and($session->receipt_payload)->toBeArray()
        ->and($session->receipt_payload['closing_subject'])->toBe("customer:{$customer->getKey()}")
        ->and($session->receipt_payload['totals']['final_total_cents'])->toBe(4500)
        ->and($session->receipt_payload['totals']['total_discount_cents'])->toBe(500)
        ->and($session->receipt_payload['customer']['name'])->toBe('Renato Russo')
        ->and($session->receipt_payload['notes'])->toBe('Fechamento rápido no caixa');

    $sale->refresh();
    expect($sale->status)->toBe('finalized')
        ->and($sale->lock_version)->toBe(2);

    expect($session->sales)->toHaveCount(1)
        ->and($session->sales->first()->getKey())->toBe($sale->getKey());

    $statusHistory = SaleStatusHistory::query()
        ->where('sale_id', $sale->getKey())
        ->where('to_status', 'finalized')
        ->firstOrFail();

    expect($statusHistory->from_status)->toBe('open')
        ->and($statusHistory->user_id)->toBe($owner->getKey());

    expect(AuditEvent::query()->where('action', 'closing_session.completed')->where('resource_id', $session->getKey())->exists())->toBeTrue();
    expect(AuditEvent::query()->where('action', 'sale.finalized')->where('resource_id', $sale->getKey())->exists())->toBeTrue();
});

it('consolidates multiple sales for the same customer into a single closing session', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $catBarber = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbearia',
        'key' => 'barber',
        'type' => 'service',
        'is_active' => true,
    ]);

    $catShop = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Loja',
        'key' => 'shop',
        'type' => 'product',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Marcos Pontes',
    ]);

    $sale1 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $catBarber->getKey(),
        'status' => 'open',
        'total_amount_cents' => 6000,
        'discount_amount_cents' => 0,
        'final_amount_cents' => 6000,
    ]);

    $sale2 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $catShop->getKey(),
        'status' => 'ready_to_bill',
        'total_amount_cents' => 4000,
        'discount_amount_cents' => 1000,
        'final_amount_cents' => 3000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale1->getKey(), $sale2->getKey()],
        'expected_total_cents' => 9000,
    ]);

    $session = ClosingSession::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $response->assertRedirect(route('closing-sessions.show', $session));

    expect($session->status)->toBe('completed')
        ->and($session->expected_total_cents)->toBe(9000)
        ->and($session->final_total_cents)->toBe(9000)
        ->and($session->closing_subject)->toBe("customer:{$customer->getKey()}");

    expect($sale1->fresh()->status)->toBe('finalized');
    expect($sale2->fresh()->status)->toBe('finalized');
    expect($session->sales)->toHaveCount(2);
});

it('consolidates multiple customer-less sales with the same reference_label', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Restaurante',
        'key' => 'restaurante',
        'is_active' => true,
    ]);

    $sale1 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => null,
        'reference_label' => 'Mesa 12',
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 8000,
        'final_amount_cents' => 8000,
    ]);

    $sale2 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => null,
        'reference_label' => 'Mesa 12',
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 3500,
        'final_amount_cents' => 3500,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale1->getKey(), $sale2->getKey()],
        'expected_total_cents' => 11500,
    ]);

    $session = ClosingSession::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $response->assertRedirect(route('closing-sessions.show', $session));

    expect($session->status)->toBe('completed')
        ->and($session->closing_subject)->toBe('reference:mesa-12')
        ->and($session->final_total_cents)->toBe(11500);

    expect($sale1->fresh()->status)->toBe('finalized');
    expect($sale2->fresh()->status)->toBe('finalized');
});

it('rejects closing session with divergent customers (different closing subjects)', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);

    $customerA = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $customerB = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $sale1 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customerA->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'final_amount_cents' => 2000,
    ]);

    $sale2 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customerB->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'final_amount_cents' => 3000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale1->getKey(), $sale2->getKey()],
    ]);

    $response->assertSessionHasErrors('closing_subject');

    expect($sale1->fresh()->status)->toBe('open');
    expect($sale2->fresh()->status)->toBe('open');
    expect(ClosingSession::query()->count())->toBe(0);
});

it('rejects closing session mixing customer sale and reference sale', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $saleWithCustomer = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'final_amount_cents' => 2000,
    ]);

    $saleWithRefOnly = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => null,
        'reference_label' => 'Mesa 10',
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'final_amount_cents' => 3000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$saleWithCustomer->getKey(), $saleWithRefOnly->getKey()],
    ]);

    $response->assertSessionHasErrors('closing_subject');
});

it('rejects closing session if any sale is already finalized or cancelled', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $saleFinalized = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
        'final_amount_cents' => 2000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$saleFinalized->getKey()],
    ]);

    $response->assertSessionHasErrors('sale_ids');
});

it('rejects closing session if lock_version does not match expected version (concurrency conflict)', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'lock_version' => 2,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'lock_versions' => [
            $sale->getKey() => 1, // outdated version
        ],
    ]);

    $response->assertStatus(409);
    expect($sale->fresh()->status)->toBe('open');
});

it('supports idempotency via X-Idempotency-Key without re-executing or creating duplicates', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 5000,
        'final_amount_cents' => 5000,
    ]);

    $idempotencyKey = 'idem-closing-test-'.Str::random(12);

    $response1 = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', $idempotencyKey)
        ->post(route('closing-sessions.store'), [
            'sale_ids' => [$sale->getKey()],
            'expected_total_cents' => 5000,
        ]);

    $session1 = ClosingSession::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $response1->assertRedirect(route('closing-sessions.show', $session1));
    expect(ClosingSession::query()->count())->toBe(1);

    // Replay with identical idempotency key
    $response2 = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', $idempotencyKey)
        ->post(route('closing-sessions.store'), [
            'sale_ids' => [$sale->getKey()],
            'expected_total_cents' => 5000,
        ]);

    $response2->assertRedirect(route('closing-sessions.show', $session1));
    expect(ClosingSession::query()->count())->toBe(1);
});

it('renders the internal receipt on closing-sessions.show', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $session = ClosingSession::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'completed',
        'expected_total_cents' => 5000,
        'final_total_cents' => 5000,
        'receipt_number' => 'REC-20260825-ABC123',
        'receipt_payload' => [
            'receipt_number' => 'REC-20260825-ABC123',
            'issued_at' => now()->toISOString(),
            'tenant' => ['id' => $tenant->getKey(), 'name' => $tenant->name],
            'unit' => ['id' => $unit->getKey(), 'name' => $unit->name],
            'closed_by' => ['id' => $owner->getKey(), 'name' => $owner->name],
            'closing_subject' => 'customer:'.Str::uuid7(),
            'currency' => 'BRL',
            'totals' => [
                'total_gross_cents' => 5000,
                'total_discount_cents' => 0,
                'final_total_cents' => 5000,
                'sales_count' => 1,
            ],
            'sales' => [],
        ],
    ]);

    $response = $this->actingAs($owner)->get(route('closing-sessions.show', $session));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('closing-sessions/show')
            ->has('session')
            ->where('session.id', $session->getKey())
            ->where('session.receipt_number', 'REC-20260825-ABC123')
        );
});

it('closes a single anonymous sale without customer or reference label with sale:{id} subject', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'uniqueness_scope' => 'none',
        'is_active' => true,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => null,
        'reference_label' => null,
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 3000,
        'final_amount_cents' => 3000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 3000,
    ]);

    $session = ClosingSession::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $response->assertRedirect(route('closing-sessions.show', $session));

    expect($session->status)->toBe('completed')
        ->and($session->closing_subject)->toBe("sale:{$sale->getKey()}");
});

it('rejects multiple anonymous sales without customer or reference label', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'uniqueness_scope' => 'none',
        'is_active' => true,
    ]);

    $sale1 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => null,
        'reference_label' => null,
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'final_amount_cents' => 2000,
    ]);

    $sale2 = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => null,
        'reference_label' => null,
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'final_amount_cents' => 3000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale1->getKey(), $sale2->getKey()],
    ]);

    $response->assertSessionHasErrors('closing_subject');
});

it('rejects closing session if expected_total_cents does not match calculated total', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 5000,
        'final_amount_cents' => 5000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 9999, // Mismatched expected total
    ]);

    $response->assertSessionHasErrors('expected_total_cents');
});

it('rejects closing session if user does not have sale.close or sale.manage permission', function () {
    [$owner, $tenant, $unit] = closingTestWorkspace();

    $restrictedUser = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $restrictedUser->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);

    $role = Role::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'key' => 'view-only-'.Str::random(6),
    ]);
    $viewPermission = Permission::query()->where('key', 'sale.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $viewPermission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'final_amount_cents' => 5000,
    ]);

    $response = $this->actingAs($restrictedUser)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 5000,
    ]);

    $response->assertForbidden();
});

it('rejects closing session for sales belonging to a different unit or workspace', function () {
    [$ownerA, $tenantA, $unitA] = closingTestWorkspace();
    [$ownerB, $tenantB, $unitB] = closingTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenantB->getKey(),
        'unit_id' => $unitB->getKey(),
        'is_active' => true,
    ]);
    $customer = Customer::factory()->create(['tenant_id' => $tenantB->getKey(), 'unit_id' => $unitB->getKey()]);

    $saleB = Sale::factory()->create([
        'tenant_id' => $tenantB->getKey(),
        'unit_id' => $unitB->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'final_amount_cents' => 5000,
    ]);

    $response = $this->actingAs($ownerA)->post(route('closing-sessions.store'), [
        'sale_ids' => [$saleB->getKey()],
        'expected_total_cents' => 5000,
    ]);

    $response->assertSessionHasErrors('sale_ids');
});
