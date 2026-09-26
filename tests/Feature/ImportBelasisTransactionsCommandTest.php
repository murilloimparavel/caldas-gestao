<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Customer;
use App\Models\FinancialObligation;
use App\Models\User;
use Illuminate\Support\Str;

/** @return array{0: string, 1: string} */
function transactionImportDestination(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Transaction import '.Str::random(8),
        'slug' => 'transaction-import-'.Str::lower(Str::random(8)),
    ]);

    return [$tenant->getKey(), $tenant->units()->firstOrFail()->getKey()];
}

function transactionImportFile(array $records): string
{
    $file = tempnam(sys_get_temp_dir(), 'transactions-');
    file_put_contents($file, json_encode(['records' => $records], JSON_THROW_ON_ERROR));

    return $file;
}

it('requires all explicit flags and dry-run', function (): void {
    $this->artisan('app:import-belasis-transactions')->assertFailed();
    [$tenantId, $unitId] = transactionImportDestination();
    $file = transactionImportFile([]);

    $this->artisan('app:import-belasis-transactions', ['--tenant-id' => $tenantId, '--unit-id' => $unitId, '--file' => $file])
        ->assertFailed();
    unlink($file);
});

it('previews a valid transaction without writing', function (): void {
    [$tenantId, $unitId] = transactionImportDestination();
    $customer = Customer::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'source_id' => 'client-1']);
    $file = transactionImportFile([['source_id' => 'transaction-1', 'customer' => ['source_id' => 'client-1'], 'date' => '2026-01-10', 'value' => '125.50', 'bill_type' => 'receivable', 'description' => 'Venda teste']]);

    $this->artisan('app:import-belasis-transactions', ['--tenant-id' => $tenantId, '--unit-id' => $unitId, '--file' => $file, '--dry-run' => true])
        ->assertSuccessful()->expectsOutputToContain('1 found');

    expect($customer->exists)->toBeTrue()
        ->and(FinancialObligation::query()->where('source_id', 'transaction-1')->exists())->toBeFalse();
    unlink($file);
});

it('imports the real Belasis transaction format', function (): void {
    [$tenantId, $unitId] = transactionImportDestination();
    Customer::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'source_id' => 'real-client-1']);
    Customer::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'source_id' => 'real-client-2']);
    $file = transactionImportFile([
        [
            'source_id' => 'real-transaction-rec',
            'date' => '2026-02-10',
            'due_date' => '2026-02-10',
            'value_cents' => 12550,
            'status' => 'received',
            'bill_type' => 'rec',
            'historical' => 'Recebimento da comanda C#123',
            'payment' => ['source_id' => 'payment-1', 'name' => 'Pix'],
            'client' => ['source_id' => 'real-client-1', 'name' => 'Cliente sanitizado'],
        ],
        [
            'source_id' => 'real-transaction-pay',
            'date' => '2026-02-11',
            'value_cents' => 8500,
            'status' => 'pending',
            'bill_type' => 'pay',
            'historical' => 'Pagamento de fornecedor',
            'payment' => ['source_id' => 'payment-2', 'name' => 'Cartão'],
            'client' => ['source_id' => 'real-client-2', 'name' => 'Outro cliente sanitizado'],
        ],
    ]);

    $arguments = [
        '--tenant-id' => $tenantId,
        '--unit-id' => $unitId,
        '--file' => $file,
        '--dry-run' => true,
        '--write' => true,
    ];

    $this->artisan('app:import-belasis-transactions', $arguments)->assertSuccessful();

    $receivable = FinancialObligation::query()->where('source_id', 'real-transaction-rec')->firstOrFail();
    $payable = FinancialObligation::query()->where('source_id', 'real-transaction-pay')->firstOrFail();
    expect($receivable->type)->toBe('receivable')
        ->and($receivable->amount_cents)->toBe(12550)
        ->and($receivable->description)->toBe('Recebimento da comanda C#123')
        ->and($receivable->payment_method)->toBe('Pix')
        ->and($payable->type)->toBe('payable')
        ->and($payable->amount_cents)->toBe(8500)
        ->and($payable->description)->toBe('Pagamento de fornecedor')
        ->and($payable->payment_method)->toBe('Cartão');

    unlink($file);
});

it('creates and replays a transaction by source id', function (): void {
    [$tenantId, $unitId] = transactionImportDestination();
    Customer::factory()->create(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'source_id' => 'client-2']);
    $file = transactionImportFile([['source_id' => 'transaction-2', 'customer' => ['source_id' => 'client-2'], 'date' => '2026-01-11', 'value' => 80, 'bill_type' => 'receivable', 'status' => 'paid']]);
    $arguments = ['--tenant-id' => $tenantId, '--unit-id' => $unitId, '--file' => $file, '--dry-run' => true, '--write' => true];

    $this->artisan('app:import-belasis-transactions', $arguments)->assertSuccessful();
    $this->artisan('app:import-belasis-transactions', $arguments)->assertSuccessful();
    expect(FinancialObligation::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('source_id', 'transaction-2')->count())->toBe(1)
        ->and(FinancialObligation::query()->where('source_id', 'transaction-2')->value('amount_cents'))->toBe(8000);
    unlink($file);
});

it('leaves transactions with unresolved customers pending', function (): void {
    [$tenantId, $unitId] = transactionImportDestination();
    $file = transactionImportFile([['source_id' => 'transaction-pending', 'customer' => ['source_id' => 'missing'], 'date' => '2026-01-12', 'value' => 30, 'bill_type' => 'payable']]);

    $this->artisan('app:import-belasis-transactions', ['--tenant-id' => $tenantId, '--unit-id' => $unitId, '--file' => $file, '--dry-run' => true])
        ->assertSuccessful()->expectsOutputToContain('1 pending');

    expect(FinancialObligation::query()->where('source_id', 'transaction-pending')->exists())->toBeFalse();
    unlink($file);
});
