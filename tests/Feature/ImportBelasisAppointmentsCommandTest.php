<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Str;

function appointmentImportFile(array $records): string
{
    $file = tempnam(sys_get_temp_dir(), 'appointments-');
    file_put_contents($file, json_encode(['entity' => 'appointments', 'records' => $records], JSON_THROW_ON_ERROR));

    return $file;
}

it('previews and imports appointments with source crosswalks', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Appointment import '.Str::random(8), 'slug' => 'appointment-import-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'source_id' => 'client-1']);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'source_id' => 'service-1']);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'source_id' => 'professional-1']);
    $file = appointmentImportFile([['source_id' => 'appointment-1', 'starts_at' => '2026-01-02 10:00', 'ends_at' => '2026-01-02 11:00', 'client_source_id' => 'client-1', 'service_source_id' => 'service-1', 'professional_source_id' => 'professional-1', 'status' => 'completed', 'notes' => 'Imported']]);

    $this->artisan('app:import-belasis-appointments', ['--tenant-id' => $tenant->getKey(), '--unit-id' => $unit->getKey(), '--file' => $file])->assertSuccessful()->expectsOutputToContain('1 created');
    expect(Appointment::query()->where('source_id', 'appointment-1')->exists())->toBeFalse();
    $this->artisan('app:import-belasis-appointments', ['--tenant-id' => $tenant->getKey(), '--unit-id' => $unit->getKey(), '--file' => $file, '--write' => true])->assertSuccessful();
    $appointment = Appointment::query()->where('source_id', 'appointment-1')->firstOrFail();
    expect($appointment->customer_id)->toBe($customer->getKey())->and($appointment->professional_id)->toBe($professional->getKey())->and($appointment->status)->toBe('completed')->and($appointment->source)->toBe('imported')->and($appointment->notes)->toBe('Imported')->and($appointment->items()->firstOrFail()->service_id)->toBe($service->getKey());
    unlink($file);
});

it('keeps appointment blocks and missing relations pending', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Appointment pending '.Str::random(8), 'slug' => 'appointment-pending-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $file = appointmentImportFile([['source_id' => 'block-1', 'starts_at' => '2026-01-02 10:00', 'ends_at' => '2026-01-02 11:00', 'client_source_id' => null, 'service_source_id' => null, 'professional_source_id' => 'missing', 'status' => null]]);
    $this->artisan('app:import-belasis-appointments', ['--tenant-id' => $tenant->getKey(), '--unit-id' => $unit->getKey(), '--file' => $file, '--write' => true])->assertSuccessful()->expectsOutputToContain('1 pending');
    expect(Appointment::query()->where('source_id', 'block-1')->exists())->toBeFalse();
    unlink($file);
});
