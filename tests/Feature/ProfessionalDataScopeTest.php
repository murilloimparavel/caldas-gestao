<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\ClosingSession;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

/** @return array{0: User, 1: User, 2: Tenant, 3: Unit, 4: Professional, 5: Professional} */
function professionalScopeWorkspace(array $permissions = ['calendar.view', 'calendar.manage', 'sale.view', 'sale.manage']): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $ownProfessional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Profissional próprio']);
    $foreignProfessional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Outro profissional']);
    $collaborator = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $collaborator->getKey(),
        'professional_id' => $ownProfessional->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'professional-'.Str::lower(Str::random(8)), 'name' => 'Profissional']);

    foreach (Permission::query()->whereIn('key', $permissions)->get() as $permission) {
        RolePermission::query()->create([
            'tenant_id' => $tenant->getKey(),
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
        ]);
    }

    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    return [$owner, $collaborator, $tenant, $unit, $ownProfessional, $foreignProfessional];
}

it('returns only the linked professional appointments and blocks cross-professional mutations', function () {
    [$owner, $collaborator, $tenant, $unit, $ownProfessional, $foreignProfessional] = professionalScopeWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $ownAppointment = Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $ownProfessional->getKey(),
    ]);
    $foreignAppointment = Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $foreignProfessional->getKey(),
    ]);
    $unrelatedCustomer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $this->actingAs($collaborator)
        ->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('appointments', function ($appointments) use ($ownAppointment, $foreignAppointment): bool {
                $ids = collect($appointments)->pluck('id')->all();

                return $ids === [$ownAppointment->getKey()] && ! in_array($foreignAppointment->getKey(), $ids, true);
            })
            ->where('options.customers', function ($customers) use ($customer, $unrelatedCustomer): bool {
                $ids = collect($customers)->pluck('id')->all();

                return $ids === [$customer->getKey()] && ! in_array($unrelatedCustomer->getKey(), $ids, true);
            }));

    $this->actingAs($collaborator)
        ->post(route('appointments.check_in', $foreignAppointment), ['lock_version' => 0])
        ->assertForbidden();

    $this->actingAs($collaborator)
        ->put(route('appointments.update', $foreignAppointment), [
            'customer_id' => $customer->getKey(),
            'service_id' => Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])->getKey(),
            'professional_id' => $foreignProfessional->getKey(),
            'starts_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'lock_version' => 0,
        ])
        ->assertForbidden();

    expect($owner->exists)->toBeTrue();
});

it('opens comandas for the linked professional, scopes the index, and blocks cross-sale access', function () {
    [$owner, $collaborator, $tenant, $unit, $ownProfessional, $foreignProfessional] = professionalScopeWorkspace();
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'uniqueness_scope' => 'none',
    ]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $unrelatedCustomer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $ownResponse = $this->actingAs($collaborator)->post(route('sales.store'), [
        'sale_category_id' => $category->getKey(),
        'customer_id' => $customer->getKey(),
    ]);
    $ownSale = Sale::query()->where('professional_id', $ownProfessional->getKey())->firstOrFail();
    $ownResponse->assertRedirect(route('sales.show', $ownSale));

    $foreignSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $foreignSale->getKey(),
        'professional_id' => $foreignProfessional->getKey(),
    ]);

    $this->actingAs($collaborator)
        ->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sales.data', function ($sales) use ($ownSale, $foreignSale): bool {
                $ids = collect($sales)->pluck('id')->all();

                return in_array($ownSale->getKey(), $ids, true) && ! in_array($foreignSale->getKey(), $ids, true);
            })
            ->where('customers', function ($customers) use ($customer, $unrelatedCustomer): bool {
                $ids = collect($customers)->pluck('id')->all();

                return $ids === [$customer->getKey()] && ! in_array($unrelatedCustomer->getKey(), $ids, true);
            }));

    $this->actingAs($collaborator)->get(route('sales.show', $foreignSale))->assertForbidden();
    $this->actingAs($collaborator)->post(route('sales.transition', $foreignSale), [
        'status' => 'ready_to_bill',
        'lock_version' => 1,
    ])->assertForbidden();
    $this->actingAs($collaborator)->post(route('sales.items.store', $foreignSale), [
        'item_type' => 'custom',
        'name_snapshot' => 'Tentativa indevida',
        'unit_price_cents' => 1000,
    ])->assertForbidden();

    $this->actingAs($collaborator)->post(route('sales.items.store', $ownSale), [
        'item_type' => 'custom',
        'name_snapshot' => 'Serviço próprio',
        'unit_price_cents' => 1000,
    ])->assertRedirect(route('sales.show', $ownSale));

    expect($ownSale->fresh()->items()->firstOrFail()->professional_id)->toBe($ownProfessional->getKey());

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $ownSale->getKey(),
        'professional_id' => $foreignProfessional->getKey(),
    ]);

    $this->actingAs($collaborator)
        ->get(route('sales.index'))
        ->assertInertia(fn ($page) => $page->where('sales.data', function ($sales) use ($ownSale): bool {
            return ! in_array($ownSale->getKey(), collect($sales)->pluck('id')->all(), true);
        }));
    $this->actingAs($collaborator)->get(route('sales.show', $ownSale))->assertForbidden();
    $this->actingAs($collaborator)->post(route('sales.transition', $ownSale), [
        'status' => 'ready_to_bill',
        'lock_version' => 2,
    ])->assertForbidden();

    $mixedSession = ClosingSession::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'completed',
    ]);
    $mixedSession->sales()->attach($ownSale->getKey());

    $this->actingAs($collaborator)->get(route('closing-sessions.show', $mixedSession))->assertForbidden();
});

it('returns no calendar settings when the linked professional is inactive', function () {
    [, $collaborator, $tenant, $unit, $ownProfessional] = professionalScopeWorkspace();
    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $ownProfessional->getKey(),
    ]);
    ScheduleBlock::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $ownProfessional->getKey(),
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
    $ownProfessional->update(['status' => 'inactive']);

    $this->actingAs($collaborator)
        ->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('calendarSettings.availability_rules', [])
            ->where('calendarSettings.schedule_blocks', []));
});

it('keeps owner access unrestricted when no professional is linked', function () {
    [$owner, , $tenant, $unit, $ownProfessional, $foreignProfessional] = professionalScopeWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $unrelatedCustomer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $appointments = Appointment::factory()->count(2)->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
    ]);
    $appointments[0]->update(['professional_id' => $ownProfessional->getKey()]);
    $appointments[1]->update(['professional_id' => $foreignProfessional->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $foreignSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'professional_id' => $foreignProfessional->getKey(),
    ]);

    $this->actingAs($owner)
        ->get(route('calendar.index'))
        ->assertInertia(fn ($page) => $page
            ->where('appointments', function ($items) use ($appointments): bool {
                return collect($items)->pluck('id')->sort()->values()->all() === $appointments->pluck('id')->sort()->values()->all();
            })
            ->where('options.customers', function ($customers) use ($customer, $unrelatedCustomer): bool {
                $ids = collect($customers)->pluck('id')->all();

                return in_array($customer->getKey(), $ids, true) && in_array($unrelatedCustomer->getKey(), $ids, true);
            }));
    $this->actingAs($owner)
        ->get(route('sales.index'))
        ->assertInertia(fn ($page) => $page->where('sales.data', function ($sales) use ($foreignSale): bool {
            return in_array($foreignSale->getKey(), collect($sales)->pluck('id')->all(), true);
        }));
});

it('scopes dashboard agenda and comanda metrics to the linked professional', function () {
    [, $collaborator, $tenant, $unit, $ownProfessional, $foreignProfessional] = professionalScopeWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $ownProfessional->getKey(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]);
    Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $foreignProfessional->getKey(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'professional_id' => $ownProfessional->getKey(),
        'status' => 'finalized',
        'created_at' => now(),
    ]);
    Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'professional_id' => $foreignProfessional->getKey(),
        'status' => 'finalized',
        'created_at' => now(),
    ]);

    $this->actingAs($collaborator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.topKpis.appointments.value', '1')
            ->where('dashboard.topKpis.tickets.value', '1'));
});
