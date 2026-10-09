<?php

namespace App\Console\Commands;

use App\Models\CommissionAccrual;
use App\Models\Sale;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('commissions:reconcile-imported-sales
    {--tenant= : Restrict the report to one tenant UUID}
    {--unit= : Restrict the report to one unit UUID}
    {--format=table : Output format: table, json, or csv}
    {--output= : Write JSON or CSV to this file instead of the console}', [
    'app:reconcile-imported-sale-commissions',
    'commissions:audit-imported-sales',
])]
#[Description('Read-only report of imported finalized sales without commission accruals')]
final class ReconcileImportedSaleCommissions extends Command
{
    private const PAYMENT_SCOPE_WARNING = 'Aviso: o escopo de importação de pagamentos não é suportado; este relatório não reconcilia pagamentos.';

    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['table', 'json', 'csv'], true)) {
            $this->components->error('Formato inválido. Use table, json ou csv.');

            return self::INVALID;
        }

        $outputPath = $this->option('output');
        if ($outputPath !== null && $format === 'table') {
            $this->components->error('A opção --output só pode ser usada com --format=json ou --format=csv.');

            return self::INVALID;
        }

        $commissionAccrualTable = (new CommissionAccrual)->getTable();
        $saleTable = (new Sale)->getTable();
        $query = Sale::query()
            ->select([
                'id', 'tenant_id', 'unit_id', 'source_id', 'status', 'total_amount_cents',
                'discount_amount_cents', 'final_amount_cents', 'source_metadata',
            ])
            ->withSum('items as active_items_total_cents', 'total_cents')
            ->where('status', 'finalized')
            ->whereNotNull('source_id')
            ->whereNotExists(function ($query) use ($commissionAccrualTable, $saleTable): void {
                $query->selectRaw('1')
                    ->from($commissionAccrualTable)
                    ->whereColumn($commissionAccrualTable.'.sale_id', $saleTable.'.id');
            })
            ->orderBy('id');

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $unitId = $this->option('unit');
        if (is_string($unitId) && $unitId !== '') {
            $query->where('unit_id', $unitId);
        }

        $rows = [];
        $query->chunkById(100, function ($sales) use (&$rows): void {
            foreach ($sales as $sale) {
                $rows[] = $this->saleRow($sale);
            }
        });

        $report = [
            'read_only' => true,
            'warning' => self::PAYMENT_SCOPE_WARNING,
            'payment_import_scope_supported' => false,
            'filters' => [
                'tenant_id' => is_string($tenantId) && $tenantId !== '' ? $tenantId : null,
                'unit_id' => is_string($unitId) && $unitId !== '' ? $unitId : null,
            ],
            'totals' => [
                'imported_finalized_sales_without_commission_accruals' => count($rows),
                'sale_total_mismatches' => count(array_filter($rows, static fn (array $row): bool => $row['sale_total_mismatch'])),
                'payment_scope_pending_unsupported_contract' => count(array_filter($rows, static fn (array $row): bool => $row['payment_scope_status'] === 'pending_unsupported_contract')),
            ],
            'sales' => $rows,
        ];

        if ($format === 'json') {
            $rendered = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($rendered === false) {
                $this->components->error('Não foi possível serializar o relatório.');

                return self::FAILURE;
            }
        } elseif ($format === 'csv') {
            $rendered = $this->renderCsv($rows);
        } else {
            $this->components->warn(self::PAYMENT_SCOPE_WARNING);
            $this->renderTable($rows);
            $this->line('Vendas importadas finalizadas sem comissão: '.count($rows));

            return self::SUCCESS;
        }

        if ($outputPath !== null) {
            $bytesWritten = @file_put_contents((string) $outputPath, $rendered);
            if ($bytesWritten === false) {
                $this->components->error('Não foi possível gravar o arquivo de relatório.');

                return self::FAILURE;
            }

            $this->components->info("Relatório somente leitura gravado em {$outputPath}.");

            return self::SUCCESS;
        }

        $this->output->writeln($rendered);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function saleRow(Sale $sale): array
    {
        $activeItemsTotalCents = (int) ($sale->active_items_total_cents ?? 0);
        $discountAmountCents = (int) $sale->discount_amount_cents;
        $expectedFinalAmountCents = max(0, $activeItemsTotalCents - $discountAmountCents);
        $totalAmountMismatch = (int) $sale->total_amount_cents !== $activeItemsTotalCents;
        $finalAmountMismatch = (int) $sale->final_amount_cents !== $expectedFinalAmountCents;

        return [
            'sale_id' => (string) $sale->getKey(),
            'tenant_id' => (string) $sale->tenant_id,
            'unit_id' => (string) $sale->unit_id,
            'source_id' => $sale->source_id,
            'status' => (string) $sale->status,
            'commission_accrual_status' => 'missing',
            'commission_accrual_count' => 0,
            'total_amount_cents' => (int) $sale->total_amount_cents,
            'discount_amount_cents' => $discountAmountCents,
            'final_amount_cents' => (int) $sale->final_amount_cents,
            'active_items_total_cents' => $activeItemsTotalCents,
            'expected_final_amount_cents' => $expectedFinalAmountCents,
            'total_amount_mismatch' => $totalAmountMismatch,
            'final_amount_mismatch' => $finalAmountMismatch,
            'sale_total_mismatch' => $totalAmountMismatch || $finalAmountMismatch,
            'payment_scope_status' => data_get($sale->source_metadata, 'payment_scope.status', 'not_present'),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function renderTable(array $rows): void
    {
        $this->table(
            ['Venda', 'Origem', 'Tenant', 'Unidade', 'Itens ativos', 'Valor final', 'Divergência', 'Escopo pagamentos'],
            array_map(static fn (array $row): array => [
                $row['sale_id'], $row['source_id'], $row['tenant_id'], $row['unit_id'],
                $row['active_items_total_cents'], $row['final_amount_cents'],
                $row['sale_total_mismatch'] ? 'yes' : 'no', $row['payment_scope_status'],
            ], $rows),
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function renderCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, [
            'sale_id', 'tenant_id', 'unit_id', 'source_id', 'status', 'commission_accrual_status',
            'active_items_total_cents', 'total_amount_cents', 'final_amount_cents',
            'expected_final_amount_cents', 'sale_total_mismatch', 'payment_scope_status',
        ]);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['sale_id'], $row['tenant_id'], $row['unit_id'], $row['source_id'], $row['status'],
                $row['commission_accrual_status'], $row['active_items_total_cents'], $row['total_amount_cents'],
                $row['final_amount_cents'], $row['expected_final_amount_cents'], $row['sale_total_mismatch'] ? 'true' : 'false',
                $row['payment_scope_status'],
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
