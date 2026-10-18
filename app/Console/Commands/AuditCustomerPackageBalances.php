<?php

namespace App\Console\Commands;

use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\Membership;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\AuditEventWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('packages:audit-balances
    {--tenant= : Restrict the audit to one tenant UUID}
    {--package= : Inspect or repair one customer package UUID}
    {--format=table : Output format: table or json}
    {--apply : Persist a repair; omitted means dry-run}
    {--actor= : User UUID responsible for an applied repair}
    {--reason= : Human-readable reason for an applied repair}')]
#[Description('Audits customer package balances from allocation and usage evidence; dry-run is the default')]
final class AuditCustomerPackageBalances extends Command
{
    /** @var list<string> */
    private const RESERVATION_STATUSES = ['reserved', 'consumed', 'released'];

    public function handle(AuditEventWriter $auditEvents): int
    {
        $format = strtolower((string) $this->option('format'));
        $apply = (bool) $this->option('apply');
        $packageId = $this->option('package');
        $tenantId = $this->option('tenant');
        $actorId = $this->option('actor');
        $reason = $this->option('reason');

        if (! in_array($format, ['table', 'json'], true)) {
            $this->components->error('Formato inválido. Use table ou json.');

            return self::INVALID;
        }

        if ($apply && (! is_string($packageId) || trim($packageId) === '')) {
            $this->components->error('A opção --package é obrigatória com --apply.');

            return self::INVALID;
        }

        if ($apply && (! is_string($actorId) || ! Str::isUuid($actorId) || ! User::query()->whereKey($actorId)->exists())) {
            $this->components->error('A opção --actor é obrigatória com --apply e deve ser o UUID de um usuário existente.');

            return self::INVALID;
        }

        if ($apply && (! is_string($reason) || trim($reason) === '' || mb_strlen($reason) > 500)) {
            $this->components->error('A opção --reason é obrigatória com --apply e deve ter entre 1 e 500 caracteres.');

            return self::INVALID;
        }

        if (! $apply && (($actorId !== null && $actorId !== '') || ($reason !== null && $reason !== ''))) {
            $this->components->error('As opções --actor e --reason só podem ser usadas com --apply.');

            return self::INVALID;
        }

        if ($apply) {
            return $this->applyRepair((string) $packageId, (string) $actorId, trim((string) $reason), $tenantId, $format, $auditEvents);
        }

        $query = CustomerPackage::query()
            ->with(['serviceBalances', 'usages', 'reservations'])
            ->orderBy('id');

        if (is_string($tenantId) && trim($tenantId) !== '') {
            $query->where('tenant_id', trim($tenantId));
        }

        if (is_string($packageId) && trim($packageId) !== '') {
            $query->whereKey(trim($packageId));
        }

        /** @var list<array<string, mixed>> $reports */
        $reports = $query->get()->map(fn (CustomerPackage $package): array => $this->auditPackage($package))->values()->all();

        $this->renderReports($reports, $format, false, false);

        return self::SUCCESS;
    }

    private function applyRepair(
        string $packageId,
        string $actorId,
        string $reason,
        mixed $tenantId,
        string $format,
        AuditEventWriter $auditEvents,
    ): int {
        $result = DB::transaction(function () use ($packageId, $actorId, $reason, $tenantId, $auditEvents): array {
            $packageQuery = CustomerPackage::query()->whereKey($packageId);
            if (is_string($tenantId) && trim($tenantId) !== '') {
                $packageQuery->where('tenant_id', trim($tenantId));
            }

            /** @var CustomerPackage|null $package */
            $package = $packageQuery->lockForUpdate()->first();
            if ($package === null) {
                return ['error' => 'Pacote não encontrado no escopo informado.'];
            }

            if (! Membership::query()->where('tenant_id', $package->tenant_id)->where('user_id', $actorId)->where('status', 'active')->exists()) {
                return ['error' => 'O responsável informado não possui uma participação ativa neste tenant.'];
            }

            $package->setRelation('serviceBalances', CustomerPackageService::query()
                ->where('customer_package_id', $package->getKey())
                ->lockForUpdate()
                ->get());
            $package->setRelation('usages', PackageUsage::query()
                ->where('customer_package_id', $package->getKey())
                ->lockForUpdate()
                ->get());
            $package->setRelation('reservations', PackageUsageReservation::query()
                ->where('customer_package_id', $package->getKey())
                ->lockForUpdate()
                ->get());

            $report = $this->auditPackage($package);
            if (! $report['repairable']) {
                return ['report' => $report, 'error' => 'O pacote é ambíguo e foi preservado sem alterações.', 'changed' => false];
            }

            if (! $report['has_mismatch']) {
                return ['report' => $report, 'message' => 'Nenhuma divergência encontrada; nada foi alterado.', 'changed' => false];
            }

            $package->forceFill([
                'remaining_sessions' => $report['expected_remaining_sessions'],
                'lock_version' => $package->lock_version + 1,
            ])->save();

            foreach ($report['expected_service_balances'] as $serviceId => $expectedRemaining) {
                $updated = CustomerPackageService::query()
                    ->where('tenant_id', $package->tenant_id)
                    ->where('unit_id', $package->unit_id)
                    ->where('customer_package_id', $package->getKey())
                    ->where('service_id', $serviceId)
                    ->update(['remaining_quantity' => $expectedRemaining]);

                if ($updated !== 1) {
                    throw new \RuntimeException('O saldo de serviço não correspondeu exatamente ao escopo esperado; o reparo foi revertido.');
                }
            }

            $package->setRelation('serviceBalances', CustomerPackageService::query()
                ->where('tenant_id', $package->tenant_id)
                ->where('unit_id', $package->unit_id)
                ->where('customer_package_id', $package->getKey())
                ->lockForUpdate()
                ->get());

            $auditEvents->record([
                'tenant_id' => $package->tenant_id,
                'unit_id' => $package->unit_id,
                'actor_user_id' => $actorId,
                'action' => 'customer_package.balance_reconciled',
                'resource_type' => 'customer_package',
                'resource_id' => $package->getKey(),
                'reason' => $reason,
                'metadata' => [
                    'customer_package_id' => $package->getKey(),
                    'remaining_sessions' => $report['expected_remaining_sessions'],
                    'total_sessions' => $report['total_sessions'],
                    'sessions_consumed' => $report['active_usage_sessions'],
                    'service_ids' => array_keys($report['expected_service_balances']),
                    'lock_version' => $package->lock_version,
                    'reason_code' => 'ledger_balance_reconciled_from_verified_evidence',
                ],
            ]);

            return ['report' => $this->auditPackage($package), 'message' => 'Reparo aplicado com sucesso.', 'changed' => true];
        }, 5);

        if (isset($result['error'])) {
            if (isset($result['report'])) {
                $this->renderReports([$result['report']], $format, true, false);
            }
            $this->components->error($result['error']);

            return self::FAILURE;
        }

        $this->renderReports([$result['report']], $format, true, (bool) $result['changed']);
        if ($format === 'table') {
            $this->components->info((string) $result['message']);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function auditPackage(CustomerPackage $package): array
    {
        $snapshot = collect($this->snapshotEntries($package));
        $allocations = [];
        $issues = [];

        foreach ($snapshot as $service) {
            if (! is_array($service)) {
                $issues[] = 'invalid_or_duplicate_service_snapshot';

                continue;
            }

            $serviceId = (string) ($service['id'] ?? '');
            $quantity = (int) ($service['quantity'] ?? 0);
            if ($serviceId === '' || $quantity <= 0 || isset($allocations[$serviceId])) {
                $issues[] = 'invalid_or_duplicate_service_snapshot';

                continue;
            }
            $allocations[$serviceId] = $quantity;
        }

        if ($snapshot->isEmpty()) {
            $issues[] = 'missing_service_snapshot';
        }
        if (array_sum($allocations) !== (int) $package->total_sessions) {
            $issues[] = 'snapshot_total_mismatch';
        }
        if ($package->total_sessions <= 0 || $package->remaining_sessions < 0 || $package->remaining_sessions > $package->total_sessions) {
            $issues[] = 'package_counter_out_of_range';
        }

        $balances = $package->serviceBalances->keyBy('service_id');
        if ($balances->count() !== count($allocations) || $balances->keys()->diff(array_keys($allocations))->isNotEmpty()) {
            $issues[] = 'service_balance_scope_mismatch';
        }

        $activeUsages = $package->usages->filter(static fn (PackageUsage $usage): bool => $usage->reversed_at === null);
        $activeUsageSessions = (int) $activeUsages->sum('sessions_consumed');
        $expectedRemaining = (int) $package->total_sessions - $activeUsageSessions;
        if ($expectedRemaining < 0) {
            $issues[] = 'usage_exceeds_package_allocation';
        }

        $expectedBalances = [];
        foreach ($allocations as $serviceId => $allocatedQuantity) {
            $used = (int) $activeUsages->where('service_id', $serviceId)->sum('sessions_consumed');
            $expectedBalances[$serviceId] = $allocatedQuantity - $used;
            if ($expectedBalances[$serviceId] < 0) {
                $issues[] = 'usage_exceeds_service_allocation';
            }
            $balance = $balances->get($serviceId);
            if ($balance === null || (int) $balance->allocated_quantity !== $allocatedQuantity) {
                $issues[] = 'stored_service_allocation_mismatch';
            }
        }

        foreach ($package->serviceBalances as $balance) {
            $allocatedQuantity = filter_var($balance->getRawOriginal('allocated_quantity'), FILTER_VALIDATE_INT);
            $remainingQuantity = filter_var($balance->getRawOriginal('remaining_quantity'), FILTER_VALIDATE_INT);
            if ($balance->tenant_id !== $package->tenant_id || $balance->unit_id !== $package->unit_id
                || $balance->customer_package_id !== $package->getKey() || ! is_int($allocatedQuantity)
                || ! is_int($remainingQuantity) || $allocatedQuantity < 0
                || $remainingQuantity < 0 || $remainingQuantity > $allocatedQuantity) {
                $issues[] = 'service_balance_scope_or_range_mismatch';
            }
        }

        foreach ($package->usages as $usage) {
            if ($usage->tenant_id !== $package->tenant_id || $usage->unit_id !== $package->unit_id
                || $usage->customer_package_id !== $package->getKey() || $usage->service_id === null
                || ! isset($allocations[$usage->service_id]) || $usage->sessions_consumed <= 0) {
                $issues[] = 'usage_scope_mismatch';
            }
        }

        $reservationReport = $this->auditReservations($package, $allocations, $expectedBalances);
        $issues = array_values(array_unique([...$issues, ...$reservationReport['issues']]));
        $storedBalances = $balances->mapWithKeys(static fn (CustomerPackageService $balance): array => [
            (string) $balance->service_id => (int) $balance->remaining_quantity,
        ])->all();
        $normalizedStoredBalances = $storedBalances;
        $normalizedExpectedBalances = $expectedBalances;
        ksort($normalizedStoredBalances);
        ksort($normalizedExpectedBalances);
        $hasMismatch = $expectedRemaining !== (int) $package->remaining_sessions || $normalizedStoredBalances !== $normalizedExpectedBalances;

        return [
            'customer_package_id' => $package->getKey(),
            'tenant_id' => $package->tenant_id,
            'status' => $package->status,
            'repairable' => $issues === [],
            'has_mismatch' => $hasMismatch,
            'issues' => $issues,
            'warnings' => $reservationReport['warnings'],
            'total_sessions' => (int) $package->total_sessions,
            'active_usage_sessions' => $activeUsageSessions,
            'active_reservation_sessions' => $reservationReport['active_sessions'],
            'expected_remaining_sessions' => $expectedRemaining,
            'stored_remaining_sessions' => (int) $package->remaining_sessions,
            'expected_service_balances' => $expectedBalances,
            'stored_service_balances' => $storedBalances,
            'service_deltas' => array_map(
                static fn (string $serviceId): int => ($expectedBalances[$serviceId] ?? 0) - ($storedBalances[$serviceId] ?? 0),
                array_values(array_unique([...array_keys($expectedBalances), ...array_keys($storedBalances)])),
            ),
        ];
    }

    /** @param array<string, int> $allocations
     * @param  array<string, int>  $expectedBalances
     * @return array{active_sessions:int, issues:list<string>, warnings:list<string>}
     */
    private function auditReservations(CustomerPackage $package, array $allocations, array $expectedBalances): array
    {
        $issues = [];
        $warnings = [];
        $activeSessions = 0;
        $reservedByService = [];
        $usages = $package->usages->keyBy('id');
        $saleItems = SaleItem::withTrashed()->whereIn('id', $package->reservations->pluck('sale_item_id')->filter()->all())->with('sale')->get()->keyBy('id');

        foreach ($package->reservations as $reservation) {
            if ($reservation->tenant_id !== $package->tenant_id || $reservation->unit_id !== $package->unit_id
                || $reservation->customer_package_id !== $package->getKey() || $reservation->sessions_reserved <= 0
                || ! in_array($reservation->status, self::RESERVATION_STATUSES, true)
                || ! isset($allocations[$reservation->service_id])) {
                $issues[] = 'reservation_scope_mismatch';

                continue;
            }

            if ($reservation->status === 'reserved') {
                $item = $saleItems->get($reservation->sale_item_id);
                $sale = $item?->getRelation('sale');
                if ($item === null || $item->tenant_id !== $package->tenant_id || $item->unit_id !== $package->unit_id
                    || $item->sale_id !== $reservation->sale_id || ($item->customer_package_id !== null && $item->customer_package_id !== $package->getKey())
                    || $item->item_type !== 'service' || $item->service_id !== $reservation->service_id || $item->covered_quantity !== $reservation->sessions_reserved
                    || ! ($sale instanceof Sale) || $sale->tenant_id !== $package->tenant_id || $sale->unit_id !== $package->unit_id
                    || $sale->customer_id !== $package->customer_id
                    || ! in_array($sale->status, ['open', 'ready_to_bill'], true)) {
                    $issues[] = 'reserved_item_mismatch';
                }
                if ($item?->customer_package_id === null) {
                    $warnings[] = 'legacy_sale_item_package_link_missing';
                }
                $activeSessions += (int) $reservation->sessions_reserved;
                $reservedByService[$reservation->service_id] = ($reservedByService[$reservation->service_id] ?? 0) + $reservation->sessions_reserved;
            } elseif ($reservation->status === 'consumed') {
                $usage = $usages->get($reservation->package_usage_id);
                $item = $saleItems->get($reservation->sale_item_id);
                $sale = $item?->getRelation('sale');
                if ($usage === null || $usage->reversed_at !== null || $usage->sale_id !== $reservation->sale_id
                    || $usage->sale_item_id !== $reservation->sale_item_id || $usage->service_id !== $reservation->service_id
                    || $usage->sessions_consumed !== $reservation->sessions_reserved || $item === null
                    || $item->tenant_id !== $package->tenant_id || $item->unit_id !== $package->unit_id
                    || $item->sale_id !== $reservation->sale_id || $item->service_id !== $reservation->service_id
                    || $item->item_type !== 'service' || ($item->customer_package_id !== null && $item->customer_package_id !== $package->getKey()) || $item->covered_quantity !== $reservation->sessions_reserved
                    || ! ($sale instanceof Sale) || $sale->tenant_id !== $package->tenant_id || $sale->unit_id !== $package->unit_id
                    || $sale->customer_id !== $package->customer_id
                    || $sale->status !== 'finalized') {
                    $issues[] = 'consumed_reservation_mismatch';
                }
                if ($item?->customer_package_id === null) {
                    $warnings[] = 'legacy_sale_item_package_link_missing';
                }
            } elseif ($reservation->package_usage_id !== null) {
                $usage = $usages->get($reservation->package_usage_id);
                $item = $saleItems->get($reservation->sale_item_id);
                $sale = $item?->sale;
                $validRelease = $reservation->released_at !== null
                    && $usage !== null
                    && $usage->reversed_at !== null
                    && $usage->tenant_id === $package->tenant_id
                    && $usage->unit_id === $package->unit_id
                    && $usage->customer_package_id === $package->getKey()
                    && $usage->sale_id === $reservation->sale_id
                    && $usage->sale_item_id === $reservation->sale_item_id
                    && $usage->service_id === $reservation->service_id
                    && $usage->sessions_consumed === $reservation->sessions_reserved
                    && $item !== null
                    && $item->item_type === 'service'
                    && $item->tenant_id === $package->tenant_id
                    && $item->unit_id === $package->unit_id
                    && $item->sale_id === $reservation->sale_id
                    && $item->service_id === $reservation->service_id
                    && $item->covered_quantity === $reservation->sessions_reserved
                    && ($item->customer_package_id === null || $item->customer_package_id === $package->getKey())
                    && $sale !== null
                    && $sale->tenant_id === $package->tenant_id
                    && $sale->unit_id === $package->unit_id
                    && $sale->customer_id === $package->customer_id;

                if (! $validRelease) {
                    $issues[] = 'released_reservation_mismatch';
                }
                if ($item?->customer_package_id === null) {
                    $warnings[] = 'legacy_sale_item_package_link_missing';
                }
            } else {
                $item = $saleItems->get($reservation->sale_item_id);
                $sale = $item?->sale;
                $validRelease = $reservation->released_at !== null
                    && $item !== null
                    && $item->item_type === 'service'
                    && $item->tenant_id === $package->tenant_id
                    && $item->unit_id === $package->unit_id
                    && $item->sale_id === $reservation->sale_id
                    && $item->service_id === $reservation->service_id
                    && $item->covered_quantity === $reservation->sessions_reserved
                    && ($item->customer_package_id === null || $item->customer_package_id === $package->getKey())
                    && $sale !== null
                    && $sale->tenant_id === $package->tenant_id
                    && $sale->unit_id === $package->unit_id
                    && $sale->customer_id === $package->customer_id
                    && ($item->trashed() || $sale->status === 'cancelled');
                if (! $validRelease) {
                    $issues[] = 'released_reservation_mismatch';
                }
                if ($item?->customer_package_id === null) {
                    $warnings[] = 'legacy_sale_item_package_link_missing';
                }
            }
        }

        foreach ($reservedByService as $serviceId => $quantity) {
            if ($quantity > ($expectedBalances[$serviceId] ?? 0)) {
                $issues[] = 'reserved_sessions_exceed_balance';
            }
        }
        if ($activeSessions > array_sum($expectedBalances)) {
            $issues[] = 'reserved_sessions_exceed_package_balance';
        }

        foreach ($package->usages as $usage) {
            if (($usage->sale_id !== null || $usage->sale_item_id !== null) && $usage->reversed_at === null
                && ! $package->reservations->contains(static fn (PackageUsageReservation $reservation): bool => $reservation->package_usage_id === $usage->getKey() && $reservation->status === 'consumed')) {
                $issues[] = 'sale_usage_without_consumed_reservation';
            }
        }

        return [
            'active_sessions' => $activeSessions,
            'issues' => array_values(array_unique($issues)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @return list<mixed> */
    private function snapshotEntries(CustomerPackage $package): array
    {
        $rawSnapshot = $package->getRawOriginal('eligible_services_snapshot');

        if (is_string($rawSnapshot)) {
            $decodedSnapshot = json_decode($rawSnapshot, true);

            return is_array($decodedSnapshot) ? array_values($decodedSnapshot) : [];
        }

        if (is_array($rawSnapshot)) {
            return array_values($rawSnapshot);
        }

        $snapshot = $package->eligible_services_snapshot;

        return is_array($snapshot) ? array_values($snapshot) : [];
    }

    /** @param list<array<string,mixed>> $reports */
    private function renderReports(array $reports, string $format, bool $applyRequested, bool $changed): void
    {
        $payload = [
            'read_only' => ! $applyRequested,
            'apply_requested' => $applyRequested,
            'applied' => $changed,
            'changed' => $changed,
            'totals' => [
                'packages' => count($reports),
                'repairable' => collect($reports)->where('repairable', true)->count(),
                'divergent' => collect($reports)->where('has_mismatch', true)->count(),
                'ambiguous' => collect($reports)->where('repairable', false)->count(),
            ],
            'packages' => $reports,
        ];

        if ($format === 'json') {
            $this->output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->table(
            ['Pacote', 'Status', 'Esperado', 'Gravado', 'Serviços esperados', 'Serviços gravados', 'Reparo', 'Problemas', 'Avisos'],
            collect($reports)->map(static fn (array $report): array => [
                $report['customer_package_id'],
                $report['status'],
                $report['expected_remaining_sessions'],
                $report['stored_remaining_sessions'],
                $report['expected_service_balances'] === [] ? '—' : json_encode($report['expected_service_balances'], JSON_UNESCAPED_UNICODE),
                $report['stored_service_balances'] === [] ? '—' : json_encode($report['stored_service_balances'], JSON_UNESCAPED_UNICODE),
                $report['has_mismatch'] && $report['repairable'] ? 'sim' : 'não',
                $report['issues'] === [] ? '—' : implode(', ', $report['issues']),
                $report['warnings'] === [] ? '—' : implode(', ', $report['warnings']),
            ])->all(),
        );
        $this->line('Pacotes: '.$payload['totals']['packages'].' | Divergentes: '.$payload['totals']['divergent'].' | Ambíguos: '.$payload['totals']['ambiguous']);
        if (! $applyRequested) {
            $this->components->info('Dry-run: nenhum registro foi alterado.');
        }
    }
}
