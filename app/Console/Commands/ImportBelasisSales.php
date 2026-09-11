<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

final class BelasisPendingRelation extends RuntimeException {}

#[Signature('app:import-belasis-sales
    {--tenant-id= : Explicit destination tenant UUID}
    {--unit-id= : Explicit destination unit UUID}
    {--file= : Sanitized Belasis sales JSON file}
    {--sale-category-id= : Explicit active destination sale category UUID}
    {--dry-run : Validate and report without writing}
    {--report= : Optional JSON report output path}')]
#[Description('Import sanitized Belasis sales into a tenant and unit')]
final class ImportBelasisSales extends Command
{
    /** @var array{found:int,created:int,updated:int,skipped:int,failed:int,pending:int,payments_pending:int} */
    private array $summary = [
        'found' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'pending' => 0,
        'payments_pending' => 0,
    ];

    /** @var list<array{row:int,status:string,reason?:string,error?:string,payment_status?:string}> */
    private array $rows = [];

    public function handle(): int
    {
        try {
            $tenantId = $this->validatedUuid((string) $this->option('tenant-id'), 'tenant');
            $unitId = $this->validatedUuid((string) $this->option('unit-id'), 'unit');
            $categoryId = $this->validatedUuid((string) $this->option('sale-category-id'), 'sale category');
            $this->assertDestination($tenantId, $unitId);

            $category = SaleCategory::query()
                ->whereKey($categoryId)
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('is_active', true)
                ->first();
            if ($category === null) {
                throw new RuntimeException('The sale category must be active and belong to the destination tenant and unit.');
            }

            $sales = $this->readSales((string) $this->option('file'));
        } catch (Throwable) {
            $this->components->error('Belasis sales import validation failed.');

            return self::FAILURE;
        }
        $this->summary['found'] = count($sales);

        foreach ($sales as $position => $record) {
            $this->processRecord($record, $position + 1, $tenantId, $unitId, $category);
        }

        $this->components->info(sprintf(
            'Belasis sales: %d found, %d created, %d updated, %d skipped, %d pending, %d failed, %d payments pending scope.',
            $this->summary['found'],
            $this->summary['created'],
            $this->summary['updated'],
            $this->summary['skipped'],
            $this->summary['pending'],
            $this->summary['failed'],
            $this->summary['payments_pending'],
        ));
        $this->writeReport();

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $record */
    private function processRecord(array $record, int $position, string $tenantId, string $unitId, SaleCategory $category): void
    {
        try {
            $mapped = $this->mapSale($record, $tenantId, $unitId, $category);
        } catch (BelasisPendingRelation $exception) {
            $this->summary['pending']++;
            $this->addRow($position, 'pending', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->summary['failed']++;
            $this->addRow($position, 'failed', 'invalid_sale', $exception::class);

            return;
        }

        if ($mapped['payment_scope'] !== null) {
            $this->summary['payments_pending'] += $mapped['payment_scope']['count'];
        }

        $existing = Sale::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('source_id', $mapped['sale']['source_id'])
            ->exists();
        $status = $existing ? 'updated' : 'created';
        $paymentStatus = $mapped['payment_scope'] === null ? null : 'pending_scope';

        if ((bool) $this->option('dry-run')) {
            $this->summary[$status]++;
            $this->addRow($position, 'would_'.$status, null, null, $paymentStatus);

            return;
        }

        try {
            DB::transaction(function () use ($mapped, $tenantId, $unitId): void {
                $sale = Sale::withTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('source_id', $mapped['sale']['source_id'])
                    ->lockForUpdate()
                    ->first();

                if ($sale === null) {
                    $sale = Sale::query()->forceCreate($mapped['sale']);
                } else {
                    if ($sale->trashed()) {
                        $sale->restore();
                    }
                    $sale->forceFill([
                        ...$mapped['sale'],
                        'lock_version' => ((int) $sale->lock_version) + 1,
                    ])->save();
                }

                $this->syncItems($sale, $mapped['items'], $tenantId, $unitId);
            }, 5);
        } catch (Throwable $exception) {
            $this->summary['failed']++;
            $this->addRow($position, 'failed', 'transaction_rolled_back', $exception::class, $paymentStatus);

            return;
        }

        $this->summary[$status]++;
        $this->addRow($position, $status, null, null, $paymentStatus);
    }

    /**
     * @return array{sale:array<string,mixed>,items:list<array<string,mixed>>,payment_scope:array<string,mixed>|null}
     */
    private function mapSale(array $record, string $tenantId, string $unitId, SaleCategory $category): array
    {
        $sourceId = $this->sourceId($record['source_id'] ?? null, 'missing_sale_source_id');
        if (! array_key_exists('finished', $record) || ! is_bool($record['finished'])) {
            throw new RuntimeException('invalid_finished');
        }
        if (! array_key_exists('client_source_id', $record)) {
            throw new RuntimeException('missing_client_source_id');
        }

        $customerId = null;
        $clientSourceId = $this->scalarString($record['client_source_id']);
        if ($clientSourceId !== null) {
            $customerId = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('source_id', $clientSourceId)
                ->value('id');
            if ($customerId === null) {
                throw new BelasisPendingRelation('missing_customer_relation');
            }
        }

        $items = [];
        $itemTypes = [];
        foreach ($record['items'] as $item) {
            $mappedItem = $this->mapItem($item, $tenantId, $unitId);
            $items[] = $mappedItem;
            $itemTypes[] = $mappedItem['item_type'];
        }

        $itemTypes = array_values(array_unique($itemTypes));
        if ($category->type !== 'mixed' && count(array_diff($itemTypes, [$category->type])) > 0) {
            throw new RuntimeException('sale_category_type_mismatch');
        }

        $itemsTotal = array_sum(array_map(static fn (array $item): int => $item['total_cents'], $items));
        $discountCents = $this->optionalAmount($record, 'discount_cents') ?? 0;
        $finalCents = $this->requiredAmount($record, 'total_cents');
        if ($itemsTotal - $discountCents !== $finalCents) {
            throw new RuntimeException('sale_totals_incoherent');
        }

        $paymentScope = $this->paymentScope($record['payments']);
        $metadata = $this->saleMetadata($record, $paymentScope);
        $date = $this->sourceDate($record['date'] ?? null);
        $updatedAt = array_key_exists('detail_updated_at', $record) && $record['detail_updated_at'] !== null
            ? $this->sourceDateTime($record['detail_updated_at'])
            : $date->copy();

        return [
            'sale' => [
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'source_id' => $sourceId,
                'customer_id' => $customerId,
                'sale_category_id' => $category->getKey(),
                'category_key_snapshot' => $category->key,
                'category_name_snapshot' => $category->name,
                'status' => $record['finished'] ? 'finalized' : 'open',
                'currency' => 'BRL',
                'total_amount_cents' => $itemsTotal,
                'discount_amount_cents' => $discountCents,
                'final_amount_cents' => $finalCents,
                'source_metadata' => $metadata,
                'created_at' => $date,
                'updated_at' => $updatedAt,
            ],
            'items' => $items,
            'payment_scope' => $paymentScope,
        ];
    }

    /** @return array<string, mixed> */
    private function mapItem(array $item, string $tenantId, string $unitId): array
    {
        $sourceId = $this->sourceId($item['source_id'] ?? null, 'missing_item_source_id');
        $isService = $item['is_service'] ?? $item['product_is_service'] ?? null;
        if (! is_bool($isService)) {
            throw new BelasisPendingRelation('missing_item_type_relation');
        }

        $productSourceId = $this->sourceId($item['product_source_id'] ?? null, 'missing_product_source_id');
        $catalogItem = $isService
            ? Service::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('source_id', $productSourceId)->first()
            : Product::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('source_id', $productSourceId)->first();
        if ($catalogItem === null) {
            throw new BelasisPendingRelation($isService ? 'missing_service_relation' : 'missing_product_relation');
        }

        $professionalId = null;
        $professionalSourceId = $this->scalarString($item['professional_source_id'] ?? null);
        if ($professionalSourceId !== null) {
            $professionalId = Professional::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('source_id', $professionalSourceId)
                ->value('id');
            if ($professionalId === null) {
                throw new BelasisPendingRelation('missing_professional_relation');
            }
        }

        $quantity = $this->positiveInteger($item['quantity'] ?? null);
        $unitPriceCents = $this->requiredAmountFromKeys($item, ['value_cents', 'unit_price_cents'], 'missing_item_value');
        $discountCents = $this->optionalAmount($item, 'discount_cents') ?? 0;
        $grossCents = $unitPriceCents * $quantity;
        if ($grossCents > 2147483647 || $discountCents > $grossCents) {
            throw new RuntimeException('item_amounts_incoherent');
        }
        $totalCents = $this->requiredAmountFromKeys($item, ['sum_cents', 'total_cents'], 'missing_item_sum');
        if ($grossCents - $discountCents !== $totalCents) {
            throw new RuntimeException('item_amounts_incoherent');
        }

        $name = $this->nullableString($item['product_name'] ?? $item['product_description'] ?? null);
        if ($name === null || mb_strlen($name) > 160) {
            throw new RuntimeException('missing_item_name');
        }

        return [
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'source_id' => $sourceId,
            'item_type' => $isService ? 'service' : 'product',
            'service_id' => $isService ? $catalogItem->getKey() : null,
            'product_id' => $isService ? null : $catalogItem->getKey(),
            'professional_id' => $professionalId,
            'name_snapshot' => $name,
            'unit_price_cents' => $unitPriceCents,
            'quantity' => $quantity,
            'discount_cents' => $discountCents,
            'total_cents' => $totalCents,
            'source_metadata' => $this->itemMetadata($item),
        ];
    }

    /** @param list<array<string,mixed>> $items */
    private function syncItems(Sale $sale, array $items, string $tenantId, string $unitId): void
    {
        $incomingSourceIds = [];
        foreach ($items as $item) {
            $incomingSourceIds[] = $item['source_id'];
            $existing = SaleItem::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('sale_id', $sale->getKey())
                ->where('source_id', $item['source_id'])
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                SaleItem::query()->forceCreate([...$item, 'sale_id' => $sale->getKey()]);

                continue;
            }

            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->forceFill([...$item, 'sale_id' => $sale->getKey()])->save();
        }

        SaleItem::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('sale_id', $sale->getKey())
            ->whereNotNull('source_id')
            ->whereNotIn('source_id', $incomingSourceIds)
            ->get()
            ->each(static fn (SaleItem $item): ?bool => $item->delete());
    }

    /** @return array<string, mixed>|null */
    private function paymentScope(mixed $payments): ?array
    {
        if (! is_array($payments) || ! array_is_list($payments)) {
            throw new RuntimeException('invalid_payments_structure');
        }
        if ($payments === []) {
            return null;
        }

        $totalCents = 0;
        $statuses = [];
        $records = [];
        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                throw new RuntimeException('invalid_payment');
            }
            foreach (['source_id', 'date', 'due_date', 'value_cents', 'status'] as $key) {
                if (! array_key_exists($key, $payment)) {
                    throw new RuntimeException('invalid_payment');
                }
            }
            $this->sourceId($payment['source_id'], 'invalid_payment');
            $this->sourceDate($payment['date']);
            $this->sourceDate($payment['due_date']);
            $totalCents += $this->requiredAmount($payment, 'value_cents');
            $status = $this->nullableString($payment['status']);
            if ($status === null) {
                throw new RuntimeException('invalid_payment');
            }
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            if (array_key_exists('payment', $payment) && $payment['payment'] !== null && ! is_array($payment['payment'])) {
                throw new RuntimeException('invalid_payment');
            }
            $records[] = $payment;
        }

        return [
            'status' => 'pending_unsupported_contract',
            'count' => count($payments),
            'total_cents' => $totalCents,
            'statuses' => $statuses,
            'records' => $records,
        ];
    }

    /** @param array<string, mixed> $record */
    private function saleMetadata(array $record, ?array $paymentScope): ?array
    {
        $metadata = [];
        foreach (['detail_status'] as $key) {
            if (array_key_exists($key, $record) && is_scalar($record[$key])) {
                $metadata[$key] = (string) $record[$key];
            }
        }
        if ($paymentScope !== null) {
            $metadata['payment_scope'] = $paymentScope;
        }

        return $metadata === [] ? null : $metadata;
    }

    /** @param array<string, mixed> $item */
    private function itemMetadata(array $item): ?array
    {
        $metadata = [];
        foreach (['discount_type', 'discount_percentage', 'package_item_source_id', 'subscription_item_source_id', 'batch_source_id'] as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null) {
                $metadata[$key] = $item[$key];
            }
        }

        return $metadata === [] ? null : $metadata;
    }

    /** @return list<array<string,mixed>> */
    private function readSales(string $path): array
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Import file is not readable.');
        }
        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Import file contains invalid JSON.');
        }
        if (! is_array($payload) || ! array_key_exists('records', $payload) || ! is_array($payload['records']) || ! array_is_list($payload['records'])) {
            throw new RuntimeException('Import file must contain a records list.');
        }
        foreach ($payload['records'] as $record) {
            if (! is_array($record)) {
                throw new RuntimeException('Each record must be an object.');
            }
            foreach (['source_id', 'date', 'finished', 'total_cents', 'discount_cents', 'client_source_id', 'items', 'payments'] as $key) {
                if (! array_key_exists($key, $record)) {
                    throw new RuntimeException('Import file has an invalid sales structure.');
                }
            }
            if (! is_array($record['items']) || ! array_is_list($record['items']) || $record['items'] === []) {
                throw new RuntimeException('Import file has an invalid items structure.');
            }
            if (! is_array($record['payments']) || ! array_is_list($record['payments'])) {
                throw new RuntimeException('Import file has an invalid payments structure.');
            }
            foreach ($record['items'] as $item) {
                if (! is_array($item) || ! array_key_exists('source_id', $item) || ! array_key_exists('quantity', $item) || ! array_key_exists('product_source_id', $item) || ! (array_key_exists('is_service', $item) || array_key_exists('product_is_service', $item)) || ! (array_key_exists('product_name', $item) || array_key_exists('product_description', $item)) || ! (array_key_exists('value_cents', $item) || array_key_exists('unit_price_cents', $item)) || ! (array_key_exists('sum_cents', $item) || array_key_exists('total_cents', $item))) {
                    throw new RuntimeException('Import file has an invalid item structure.');
                }
            }
        }

        return array_values($payload['records']);
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

    private function sourceId(mixed $value, string $reason): string
    {
        $value = $this->scalarString($value);
        if ($value === null || mb_strlen($value) > 191) {
            throw new RuntimeException($reason);
        }

        return $value;
    }

    private function requiredAmount(array $record, string $key): int
    {
        return $this->amountCents($record[$key] ?? null);
    }

    /** @param list<string> $keys */
    private function requiredAmountFromKeys(array $record, array $keys, string $reason): int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $record)) {
                return $this->amountCents($record[$key]);
            }
        }

        throw new RuntimeException($reason);
    }

    private function optionalAmount(array $record, string $key): ?int
    {
        return array_key_exists($key, $record) ? $this->amountCents($record[$key]) : null;
    }

    private function amountCents(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1)) {
            throw new RuntimeException('amount_must_be_non_negative_integer');
        }
        $amount = (int) $value;
        if ($amount < 0 || $amount > 2147483647) {
            throw new RuntimeException('amount_out_of_range');
        }

        return $amount;
    }

    private function positiveInteger(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1)) {
            throw new RuntimeException('quantity_must_be_positive_integer');
        }
        $quantity = (int) $value;
        if ($quantity < 1 || $quantity > 2147483647) {
            throw new RuntimeException('quantity_must_be_positive_integer');
        }

        return $quantity;
    }

    private function sourceDate(mixed $value): Carbon
    {
        $value = $this->scalarString($value);
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new RuntimeException('invalid_source_date');
        }
        $date = Carbon::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('invalid_source_date');
        }

        return $date;
    }

    private function sourceDateTime(mixed $value): Carbon
    {
        $value = $this->scalarString($value);
        if ($value === null) {
            throw new RuntimeException('invalid_source_datetime');
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw new RuntimeException('invalid_source_datetime');
        }
    }

    private function scalarString(mixed $value): ?string
    {
        if ($value === null || (! is_scalar($value) && ! $value instanceof \Stringable)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->scalarString($value);

        return $value === '' ? null : $value;
    }

    private function addRow(int $position, string $status, ?string $reason = null, ?string $error = null, ?string $paymentStatus = null): void
    {
        $row = ['row' => $position, 'status' => $status];
        if ($reason !== null) {
            $row['reason'] = $reason;
        }
        if ($error !== null) {
            $row['error'] = $error;
        }
        if ($paymentStatus !== null) {
            $row['payment_status'] = $paymentStatus;
        }
        $this->rows[] = $row;
    }

    private function writeReport(): void
    {
        $path = $this->option('report');
        if (! is_string($path) || $path === '') {
            return;
        }
        $written = file_put_contents($path, json_encode([
            'mode' => (bool) $this->option('dry-run') ? 'dry-run' : 'write',
            'summary' => $this->summary,
            'rows' => $this->rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if ($written === false) {
            throw new RuntimeException('Unable to write import report.');
        }
        $this->components->info('Report written.');
    }
}
