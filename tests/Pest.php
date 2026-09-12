<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** @return array{0: User, 1: Tenant, 2: Unit, 3: Service, 4: Professional} */
function onlineBookingWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Booking '.Str::random(8),
        'slug' => 'booking-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    return [$owner, $tenant, $unit, $service, $professional];
}
