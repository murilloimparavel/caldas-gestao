<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\PackageTemplate;
use App\Models\PackageUsage;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** @return array{0: User, 1: CustomerPackage} */
function observabilityPackageWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Balance observability '.Str::random(8),
        'slug' => 'balance-observability-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'total_sessions' => 4]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'total_sessions' => 4,
        'remaining_sessions' => 3,
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name, 'quantity' => 4]],
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 4,
        'remaining_quantity' => 3,
    ]);
    PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'sessions_consumed' => 1,
        'user_id' => $owner->getKey(),
    ]);

    return [$owner, $package];
}

it('emits an aggregate read-only summary without package identifiers', function (): void {
    $exitCode = Artisan::call('packages:audit-balances', [
        '--format' => 'json',
        '--summary' => true,
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['read_only'])->toBeTrue()
        ->and($payload['packages'])->toBe([])
        ->and($payload)->toHaveKeys(['alert', 'totals']);
});

it('emits a PII-free operational warning in an aggregate summary', function (): void {
    [, $package] = observabilityPackageWorkspace();
    $package->serviceBalances()->update(['remaining_quantity' => 0]);
    Log::spy();

    $exitCode = Artisan::call('packages:audit-balances', [
        '--format' => 'json',
        '--summary' => true,
    ]);
    $output = Artisan::output();
    $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['alert'])->toBe('balance_review_required')
        ->and($payload['operator_message'])->toContain('causa não foi determinada')
        ->and($payload['safe_action'])->toContain('nenhum reparo automático')
        ->and($payload['packages'])->toBe([])
        ->and($output)->not->toContain($package->getKey());

    Log::shouldHaveReceived('warning')->once()->with('customer_package.balance_audit_review_required', Mockery::on(function (array $context) use ($package): bool {
        return $context['divergent'] === 1
            && $context['ambiguous'] === 0
            && ! array_key_exists('customer_package_id', $context)
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $package->getKey());
    }));
});

it('explains detailed audit output in operational language', function (): void {
    [, $package] = observabilityPackageWorkspace();
    $package->serviceBalances()->update(['remaining_quantity' => 0]);
    Artisan::call('packages:audit-balances', [
        '--format' => 'json',
        '--package' => $package->getKey(),
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['packages'][0]['classification'])->toBe('repairable_divergence')
        ->and($payload['packages'][0]['cause'])->toBe('indeterminate')
        ->and($payload['packages'][0]['operator_message'])->toContain('evento que causou');
});
