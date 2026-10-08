<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\FinancialObligation;
use App\Models\PackageTemplate;
use App\Models\PackageUsage;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('arquiva estados encerrados e restaura o status original sem alterar o saldo', function (string $status): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Archived package '.Str::random(8),
        'slug' => 'archived-package-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'status' => $status,
        'total_sessions' => 4,
        'remaining_sessions' => 1,
    ]);
    $obligation = FinancialObligation::factory()->receivable()->paid()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'customer_package_id' => $package->getKey(),
        'amount_cents' => 30000,
    ]);
    $usage = PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'user_id' => $owner->getKey(),
        'sessions_consumed' => 3,
    ]);

    $this->actingAs($owner)
        ->post(route('customer-packages.archive', $package))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $archived = $package->fresh();
    expect($archived->status)->toBe('archived')
        ->and($archived->archived_from_status)->toBe($status)
        ->and($archived->archived_at)->not->toBeNull()
        ->and($archived->remaining_sessions)->toBe(1)
        ->and($archived->total_sessions)->toBe(4);

    $this->actingAs($owner)
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('customers/show')
            ->has('customer.customer_packages', 0));

    $this->actingAs($owner)
        ->get(route('packages.show', $template))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('packages/show')
            ->where('customerPackages.total', 0));

    $this->actingAs($owner)
        ->get(route('packages.archived'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('packages/archived')
            ->has('packages.data', 1)
            ->where('packages.data.0.id', $package->getKey())
            ->where('packages.data.0.financial_obligation.id', $obligation->getKey())
            ->where('packages.data.0.usages.0.id', $usage->getKey()));

    $this->actingAs($owner)
        ->get(route('packages.archived', ['search' => $customer->name]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('packages/archived')
            ->where('filters.search', $customer->name)
            ->has('packages.data', 1));

    $this->actingAs($owner)
        ->post(route('customer-packages.restore', $package))
        ->assertSessionHasNoErrors();

    $restored = $package->fresh();
    expect($restored->status)->toBe($status)
        ->and($restored->archived_from_status)->toBeNull()
        ->and($restored->archived_at)->toBeNull()
        ->and($restored->remaining_sessions)->toBe(1)
        ->and($restored->total_sessions)->toBe(4)
        ->and(FinancialObligation::query()->whereKey($obligation->getKey())->value('status'))->toBe('paid')
        ->and(PackageUsage::query()->whereKey($usage->getKey())->value('sessions_consumed'))->toBe(3);
})->with(['completed', 'exhausted', 'cancelled', 'expired']);

it('impede arquivar pacotes pendentes ou ativos', function (string $status): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Not archivable '.Str::random(8),
        'slug' => 'not-archivable-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => $status,
    ]);

    $this->actingAs($owner)
        ->post(route('customer-packages.archive', $package))
        ->assertSessionHasErrors('package');

    expect($package->fresh()->status)->toBe($status)
        ->and($package->fresh()->archived_at)->toBeNull();
})->with(['pending', 'active']);
