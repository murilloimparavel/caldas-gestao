<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\FinancialObligation;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Signature('app:import-belasis-transactions
    {--tenant-id= : Explicit destination tenant UUID}
    {--unit-id= : Explicit destination unit UUID}
    {--file= : JSON file containing the sanitized Belasis transactions export}
    {--dry-run : Validate and preview without persisting changes}
    {--write : Persist validated changes; --dry-run remains required as a safety gate}
    {--chunk=100 : Number of rows processed per chunk}')]
#[Description('Import sanitized Belasis financial transactions idempotently')]
final class ImportBelasisTransactions extends Command
{
    /** @var array<string, int> */
    private array $summary = ['found' => 0, 'exported' => 0, 'ignored' => 0, 'failed' => 0, 'pending' => 0];

    /** @var array<string, true> */
    private array $seenSourceIds = [];

    public function handle(): int
    {
        $this->summary = ['found' => 0, 'exported' => 0, 'ignored' => 0, 'failed' => 0, 'pending' => 0];
        $this->seenSourceIds = [];

        if (! $this->option('dry-run')) {
            $this->components->error('The --dry-run flag is required.');

            return self::FAILURE;
        }

        try {
            $tenantId = $this->validatedUuid((string) $this->option('tenant-id'));
            $unitId = $this->validatedUuid((string) $this->option('unit-id'));
            $this->assertDestination($tenantId, $unitId);
            $records = $this->readRecords((string) $this->option('file'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->summary['found'] = count($records);
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));
        foreach (array_chunk($records, $chunkSize) as $chunk) {
            foreach ($chunk as $position => $record) {
                $this->processRecord($record, $tenantId, $unitId, $position + 1);
            }
        }

        $this->components->info(sprintf(
            'Transactions: %d found, %d exported, %d ignored, %d failed, %d pending (%s).',
            $this->summary['found'], $this->summary['exported'], $this->summary['ignored'],
            $this->summary['failed'], $this->summary['pending'],
            $this->option('write') ? 'write' : 'dry-run',
        ));

        return $this->summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string, mixed> $record */
    private function processRecord(array $record, string $tenantId, string $unitId, int $position): void
    {
        $sourceId = $this->scalarString($record['source_id'] ?? $record['id'] ?? null);
        if ($sourceId === null) {
            $this->ignored($position, null, 'missing_source_id');

            return;
        }
        if (isset($this->seenSourceIds[$sourceId])) {
            $this->ignored($position, $sourceId, 'duplicate_source_id_in_file');

            return;
        }
        $this->seenSourceIds[$sourceId] = true;

        try {
            $mapped = $this->mapRecord($record, $sourceId, $tenantId, $unitId);
            if ($mapped === null) {
                $this->summary['pending']++;

                return;
            }

            $existing = FinancialObligation::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('source_id', $sourceId)
                ->first();
            if (! $this->option('write')) {
                $this->summary['exported']++;

                return;
            }

            DB::transaction(function () use ($mapped, $existing): void {
                if ($existing === null) {
                    FinancialObligation::query()->forceCreate($mapped + ['id' => (string) Str::uuid7()]);
                } else {
                    $existing->forceFill($mapped + ['lock_version' => $existing->lock_version + 1])->save();
                }
                $this->summary['exported']++;
            }, 5);
        } catch (Throwable $exception) {
            $this->summary['failed']++;
        }
    }

    /** @param array<string, mixed> $record
     * @return array<string, mixed>|null
     */
    private function mapRecord(array $record, string $sourceId, string $tenantId, string $unitId): ?array
    {
        $date = $this->date($record['date'] ?? $record['due_date'] ?? null);
        $hasValueInCents = array_key_exists('value_cents', $record);
        $amount = $this->amountCents($record['value_cents'] ?? $record['value'] ?? null, $hasValueInCents);
        $type = match (mb_strtolower($this->scalarString($record['bill_type'] ?? null) ?? '')) {
            'rec' => 'receivable',
            'pay' => 'payable',
            'receivable' => 'receivable',
            'payable' => 'payable',
            default => null,
        };
        if ($date === null || $amount === null || ! in_array($type, ['payable', 'receivable'], true)) {
            throw new \RuntimeException('Record has invalid source_id, date, value or bill_type.');
        }

        $customerSourceId = $this->customerSourceId($record);
        $customerId = null;
        if ($customerSourceId !== null) {
            $customerId = Customer::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('source_id', $customerSourceId)->value('id');
        }
        if ($customerId === null) {
            return null;
        }

        $description = $this->description($record) ?? 'Belasis transaction '.$sourceId;
        if (mb_strlen($description) > 255) {
            throw new \RuntimeException('Transaction description exceeds the database limit.');
        }
        $status = $this->status($record['status'] ?? null);
        $paidDate = $status === 'paid' ? ($this->date($record['paid_date'] ?? $record['payment_date'] ?? $record['date'] ?? null) ?? $date) : null;

        return [
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'source_id' => $sourceId,
            'type' => $type,
            'customer_id' => $customerId,
            'description' => $description,
            'amount_cents' => $amount,
            'due_date' => $date,
            'paid_date' => $paidDate,
            'status' => $status,
            'payment_method' => $this->paymentMethod($record),
            'source_metadata' => json_encode($this->metadata($record), JSON_THROW_ON_ERROR),
            'lock_version' => 1,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function readRecords(string $path): array
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException('Import file is not readable.');
        }
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $records = is_array($payload) && isset($payload['records']) ? $payload['records'] : $payload;
        if (! is_array($records)) {
            throw new \RuntimeException('Import file must contain a JSON array or records array.');
        }

        return array_values(array_filter($records, 'is_array'));
    }

    private function assertDestination(string $tenantId, string $unitId): void
    {
        if (! Tenant::query()->whereKey($tenantId)->exists() || ! Unit::query()->whereKey($unitId)->where('tenant_id', $tenantId)->exists()) {
            throw new \RuntimeException('The explicit tenant and unit must exist and belong together.');
        }
    }

    private function validatedUuid(string $value): string
    {
        if (! Str::isUuid($value)) {
            throw new \RuntimeException('Tenant and unit must be valid UUIDs.');
        }

        return $value;
    }

    /** @param array<string, mixed> $record */
    private function customerSourceId(array $record): ?string
    {
        foreach ([$record['customer'] ?? null, $record['client'] ?? null] as $relation) {
            if (is_array($relation)) {
                $sourceId = $this->scalarString($relation['source_id'] ?? null);
                if ($sourceId !== null) {
                    return $sourceId;
                }
            }
        }
        foreach (['customer_source_id', 'client_source_id'] as $key) {
            $sourceId = $this->scalarString($record[$key] ?? null);
            if ($sourceId !== null) {
                return $sourceId;
            }
        }

        return null;
    }

    private function amountCents(mixed $value, bool $alreadyInCents = false): ?int
    {
        if ($alreadyInCents) {
            if (is_int($value) && $value > 0 && $value <= 2147483647) {
                return $value;
            }
            if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
                $cents = (int) trim($value);

                return $cents > 0 && $cents <= 2147483647 ? $cents : null;
            }

            return null;
        }

        $value = $this->scalarString($value);
        if ($value === null || ! preg_match('/^\d+(?:[.,]\d{1,2})?$/', $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(preg_split('/[.,]/', $value, 2) ?: [], 2, '0');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $cents > 0 && $cents <= 2147483647 ? $cents : null;
    }

    /** @param array<string, mixed> $record */
    private function paymentMethod(array $record): ?string
    {
        $paymentMethod = $this->scalarString($record['payment_method'] ?? null);
        if ($paymentMethod !== null) {
            return $paymentMethod;
        }

        $payment = $record['payment'] ?? null;

        return is_array($payment) ? $this->scalarString($payment['name'] ?? null) : null;
    }

    /** @param array<string, mixed> $record */
    private function description(array $record): ?string
    {
        foreach (['description', 'title', 'historical'] as $key) {
            $description = $this->scalarString($record[$key] ?? null);
            if ($description !== null) {
                return $description;
            }
        }

        return null;
    }

    private function date(mixed $value): ?string
    {
        $value = $this->scalarString($value);
        if ($value === null) {
            return null;
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function status(mixed $value): string
    {
        return match (mb_strtolower($this->scalarString($value) ?? 'pending')) {
            'paid', 'received', 'settled' => 'paid',
            'cancelled', 'canceled' => 'cancelled',
            default => 'pending',
        };
    }

    /** @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function metadata(array $record): array
    {
        $excluded = ['source_id', 'id', 'date', 'due_date', 'value', 'bill_type', 'description', 'title', 'status', 'paid_date', 'payment_date', 'customer', 'client', 'customer_source_id', 'client_source_id', 'email', 'phone', 'cpf', 'password', 'token', 'headers', 'cookies', 'authorization'];
        $metadata = [];
        foreach ($record as $key => $value) {
            if (! in_array(mb_strtolower((string) $key), $excluded, true)) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    private function scalarString(mixed $value): ?string
    {
        if ($value === null || (! is_scalar($value) && ! $value instanceof \Stringable)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function ignored(int $position, ?string $sourceId, string $reason): void
    {
        $this->summary['ignored']++;
    }
}
