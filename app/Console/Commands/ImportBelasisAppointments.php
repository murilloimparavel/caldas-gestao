<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Signature('app:import-belasis-appointments
    {--tenant-id= : Explicit destination tenant UUID}
    {--unit-id= : Explicit destination unit UUID}
    {--file= : Sanitized Belasis appointments JSON file}
    {--dry-run : Validate and preview without persisting changes (the default)}
    {--write : Persist validated changes}
    {--report= : Optional JSON report output path}')]
#[Description('Import sanitized Belasis appointments idempotently')]
final class ImportBelasisAppointments extends Command
{
    /** @var array{found:int,created:int,updated:int,skipped:int,failed:int,pending:int} */
    private array $summary = ['found' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'pending' => 0];

    /** @var list<array<string,mixed>> */
    private array $rows = [];

    public function handle(): int
    {
        try {
            if ($this->option('write') && $this->option('dry-run')) {
                throw new RuntimeException('Use either --write or --dry-run, not both.');
            }
            $tenantId = $this->uuidOption('tenant-id', 'tenant');
            $unitId = $this->uuidOption('unit-id', 'unit');
            if (! Tenant::query()->whereKey($tenantId)->exists() || ! Unit::query()->whereKey($unitId)->where('tenant_id', $tenantId)->exists()) {
                throw new RuntimeException('The destination tenant and unit must exist and belong together.');
            }
            $records = $this->readRecords((string) $this->option('file'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->summary['found'] = count($records);
        foreach ($records as $position => $record) {
            $this->process($record, $position + 1, $tenantId, $unitId);
        }
        $this->writeReport();
        $this->components->info(sprintf('Appointments: %d found, %d created, %d updated, %d skipped, %d pending, %d failed (%s).', $this->summary['found'], $this->summary['created'], $this->summary['updated'], $this->summary['skipped'], $this->summary['pending'], $this->summary['failed'], $this->option('write') ? 'write' : 'dry-run'));

        return $this->summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<array<string,mixed>> */
    private function readRecords(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Import file is not readable: {$path}");
        }
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ! isset($payload['records']) || ! is_array($payload['records'])) {
            throw new RuntimeException('Import file must contain a records array.');
        }

        return array_values(array_filter($payload['records'], 'is_array'));
    }

    /** @param array<string,mixed> $record */
    private function process(array $record, int $position, string $tenantId, string $unitId): void
    {
        $sourceId = $this->stringValue($record['source_id'] ?? null);
        try {
            if ($sourceId === null) {
                throw new RuntimeException('missing_source_id');
            }
            $customer = $this->resolveCustomer($tenantId, $unitId, $record['client_source_id'] ?? null);
            $service = $this->resolveService($tenantId, $unitId, $record['service_source_id'] ?? null);
            $professional = $this->resolveProfessional($tenantId, $unitId, $record['professional_source_id'] ?? null);
            $startsAt = CarbonImmutable::parse((string) ($record['starts_at'] ?? ''));
            $endsAt = CarbonImmutable::parse((string) ($record['ends_at'] ?? ''));
            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw new RuntimeException('invalid_time_range');
            }
            $status = $this->status($record['status'] ?? null);
            $existing = Appointment::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('source_id', $sourceId)->first();
            $action = $existing === null ? 'created' : 'updated';
            if (! $this->option('write')) {
                $this->summary[$action]++;
                $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => 'would_'.$action];

                return;
            }
            DB::transaction(function () use ($existing, $record, $sourceId, $customer, $service, $professional, $startsAt, $endsAt, $status, $tenantId, $unitId): void {
                $appointment = $existing ?? new Appointment(['id' => (string) Str::uuid7()]);
                $appointment->forceFill(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'source_id' => $sourceId, 'customer_id' => $customer->getKey(), 'professional_id' => $professional->getKey(), 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'timezone' => 'America/Sao_Paulo', 'status' => $status, 'source' => 'imported', 'notes' => $this->stringValue($record['notes'] ?? null), 'cancelled_at' => $status === 'cancelled' ? $endsAt : null, 'lock_version' => $existing === null ? 0 : $existing->lock_version + 1])->save();
                AppointmentItem::query()->updateOrCreate(['tenant_id' => $tenantId, 'unit_id' => $unitId, 'appointment_id' => $appointment->getKey(), 'position' => 1], ['id' => (string) Str::uuid7(), 'service_id' => $service->getKey(), 'professional_id' => $professional->getKey(), 'service_name_snapshot' => $service->name, 'duration_minutes' => max(1, $startsAt->diffInMinutes($endsAt)), 'price_cents' => $service->price_cents]);
            });
            $this->summary[$action]++;
            $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => $action];
        } catch (Throwable $exception) {
            $reason = $exception->getMessage();
            $pending = str_starts_with($reason, 'missing_') || $reason === 'invalid_time_range';
            $this->summary[$pending ? 'pending' : 'failed']++;
            $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => $pending ? 'pending' : 'failed', 'reason' => $reason];
        }
    }

    private function uuidOption(string $key, string $label): string
    {
        $raw = $this->option($key);
        $value = is_scalar($raw) ? trim((string) $raw) : '';
        if (! Str::isUuid($value)) {
            throw new RuntimeException("A valid {$label} UUID is required.");
        }

        return $value;
    }

    private function status(mixed $value): string
    {
        return match (Str::lower(trim((string) $value))) {
            'completed', 'concluded', 'done' => 'completed',
            'cancelled', 'canceled' => 'cancelled',
            'no_show', 'noshow', 'no-show' => 'no_show',
            'scheduled' => 'scheduled',
            default => 'confirmed',
        };
    }

    private function resolveCustomer(string $tenantId, string $unitId, mixed $sourceId): Customer
    {
        $source = $this->stringValue($sourceId);
        $record = $source === null ? null : Customer::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('source_id', $source)->first();
        if ($record === null) {
            throw new RuntimeException('missing_customer_relation');
        }

        return $record;
    }

    private function resolveService(string $tenantId, string $unitId, mixed $sourceId): Service
    {
        $source = $this->stringValue($sourceId);
        $record = $source === null ? null : Service::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('source_id', $source)->first();
        if ($record === null) {
            throw new RuntimeException('missing_service_relation');
        }

        return $record;
    }

    private function resolveProfessional(string $tenantId, string $unitId, mixed $sourceId): Professional
    {
        $source = $this->stringValue($sourceId);
        $record = $source === null ? null : Professional::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('source_id', $source)->first();
        if ($record === null) {
            throw new RuntimeException('missing_professional_relation');
        }

        return $record;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function writeReport(): void
    {
        $path = $this->option('report');
        if (is_string($path) && $path !== '') {
            file_put_contents($path, json_encode(['summary' => $this->summary, 'appointments' => $this->rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }
    }
}
