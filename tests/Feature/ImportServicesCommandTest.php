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
        'source_id' => 'belasis-service-1',
        'name' => 'Barba teste',
        'price_cents' => 3500,
        'duration_minutes' => 30,
    ]]], JSON_THROW_ON_ERROR));

    $arguments = ['file' => $file, '--tenant-email' => $owner->email];
    $this->artisan('app:import-services', $arguments)->assertSuccessful();
    $this->artisan('app:import-services', $arguments)->assertSuccessful();

    expect(Service::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(Service::query()->where('tenant_id', $tenant->getKey())->firstOrFail()->price_cents)->toBe(3500)
        ->and(Service::query()->where('tenant_id', $tenant->getKey())->firstOrFail()->source_id)->toBe('belasis-service-1');
    unlink($file);
});

it('reconciles a legacy service without source id by a unique normalized name', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Import workspace '.Str::random(8),
        'slug' => 'import-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $legacyService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte  teste',
        'price_cents' => 1000,
        'source_id' => null,
    ]);
    $file = tempnam(sys_get_temp_dir(), 'services-');
    file_put_contents($file, json_encode(['services' => [[
        'source_id' => 'belasis-service-unique',
        'name' => ' corte teste ',
        'price_cents' => 3500,
        'duration_minutes' => 45,
    ]]], JSON_THROW_ON_ERROR));

    $this->artisan('app:import-services', [
        'file' => $file,
        '--tenant-email' => $owner->email,
    ])->assertSuccessful();

    expect(Service::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and($legacyService->fresh()->source_id)->toBe('belasis-service-unique')
        ->and($legacyService->fresh()->price_cents)->toBe(3500)
        ->and($legacyService->fresh()->duration_minutes)->toBe(45);
    unlink($file);
});

it('updates an existing source id match and skips ambiguous legacy names', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Import workspace '.Str::random(8),
        'slug' => 'import-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $existingService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Nome antigo',
        'source_id' => 'belasis-service-update',
        'price_cents' => 1000,
    ]);
    Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Ambíguo',
        'source_id' => null,
    ]);
    Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => ' ambíguo ',
        'source_id' => null,
    ]);
    $file = tempnam(sys_get_temp_dir(), 'services-');
    $report = tempnam(sys_get_temp_dir(), 'services-report-');
    file_put_contents($file, json_encode(['services' => [[
        'source_id' => 'belasis-service-update',
        'name' => 'Nome atualizado',
        'price_cents' => 4500,
        'duration_minutes' => 50,
    ], [
        'source_id' => 'belasis-service-ambiguous',
        'name' => 'AMBÍGUO',
        'price_cents' => 5000,
        'duration_minutes' => 30,
    ]]], JSON_THROW_ON_ERROR));

    $this->artisan('app:import-services', [
        'file' => $file,
        '--tenant-email' => $owner->email,
        '--report' => $report,
    ])->assertSuccessful();

    $reportPayload = json_decode((string) file_get_contents($report), true, 512, JSON_THROW_ON_ERROR);
    expect($existingService->fresh()->name)->toBe('Nome atualizado')
        ->and($existingService->fresh()->price_cents)->toBe(4500)
        ->and($reportPayload['services'][1]['reason'])->toBe('ambiguous_existing_name')
        ->and(Service::query()->where('tenant_id', $tenant->getKey())->where('source_id', 'belasis-service-ambiguous')->exists())->toBeFalse();
    unlink($file);
    unlink($report);
});

it('imports the sanitized Belasis service catalog without duplicates', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Belasis import '.Str::random(8),
        'slug' => 'belasis-'.Str::lower(Str::random(8)),
    ]);
    $file = base_path('tests/Fixtures/belasis-services.sanitized.json');

    $arguments = ['file' => $file, '--tenant-email' => $owner->email];
    $this->artisan('app:import-services', $arguments)->assertSuccessful();
    $this->artisan('app:import-services', $arguments)->assertSuccessful();

    expect(Service::query()->where('tenant_id', $tenant->getKey())->count())->toBe(12)
        ->and(Service::query()->where('tenant_id', $tenant->getKey())->where('online_booking_enabled', true)->count())->toBe(12);
});
