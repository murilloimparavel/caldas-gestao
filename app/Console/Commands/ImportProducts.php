<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Signature('app:import-products
    {--tenant-id= : Explicit destination tenant UUID}
    {--unit-id= : Explicit destination unit UUID}
    {--file= : JSON file containing the sanitized Belasis products export}
    {--dry-run : Validate and preview without persisting changes (the default)}
    {--write : Persist validated changes}
    {--chunk=100 : Number of rows processed per chunk}
    {--report= : Optional JSON report output path}')]
#[Description('Import sanitized Belasis products idempotently')]
final class ImportProducts extends Command
{
    /** @var array{found:int,created:int,updated:int,skipped:int,failed:int,pending:int} */
    private array $summary = [
        'found' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'pending' => 0,
    ];

    /** @var list<array{row:int,source_id:?string,status:string,reason?:string,error?:string}> */
    private array $rows = [];

    /** @var array<string,true> */
    private array $seenSourceIds = [];

    public function handle(): int
    {
        $this->summary = ['found' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'pending' => 0];
        $this->rows = [];
        $this->seenSourceIds = [];

        try {
            $tenantId = $this->validatedUuid((string) $this->option('tenant-id'), 'tenant');
            $unitId = $this->validatedUuid((string) $this->option('unit-id'), 'unit');
            $this->assertDestination($tenantId, $unitId);

            if ($this->option('write') && $this->option('dry-run')) {
                throw new RuntimeException('Use either --write or --dry-run, not both.');
            }

            $products = $this->readProducts((string) $this->option('file'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->summary['found'] = count($products);
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));
        foreach (array_chunk($products, $chunkSize) as $chunkIndex => $chunk) {
            foreach ($chunk as $position => $source) {
                $this->processRow($source, $tenantId, $unitId, ($chunkIndex * $chunkSize) + $position + 1);
            }
        }

        $this->writeReport();
        $this->components->info(sprintf(
            'Products: %d found, %d created, %d updated, %d skipped, %d pending, %d failed (%s).',
            $this->summary['found'],
            $this->summary['created'],
            $this->summary['updated'],
            $this->summary['skipped'],
            $this->summary['pending'],
            $this->summary['failed'],
            $this->option('write') ? 'write' : 'dry-run',
        ));

        return $this->summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string,mixed> $source */
    private function processRow(array $source, string $tenantId, string $unitId, int $position): void
    {
        $sourceId = $this->nullableString($source['source_id'] ?? null);
        $name = $this->nullableString($source['name'] ?? null);
        if ($sourceId === null || $name === null) {
            $this->skip($position, $sourceId, 'missing_source_id_or_name');

            return;
        }
        if (isset($this->seenSourceIds[$sourceId])) {
            $this->skip($position, $sourceId, 'duplicate_source_id_in_file');

            return;
        }
        $this->seenSourceIds[$sourceId] = true;

        try {
            $categoryId = $this->resolveCategoryId($source['category_name'] ?? null, $tenantId, $unitId, $position, $sourceId);
            $data = $this->mapSource($source, $sourceId, $name, $categoryId);
            $existing = Product::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('source_id', $sourceId)
                ->first();

            if (! $this->option('write')) {
                $this->summary[$existing === null ? 'created' : 'updated']++;
                $this->rows[] = [
                    'row' => $position,
                    'source_id' => $sourceId,
                    'status' => $existing === null ? 'would_create' : 'would_update',
                ];

                return;
            }

            DB::transaction(function () use ($data, $existing, $tenantId, $unitId, $position, $sourceId): void {
                if ($existing === null) {
                    Product::query()->create([
                        ...$data,
                        'id' => (string) Str::uuid7(),
                        'tenant_id' => $tenantId,
                        'unit_id' => $unitId,
                    ]);
                    $this->summary['created']++;
                    $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => 'created'];

                    return;
                }

                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->forceFill([...$data, 'lock_version' => $existing->lock_version + 1])->save();
                $this->summary['updated']++;
                $this->rows[] = ['row' => $position, 'source_id' => $sourceId, 'status' => 'updated'];
            }, 5);
        } catch (Throwable $exception) {
            $this->summary['failed']++;
            $this->rows[] = [
                'row' => $position,
                'source_id' => $sourceId,
                'status' => 'failed',
                'reason' => 'row_rolled_back',
                'error' => $exception::class,
            ];
        }
    }

    /**
     * @param  array<string,mixed>  $source
     * @return array{source_id:string,name:string,category_id:?string,sale_price_cents:int,is_active:bool}
     */
    private function mapSource(array $source, string $sourceId, string $name, ?string $categoryId): array
    {
        if (mb_strlen($sourceId) > 191) {
            throw new RuntimeException('Product source_id exceeds the database limit.');
        }
        if (mb_strlen($name) > 160) {
            throw new RuntimeException('Product name exceeds the database limit.');
        }
        if (! array_key_exists('price_cents', $source)) {
            throw new RuntimeException('Product price_cents is required.');
        }

        return [
            'source_id' => $sourceId,
            'name' => $name,
            'category_id' => $categoryId,
            'sale_price_cents' => $this->integerCents($source['price_cents']),
            'is_active' => $this->isActive($source['status'] ?? 'active'),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function readProducts(string $path): array
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Import file is not readable.');
        }

        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (is_array($payload) && isset($payload['products']) && is_array($payload['products'])) {
            $payload = $payload['products'];
        }
        if (! is_array($payload)) {
            throw new RuntimeException('Import file must contain a JSON array or products array.');
        }

        return array_values(array_filter($payload, 'is_array'));
    }

    private function resolveCategoryId(mixed $name, string $tenantId, string $unitId, int $position, string $sourceId): ?string
    {
        $categoryName = $this->nullableString($name);
        if ($categoryName === null) {
            return null;
        }

        $categories = Category::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($categoryName)])
            ->get(['id']);
        if ($categories->count() === 1) {
            return (string) $categories->first()->getKey();
        }

        $this->summary['pending']++;
        $this->rows[] = [
            'row' => $position,
            'source_id' => $sourceId,
            'status' => 'pending',
            'reason' => $categories->isEmpty() ? 'missing_category_relation' : 'ambiguous_category_relation',
        ];

        return null;
    }

    private function isActive(mixed $status): bool
    {
        if (is_bool($status)) {
            return $status;
        }
        if (! is_scalar($status)) {
            throw new RuntimeException('Product status must be scalar.');
        }

        return ! in_array(Str::lower(trim((string) $status)), ['inactive', 'disabled', 'archived', 'false', '0'], true);
    }

    private function integerCents(mixed $value): int
    {
        $cents = filter_var($value, FILTER_VALIDATE_INT);
        if ($cents === false || $cents < 0) {
            throw new RuntimeException('Product price_cents must be a non-negative integer.');
        }

        return $cents;
    }

    private function assertDestination(string $tenantId, string $unitId): void
    {
        if (! Tenant::query()->whereKey($tenantId)->exists() || ! Unit::query()->whereKey($unitId)->where('tenant_id', $tenantId)->exists()) {
            throw new RuntimeException('The explicit tenant and unit must exist and belong together.');
        }
    }

    private function validatedUuid(string $value, string $label): string
    {
        if (! Str::isUuid($value)) {
            throw new RuntimeException("The {$label} must be a valid UUID.");
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || (! is_scalar($value) && ! $value instanceof \Stringable)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function skip(int $position, ?string $sourceId, string $reason): void
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

        $written = file_put_contents($path, json_encode([
            'mode' => $this->option('write') ? 'write' : 'dry-run',
            'summary' => $this->summary,
            'rows' => $this->rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if ($written === false) {
            throw new RuntimeException("Unable to write import report: {$path}");
        }
    }
}
