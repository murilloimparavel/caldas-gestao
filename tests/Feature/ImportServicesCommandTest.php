<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Str;

it('previews service imports without writing and reports duplicate rows', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Import workspace '.Str::random(8),
        'slug' => 'import-'.Str::lower(Str::random(8)),
    ]);
    $file = tempnam(sys_get_temp_dir(), 'services-');
    file_put_contents($file, json_encode(['services' => [[
        'name' => 'Corte teste',
        'price' => '35,00',
        'duration' => 40,
    ], [
        'name' => 'Corte teste',
        'price' => '35,00',
        'duration' => 40,
    ]]], JSON_THROW_ON_ERROR));

    $this->artisan('app:import-services', [
        'file' => $file,
        '--tenant-email' => $owner->email,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(Service::query()->where('tenant_id', $tenant->getKey())->count())->toBe(0);
    unlink($file);
});

it('imports a service once when the command is replayed', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Import workspace '.Str::random(8),
        'slug' => 'import-'.Str::lower(Str::random(8)),
    ]);
    $file = tempnam(sys_get_temp_dir(), 'services-');
    file_put_contents($file, json_encode(['services' => [[
        'name' => 'Barba teste',
        'price_cents' => 3500,
        'duration_minutes' => 30,
    ]]], JSON_THROW_ON_ERROR));

    $arguments = ['file' => $file, '--tenant-email' => $owner->email];
    $this->artisan('app:import-services', $arguments)->assertSuccessful();
    $this->artisan('app:import-services', $arguments)->assertSuccessful();

    expect(Service::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(Service::query()->where('tenant_id', $tenant->getKey())->firstOrFail()->price_cents)->toBe(3500);
    unlink($file);
});
