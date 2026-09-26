<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Signature('app:import-customers
    {file : JSON file containing the Belasis clients export}
    {tenant : Explicit destination tenant UUID}
    {unit : Explicit destination unit UUID}
    {--write : Persist changes; without this flag the command only previews}
    {--chunk=100 : Number of rows processed per chunk}
    {--report= : Optional JSON report output path}')]
#[Description('Safely import customers from a sanitized Belasis export')]
final class ImportCustomers extends Command
{
    /** @var array<string, int> */
    private array $summary = ['found' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, true> */
    private array $seenSourceIds = [];

    public function handle(): int
    {
        $this->summary = ['found' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $this->rows = [];
        $this->seenSourceIds = [];
        $tenantId = $this->validatedUuid((string) $this->argument('tenant'));
        $unitId = $this->validatedUuid((string) $this->argument('unit'));
        $this->assertDestination($tenantId, $unitId);
        $clients = $this->readClients((string) $this->argument('file'));
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));
        $this->summary['found'] = count($clients);

        foreach (array_chunk($clients, $chunkSize) as $chunk) {
            foreach ($chunk as $position => $source) {
                $this->processRow($source, $tenantId, $unitId, $position + 1);
            }
        }

        $this->writeReport();
        $this->components->info(sprintf(
            'Customers: %d found, %d created, %d updated, %d skipped, %d failed%s.',
            $this->summary['found'], $this->summary['created'], $this->summary['updated'],
            $this->summary['skipped'], $this->summary['failed'],
            $this->option('write') ? '' : ' (dry-run)',
        ));

        return $this->summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string, mixed> $source */
    private function processRow(array $source, string $tenantId, string $unitId, int $position): void
    {
        $sourceId = $this->nullableString($source['source_id'] ?? $source['id'] ?? null) ?? '';
        $name = $this->nullableString($source['name'] ?? null) ?? '';
        if ($sourceId === '' || $name === '') {
            $this->skip($position, 'missing_source_id_or_name', $sourceId);

            return;
        }
        if (isset($this->seenSourceIds[$sourceId])) {
            $this->skip($position, 'duplicate_source_id_in_file', $sourceId);

            return;
        }
        $this->seenSourceIds[$sourceId] = true;

        try {
            $data = $this->mapSource($source, $sourceId, $name);
            $existing = Customer::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('source_id', $sourceId)->first();
            $phoneMatch = $data['phone_normalized'] === null ? collect() : Customer::query()
                ->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('phone_normalized', $data['phone_normalized'])
                ->when($existing !== null, fn ($query) => $query->where($existing->getQualifiedKeyName(), '<>', $existing->getKey()))->limit(2)->get();
            if ($phoneMatch->count() > 0) {
                $this->skip($position, $phoneMatch->count() > 1 ? 'ambiguous_phone_match' : 'phone_conflict', $sourceId);

                return;
            }
            if (! $this->option('write')) {
                $this->summary[$existing === null ? 'created' : 'updated']++;
                $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => $existing === null ? 'would_create' : 'would_update'];

                return;
            }

            DB::transaction(function () use ($data, $existing, $tenantId, $unitId, $position, $sourceId): void {
                if ($existing === null) {
                    Customer::query()->create([...$data, 'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'unit_id' => $unitId, 'last_activity_at' => now(), 'lock_version' => 0]);
                    $this->summary['created']++;
                    $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => 'created'];

                    return;
                }
                $existing->forceFill($data + ['lock_version' => $existing->lock_version + 1])->save();
                $this->summary['updated']++;
                $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => 'updated'];
            }, 5);
        } catch (Throwable $exception) {
            $this->summary['failed']++;
            $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => 'failed', 'reason' => 'row_rolled_back', 'error' => class_basename($exception)];
        }
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{source_id: string, name: string, email: ?string, phone: ?string, phone_normalized: ?string, birth_date: ?string, notes: ?string, source_metadata: ?array<string, mixed>, status: string}
     */
    private function mapSource(array $source, string $sourceId, string $name): array
    {
        if (mb_strlen($name) > 160) {
            throw new \RuntimeException('Customer name exceeds the database limit.');
        }
        $phone = $this->nullableString($source['phone'] ?? $source['phone1'] ?? null);
        if ($phone !== null && mb_strlen($phone) > 40) {
            throw new \RuntimeException('Customer phone exceeds the database limit.');
        }
        $metadata = [];
        $promotedKeys = ['id', 'source_id', 'name', 'email', 'phone', 'phone1', 'birthday', 'obs', 'active', '__typename', 'phone_normalized', 'birth_date', 'notes', 'status'];
        foreach ($source as $key => $value) {
            if (! in_array($key, $promotedKeys, true)) {
                $metadata[$key] = $value;
            }
        }
        $birthday = $this->nullableString($source['birthday'] ?? null);

        return [
            'source_id' => $sourceId,
            'name' => $name,
            'email' => $this->nullableString($source['email'] ?? null),
            'phone' => $phone,
            'phone_normalized' => $this->normalizePhone($phone),
            'birth_date' => $this->birthDate($birthday),
            'notes' => $this->nullableString($source['obs'] ?? null),
            'source_metadata' => $metadata === [] ? null : $metadata,
            'status' => filter_var($source['active'] ?? true, FILTER_VALIDATE_BOOL) ? 'active' : 'inactive',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function readClients(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException('Import file is not readable.');
        }
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (isset($payload['clients']) && is_array($payload['clients'])) {
            $payload = $payload['clients'];
        }
        if (! is_array($payload)) {
            throw new \RuntimeException('Import file must contain a JSON array or clients array.');
        }

        return array_values(array_filter($payload, 'is_array'));
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

    private function normalizePhone(?string $phone): ?string
    {
        $digits = $phone === null ? null : preg_replace('/\D+/', '', $phone);

        return $digits === '' ? null : $digits;
    }

    private function birthDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function skip(int $position, string $reason, string $sourceId): void
    {
        $this->summary['skipped']++;
        $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => 'skipped', 'reason' => $reason];
    }

    private function writeReport(): void
    {
        $path = $this->option('report');
        if (! is_string($path) || $path === '') {
            return;
        }
        file_put_contents($path, json_encode(['mode' => $this->option('write') ? 'write' : 'dry-run', 'summary' => $this->summary, 'rows' => $this->rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
