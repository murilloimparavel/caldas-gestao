<?php

namespace App\Console\Commands;

use App\Models\ClosingSession;
use App\Models\CustomerPackage;
use App\Models\SaleItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

#[Signature('packages:audit-legacy
    {--tenant= : Restrict the audit to one tenant UUID}
    {--format=table : Output format: table, json, or csv}
    {--output= : Write JSON or CSV to this file instead of the console}')]
#[Description('Read-only audit of customer packages and their command, payment, and usage evidence')]
final class AuditLegacyCustomerPackages extends Command
{
    /** @var array<string, string> */
    private const CLASSIFICATION_LABELS = [
        'active_with_paid_closing' => 'Ativo com comanda finalizada e paga',
        'active_without_sale' => 'Ativo sem comanda associada',
        'active_paid_obligation_without_sale' => 'Ativo com recebível pago, sem comanda',
        'active_pending_obligation_without_sale' => 'Ativo com recebível pendente, sem comanda',
        'active_item_without_sale_link' => 'Ativo com item de pacote, mas sem comanda vinculada',
        'active_item_on_different_sale' => 'Ativo com item de pacote em outra comanda',
        'active_with_conflicting_sale_items' => 'Ativo com itens de pacote em comandas diferentes',
        'active_with_missing_sale' => 'Ativo com vínculo para comanda inexistente',
        'active_without_package_item' => 'Ativo com comanda sem item de pacote vinculado',
        'active_with_cancelled_sale' => 'Ativo com comanda cancelada',
        'active_with_unfinalized_sale' => 'Ativo com comanda não finalizada',
        'active_without_closing' => 'Ativo com venda finalizada, sem fechamento',
        'active_with_incomplete_closing' => 'Ativo com fechamento não concluído',
        'active_with_insufficient_payment' => 'Ativo com fechamento sem pagamento suficiente',
        'inactive_status' => 'Pacote fora do status ativo',
    ];

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

        $includeFinancialObligation = Schema::hasColumn('financial_obligations', 'customer_package_id');
        $query = CustomerPackage::query()
            ->select([
                'id', 'tenant_id', 'unit_id', 'customer_id', 'package_template_id', 'name_snapshot',
                'status', 'sale_id', 'total_sessions', 'remaining_sessions',
            ])
            ->with([
                'customer:id,name',
                'packageTemplate:id,name',
                'sale:id,tenant_id,unit_id,customer_id,status,final_amount_cents',
                'sale.closingSessions.payments',
            ])
            ->withSum([
                'usages as consumed_sessions' => static function (Builder $query): void {
                    $query->whereNull('reversed_at');
                },
            ], 'sessions_consumed')
            ->orderBy('id');

        if ($includeFinancialObligation) {
            $query->with('financialObligation:id,tenant_id,unit_id,customer_package_id,type,status,amount_cents,paid_date');
        }

        $tenantId = $this->option('tenant');
        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $rows = [];
        $query->chunkById(100, function ($packages) use (&$rows, $includeFinancialObligation): void {
            $candidateItems = SaleItem::query()
                ->whereIn('customer_package_id', $packages->modelKeys())
                ->where('item_type', 'package')
                ->with('sale.closingSessions.payments')
                ->get()
                ->groupBy('customer_package_id');

            foreach ($packages as $package) {
                $rows[] = $this->auditPackage(
                    $package,
                    $includeFinancialObligation,
                    $candidateItems->get($package->getKey(), collect()),
                );
            }
        });

        $report = $this->buildReport($rows, $includeFinancialObligation);
        $rendered = match ($format) {
            'json' => json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'csv' => $this->renderCsv($rows),
            default => null,
        };

        if ($rendered === false) {
            $this->components->error('Não foi possível serializar o relatório.');

            return self::FAILURE;
        }

        if ($outputPath !== null) {
            $bytesWritten = @file_put_contents((string) $outputPath, (string) $rendered);
            if ($bytesWritten === false) {
                $this->components->error('Não foi possível gravar o arquivo de relatório.');

                return self::FAILURE;
            }

            $this->components->info("Relatório somente leitura gravado em {$outputPath}.");
            $this->renderSummary($report);

            return self::SUCCESS;
        }

        if ($format === 'json' || $format === 'csv') {
            $this->output->writeln((string) $rendered);

            return self::SUCCESS;
        }

        $this->renderTable($rows);
        $this->renderSummary($report);

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    /**
     * @param  Collection<int, SaleItem>  $candidateItems
     * @return array<string, mixed>
     */
    private function auditPackage(CustomerPackage $package, bool $includeFinancialObligation, Collection $candidateItems): array
    {
        $sale = $package->sale;
        $linkedPackageItems = $candidateItems->where('sale_id', $package->sale_id);
        $differentSaleItems = $candidateItems->where('sale_id', '!=', $package->sale_id);
        $closingSessions = $sale?->closingSessions ?? collect();
        $saleFinalAmountCents = (int) ($sale?->final_amount_cents ?? 0);
        $closingEvidence = $this->closingEvidence($closingSessions, $saleFinalAmountCents);
        $hasCompletedPaidClosing = $closingSessions->contains(static function ($session) use ($saleFinalAmountCents): bool {
            $netReceived = $session->payments->sum(static fn ($payment): int => $payment->is_reversal
                ? -$payment->amount_cents
                : $payment->amount_cents);

            return $session->status === 'completed'
                && $session->final_total_cents >= $saleFinalAmountCents
                && $netReceived >= $session->final_total_cents;
        });

        $obligation = $includeFinancialObligation ? $package->financialObligation : null;
        $classification = $this->classify(
            $package->status,
            $package->sale_id,
            $sale,
            $linkedPackageItems->isNotEmpty(),
            $differentSaleItems->isNotEmpty(),
            $candidateItems->isNotEmpty(),
            $closingSessions->isNotEmpty(),
            $closingSessions->contains(static fn ($session): bool => $session->status === 'completed'),
            $hasCompletedPaidClosing,
            $obligation?->type,
            $obligation?->status,
        );

        return [
            'customer_package_id' => $package->getKey(),
            'tenant_id' => $package->tenant_id,
            'unit_id' => $package->unit_id,
            'customer_id' => $package->customer_id,
            'customer_name' => $package->customer?->name,
            'package_name' => $package->name_snapshot ?? $package->packageTemplate?->name,
            'status' => $package->status,
            'classification' => $classification,
            'classification_label' => self::CLASSIFICATION_LABELS[$classification],
            'sale_id' => $package->sale_id,
            'sale_status' => $sale?->status,
            'sale_final_amount_cents' => $sale?->final_amount_cents,
            'package_sale_item_count' => $candidateItems->count(),
            'package_sale_item_total_cents' => (int) $candidateItems->sum('total_cents'),
            'candidate_sale_ids' => $candidateItems->pluck('sale_id')->unique()->values()->all(),
            'candidate_sale_item_ids' => $candidateItems->pluck('id')->values()->all(),
            'candidate_sale_items' => $candidateItems->map(static function (SaleItem $item): array {
                $candidateSale = $item->sale;

                return [
                    'sale_item_id' => $item->getKey(),
                    'sale_id' => $item->sale_id,
                    'item_total_cents' => $item->total_cents,
                    'sale_status' => $candidateSale?->status,
                    'sale_final_amount_cents' => $candidateSale?->final_amount_cents,
                    'sale_customer_id' => $candidateSale?->customer_id,
                    'closing_sessions' => self::closingEvidence(
                        $candidateSale?->closingSessions ?? collect(),
                        (int) ($candidateSale?->final_amount_cents ?? 0),
                    ),
                ];
            })->values()->all(),
            'closing_sessions' => $closingEvidence,
            'financial_obligation_type' => $obligation?->type,
            'financial_obligation_status' => $obligation?->status,
            'financial_obligation_amount_cents' => $obligation?->amount_cents,
            'financial_obligation_paid_date' => $obligation?->paid_date?->toDateString(),
            'total_sessions' => $package->total_sessions,
            'consumed_sessions' => (int) ($package->consumed_sessions ?? 0),
            'remaining_sessions' => $package->remaining_sessions,
        ];
    }

    /** @param Collection<int, ClosingSession> $closingSessions
     * @return list<array{id: string, status: string, final_total_cents: int, payment_count: int, payment_methods: list<string>, net_received_cents: int, payment_sufficient: bool}>
     */
    private static function closingEvidence(Collection $closingSessions, int $saleFinalAmountCents): array
    {
        return $closingSessions->map(static function ($session) use ($saleFinalAmountCents): array {
            $netReceived = $session->payments->sum(static fn ($payment): int => $payment->is_reversal
                ? -$payment->amount_cents
                : $payment->amount_cents);

            return [
                'id' => $session->getKey(),
                'status' => $session->status,
                'final_total_cents' => $session->final_total_cents,
                'payment_count' => $session->payments->count(),
                'payment_methods' => $session->payments->pluck('payment_method')->unique()->values()->all(),
                'net_received_cents' => $netReceived,
                'payment_sufficient' => $session->status === 'completed'
                    && $session->final_total_cents >= $saleFinalAmountCents
                    && $netReceived >= $session->final_total_cents,
            ];
        })->values()->all();
    }

    private function classify(
        string $status,
        ?string $saleId,
        mixed $sale,
        bool $hasPackageItem,
        bool $hasDifferentSaleItem,
        bool $hasAnyPackageItem,
        bool $hasClosing,
        bool $hasCompletedClosing,
        bool $hasCompletedPaidClosing,
        ?string $obligationType,
        ?string $obligationStatus,
    ): string {
        if ($status !== 'active') {
            return 'inactive_status';
        }

        if ($saleId === null) {
            if ($hasAnyPackageItem) {
                return 'active_item_without_sale_link';
            }

            if ($obligationType === 'receivable' && $obligationStatus === 'paid') {
                return 'active_paid_obligation_without_sale';
            }

            return match ($obligationStatus) {
                'pending' => $obligationType === 'receivable'
                    ? 'active_pending_obligation_without_sale'
                    : 'active_without_sale',
                default => 'active_without_sale',
            };
        }

        if ($sale === null) {
            if ($hasDifferentSaleItem) {
                return 'active_item_on_different_sale';
            }

            return 'active_with_missing_sale';
        }

        if ($sale->status === 'cancelled') {
            return 'active_with_cancelled_sale';
        }

        if ($hasDifferentSaleItem && $hasPackageItem) {
            return 'active_with_conflicting_sale_items';
        }

        if ($hasDifferentSaleItem) {
            return 'active_item_on_different_sale';
        }

        if (! $hasPackageItem) {
            return 'active_without_package_item';
        }

        if ($sale->status !== 'finalized') {
            return 'active_with_unfinalized_sale';
        }

        if (! $hasClosing) {
            return 'active_without_closing';
        }

        if (! $hasCompletedClosing) {
            return 'active_with_incomplete_closing';
        }

        if (! $hasCompletedPaidClosing) {
            return 'active_with_insufficient_payment';
        }

        return 'active_with_paid_closing';
    }

    /** @param list<array<string, mixed>> $rows
     * @return array{generated_at: string, read_only: bool, tenant_filter: string|null, financial_obligation_link_available: bool, totals: array<string, int>, classifications: array<string, int>, packages: list<array<string, mixed>>}
     */
    private function buildReport(array $rows, bool $includeFinancialObligation): array
    {
        $classifications = [];
        $activeCount = 0;
        $activeWithoutPaidEvidence = 0;
        $activeRemainingSessions = 0;

        foreach ($rows as $row) {
            $classification = $row['classification'];
            $classifications[$classification] = ($classifications[$classification] ?? 0) + 1;

            if ($row['status'] === 'active') {
                $activeCount++;
                $activeRemainingSessions += $row['remaining_sessions'];
                if ($classification !== 'active_with_paid_closing') {
                    $activeWithoutPaidEvidence++;
                }
            }
        }

        ksort($classifications);

        return [
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'tenant_filter' => $this->option('tenant') ?: null,
            'financial_obligation_link_available' => $includeFinancialObligation,
            'totals' => [
                'packages' => count($rows),
                'active_packages' => $activeCount,
                'active_without_paid_closing_evidence' => $activeWithoutPaidEvidence,
                'active_remaining_sessions' => $activeRemainingSessions,
            ],
            'classifications' => $classifications,
            'packages' => $rows,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function renderTable(array $rows): void
    {
        $this->table(
            ['Cliente', 'Pacote', 'Status', 'Classificação', 'Comanda', 'Venda', 'Recebível', 'Saldo'],
            array_map(static fn (array $row): array => [
                $row['customer_name'] ?? $row['customer_id'],
                $row['package_name'] ?? $row['customer_package_id'],
                $row['status'],
                $row['classification_label'],
                $row['sale_id'] ?? '—',
                $row['sale_status'] ?? '—',
                $row['financial_obligation_status'] ?? '—',
                "{$row['remaining_sessions']}/{$row['total_sessions']}",
            ], $rows),
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private function renderCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }

        $headers = [
            'customer_package_id', 'tenant_id', 'unit_id', 'customer_id', 'customer_name', 'package_name',
            'status', 'classification', 'sale_id', 'sale_status', 'sale_final_amount_cents',
            'package_sale_item_count', 'package_sale_item_total_cents', 'closing_sessions',
            'candidate_sale_ids', 'candidate_sale_item_ids', 'candidate_sale_items',
            'financial_obligation_type', 'financial_obligation_status',
            'financial_obligation_amount_cents', 'financial_obligation_paid_date', 'total_sessions',
            'consumed_sessions', 'remaining_sessions',
        ];
        fputcsv($stream, $headers, ',', '"', '');

        foreach ($rows as $row) {
            $row['closing_sessions'] = json_encode($row['closing_sessions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $row['candidate_sale_ids'] = json_encode($row['candidate_sale_ids'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $row['candidate_sale_item_ids'] = json_encode($row['candidate_sale_item_ids'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $row['candidate_sale_items'] = json_encode($row['candidate_sale_items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            fputcsv($stream, array_map(static fn (string $key): mixed => $row[$key] ?? '', $headers), ',', '"', '');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    /** @param array<string, mixed> $report */
    private function renderSummary(array $report): void
    {
        $this->newLine();
        $this->components->info('Auditoria somente leitura: nenhum dado do banco foi alterado.');
        $this->line('Pacotes: '.$report['totals']['packages']);
        $this->line('Pacotes ativos: '.$report['totals']['active_packages']);
        $this->line('Ativos sem evidência de fechamento pago: '.$report['totals']['active_without_paid_closing_evidence']);
        $this->line('Sessões restantes nesses pacotes ativos: '.$report['totals']['active_remaining_sessions']);
    }
}
