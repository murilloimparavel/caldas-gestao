<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Str;

function customerImportDestination(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Customer import '.Str::random(8),
        'slug' => 'customer-import-'.Str::lower(Str::random(8)),
    ]);

    return [$tenant->getKey(), $tenant->units()->firstOrFail()->getKey()];
}

function customerImportFile(array $clients): string
{
    $file = tempnam(sys_get_temp_dir(), 'customers-');
    file_put_contents($file, json_encode($clients, JSON_THROW_ON_ERROR));

    return $file;
}

it('previews customers without writing', function (): void {
    [$tenantId, $unitId] = customerImportDestination();
    $file = customerImportFile([['source_id' => 'belasis-1', 'name' => 'Cliente teste', 'phone' => '(35) 99999-0001']]);

    $this->artisan('app:import-customers', ['file' => $file, 'tenant' => $tenantId, 'unit' => $unitId])
        ->assertSuccessful()
        ->expectsOutputToContain('1 found');

    expect(Customer::query()->where('tenant_id', $tenantId)->count())->toBe(0);
    unlink($file);
});

it('imports idempotently by tenant, unit and source id', function (): void {
    [$tenantId, $unitId] = customerImportDestination();
    $file = customerImportFile([[
        'source_id' => 'belasis-2',
        'name' => 'Cliente idempotente',
        'phone' => '(35) 99999-0002',
        'birthday' => '1990-05-20',
        'cpf' => 'redacted-in-test',
    ]]);
    $arguments = ['file' => $file, 'tenant' => $tenantId, 'unit' => $unitId, '--write' => true];

    $this->artisan('app:import-customers', $arguments)->assertSuccessful();
    $this->artisan('app:import-customers', $arguments)->assertSuccessful();

    expect(Customer::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->count())->toBe(1)
        ->and(Customer::query()->where('source_id', 'belasis-2')->firstOrFail()->phone_normalized)->toBe('35999990002');
    unlink($file);
});

it('preserves non-promoted Belasis fields and rejects compound values in scalar fields', function (): void {
    [$tenantId, $unitId] = customerImportDestination();
    $file = customerImportFile([[
        'id' => 'belasis-original',
        'source_id' => 'belasis-preserved',
        'name' => 'Cliente preservado',
        'email' => ['invalid' => 'compound'],
        'phone' => ['invalid' => 'compound'],
        'birthday' => ['invalid' => 'compound'],
        'obs' => ['invalid' => 'compound'],
        'active' => true,
        '__typename' => 'Client',
        'avatar_url' => 'https://example.test/avatar',
        'avatar_blurhash' => 'LKO2?U%2Tw=w]~RBVZRi};RPxuwH',
        'name_initials' => 'CP',
        'balance_cents' => 1250,
        'cashback_balance_cents' => 300,
        'has_avatar' => true,
        'panel' => ['appointments' => 2],
        'detail_status' => 'active',
        'client_dependents' => [['name' => 'Dependente']],
    ]]);

    $this->artisan('app:import-customers', ['file' => $file, 'tenant' => $tenantId, 'unit' => $unitId, '--write' => true])
        ->assertSuccessful();

    $customer = Customer::query()->where('source_id', 'belasis-preserved')->firstOrFail();
    expect($customer->email)->toBeNull()
        ->and($customer->phone)->toBeNull()
        ->and($customer->birth_date)->toBeNull()
        ->and($customer->notes)->toBeNull()
        ->and($customer->source_metadata)->toMatchArray([
            'avatar_url' => 'https://example.test/avatar',
            'avatar_blurhash' => 'LKO2?U%2Tw=w]~RBVZRi};RPxuwH',
            'name_initials' => 'CP',
            'balance_cents' => 1250,
            'cashback_balance_cents' => 300,
            'has_avatar' => true,
            'panel' => ['appointments' => 2],
            'detail_status' => 'active',
            'client_dependents' => [['name' => 'Dependente']],
        ])
        ->and($customer->source_metadata)->not->toHaveKeys(['id', 'source_id', 'name', 'email', 'phone', 'phone1', 'birthday', 'obs', 'active', '__typename']);
    unlink($file);
});

it('keeps the same source id isolated between tenants', function (): void {
    [$tenantA, $unitA] = customerImportDestination();
    [$tenantB, $unitB] = customerImportDestination();
    $file = customerImportFile([['source_id' => 'belasis-shared', 'name' => 'Cliente compartilhado', 'phone' => '35999990003']]);

    $this->artisan('app:import-customers', ['file' => $file, 'tenant' => $tenantA, 'unit' => $unitA, '--write' => true])->assertSuccessful();
    $this->artisan('app:import-customers', ['file' => $file, 'tenant' => $tenantB, 'unit' => $unitB, '--write' => true])->assertSuccessful();

    expect(Customer::query()->where('tenant_id', $tenantA)->where('source_id', 'belasis-shared')->count())->toBe(1)
        ->and(Customer::query()->where('tenant_id', $tenantB)->count())->toBe(1);
    unlink($file);
});

it('does not merge an ambiguous phone and reports row failure without leaking personal data', function (): void {
    [$tenantId, $unitId] = customerImportDestination();
    Customer::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'phone' => '35999990004', 'phone_normalized' => '35999990004']);
    Customer::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'phone' => '(35) 99999-0004', 'phone_normalized' => '35999990004']);
    $file = customerImportFile([['source_id' => 'belasis-ambiguous', 'name' => 'Não deve importar', 'phone' => '35999990004']]);
    $report = tempnam(sys_get_temp_dir(), 'customer-report-');

    $this->artisan('app:import-customers', ['file' => $file, 'tenant' => $tenantId, 'unit' => $unitId, '--write' => true, '--report' => $report])
        ->assertSuccessful();

    $reportPayload = json_decode((string) file_get_contents($report), true, 512, JSON_THROW_ON_ERROR);
    expect(Customer::query()->where('source_id', 'belasis-ambiguous')->exists())->toBeFalse()
        ->and($reportPayload['summary']['skipped'])->toBe(1)
        ->and((string) file_get_contents($report))->not->toContain('Não deve importar');
    unlink($file);
    unlink($report);
});

it('rolls back an invalid row and reports it without stopping valid rows', function (): void {
    [$tenantId, $unitId] = customerImportDestination();
    $file = customerImportFile([
        ['source_id' => 'belasis-valid', 'name' => 'Cliente válido', 'phone' => '35999990005'],
        ['source_id' => 'belasis-invalid', 'name' => str_repeat('x', 161), 'phone' => '35999990006'],
    ]);
    $report = tempnam(sys_get_temp_dir(), 'customer-report-');

    $this->artisan('app:import-customers', ['file' => $file, 'tenant' => $tenantId, 'unit' => $unitId, '--write' => true, '--report' => $report])
        ->assertFailed();

    expect(Customer::query()->where('source_id', 'belasis-valid')->exists())->toBeTrue()
        ->and(Customer::query()->where('source_id', 'belasis-invalid')->exists())->toBeFalse();
    $reportPayload = json_decode((string) file_get_contents($report), true, 512, JSON_THROW_ON_ERROR);
    expect($reportPayload['summary']['failed'])->toBe(1);
    unlink($file);
    unlink($report);
});
