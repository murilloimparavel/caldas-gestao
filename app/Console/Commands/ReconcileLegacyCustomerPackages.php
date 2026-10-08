<?php

namespace App\Console\Commands;

use App\Models\CustomerPackage;
use App\Models\Membership;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\LegacyPackageReconciliation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

#[Signature('packages:reconcile-legacy
    {--tenant= : Restrict reconciliation to one tenant UUID}
    {--actor= : User UUID responsible for applying changes}
    {--backup-reference= : Reference to the verified backup used for this change}
    {--apply : Persist safe status corrections; omitted means dry-run}')]
#[Description('Dry-run is the default; --apply requires --tenant, an active tenant --actor, and verified --backup-reference')]
final class ReconcileLegacyCustomerPackages extends Command
{
    public function handle(LegacyPackageReconciliation $reconciliation, AuditEventWriter $auditEvents): int
    {
        $financialLinkAvailable = Schema::hasColumn('financial_obligations', 'customer_package_id');
        $tenantId = $this->option('tenant');
        $apply = (bool) $this->option('apply');
        $actorId = $this->option('actor');
        $backupReference = $this->option('backup-reference');
        if ($apply && (! is_string($tenantId) || trim($tenantId) === '')) {
            $this->components->error('A opção --tenant é obrigatória com --apply para validar a unidade de trabalho do responsável.');

            return self::INVALID;
        }

        if ($apply && (! is_string($actorId) || ! Str::isUuid($actorId) || ! User::query()->whereKey($actorId)->exists())) {
            $this->components->error('A opção --actor é obrigatória com --apply e deve ser o UUID de um usuário existente.');

            return self::INVALID;
        }

        if ($apply && ! Membership::query()->where('tenant_id', $tenantId)->where('user_id', $actorId)->where('status', 'active')->exists()) {
            $this->components->error('O responsável informado não possui uma participação ativa neste tenant.');

            return self::INVALID;
        }

        if ($apply && (! is_string($backupReference) || trim($backupReference) === '' || mb_strlen($backupReference) > 120)) {
            $this->components->error('A opção --backup-reference é obrigatória com --apply e deve identificar um backup verificado (máximo de 120 caracteres).');

            return self::INVALID;
        }

        if (! $apply && (($actorId !== null && $actorId !== '') || ($backupReference !== null && $backupReference !== ''))) {
            $this->components->error('As opções --actor e --backup-reference só podem ser usadas com --apply.');

            return self::INVALID;
        }
        $query = CustomerPackage::query()
            ->where('status', 'active')
            ->with([
                'sale.closingSessions.payments',
                'usages',
                'serviceBalances',
                'reservations',
                ...($financialLinkAvailable ? ['financialObligation'] : []),
            ])
            ->orderBy('id');

        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $counts = ['pending' => 0, 'review_required' => 0, 'active' => 0, 'updated' => 0, 'skipped' => 0];

        $query->chunkById(100, function (Collection $packages) use ($reconciliation, $auditEvents, $apply, $actorId, $backupReference, $financialLinkAvailable, &$counts): void {
            $linkedItems = SaleItem::query()
                ->whereIn('customer_package_id', $packages->modelKeys())
                ->with('sale')
                ->get()
                ->groupBy('customer_package_id');

            foreach ($packages as $package) {
                $assessment = $reconciliation->assess(
                    $package,
                    $linkedItems->get($package->getKey(), collect()),
                    $financialLinkAvailable,
                );
                $targetStatus = $assessment['target_status'];

                if ($apply && $targetStatus !== 'active') {
                    $result = DB::transaction(function () use ($package, $reconciliation, $auditEvents, $actorId, $backupReference, $financialLinkAvailable): ?array {
                        /** @var CustomerPackage|null $lockedPackage */
                        $lockedPackage = CustomerPackage::query()
                            ->whereKey($package->getKey())
                            ->lockForUpdate()
                            ->first();

                        if ($lockedPackage === null || $lockedPackage->status !== 'active') {
                            return null;
                        }

                        $lockedPackage->load([
                            'sale.closingSessions.payments',
                            'usages',
                            'serviceBalances',
                            'reservations',
                            ...($financialLinkAvailable ? ['financialObligation'] : []),
                        ]);
                        $packageItems = SaleItem::query()
                            ->where('customer_package_id', $lockedPackage->getKey())
                            ->with('sale')
                            ->get();
                        $lockedAssessment = $reconciliation->assess($lockedPackage, $packageItems, $financialLinkAvailable);
                        $newStatus = $lockedAssessment['target_status'];

                        if ($newStatus === 'active') {
                            return ['status' => 'active', 'reasons' => $lockedAssessment['reasons']];
                        }

                        $previousStatus = $lockedPackage->status;
                        $lockedPackage->forceFill([
                            'status' => $newStatus,
                            'lock_version' => $lockedPackage->lock_version + 1,
                        ])->save();

                        $auditEvents->record([
                            'tenant_id' => $lockedPackage->tenant_id,
                            'unit_id' => $lockedPackage->unit_id,
                            'actor_user_id' => $actorId,
                            'action' => 'customer_package.legacy_reconciled',
                            'resource_type' => 'customer_package',
                            'resource_id' => $lockedPackage->getKey(),
                            'reason' => 'Reconciliação conservadora de pacote legado sem evidência suficiente de faturamento.',
                            'metadata' => [
                                'from_status' => $previousStatus,
                                'to_status' => $newStatus,
                                'sale_id' => $lockedPackage->sale_id,
                                'reason_code' => $lockedAssessment['reasons'][0] ?? 'legacy_evidence_insufficient',
                                'remaining_sessions' => $lockedPackage->remaining_sessions,
                                'backup_reference' => trim((string) $backupReference),
                            ],
                        ]);

                        return ['status' => $newStatus, 'reasons' => $lockedAssessment['reasons']];
                    }, 5);

                    if ($result === null) {
                        $counts['skipped']++;

                        continue;
                    }

                    if ($result['status'] !== 'active') {
                        $counts['updated']++;
                    }

                    $targetStatus = $result['status'];
                    $reasons = $result['reasons'];
                } else {
                    $reasons = $assessment['reasons'];
                }

                $counts[$targetStatus]++;
                $this->line($package->getKey().': active -> '.$targetStatus.' ('.implode(', ', $reasons).')');
            }
        });

        if (! $apply) {
            $this->components->info('Dry-run: nenhum registro foi alterado.');
        }

        $this->line('Pending: '.$counts['pending']);
        $this->line('Review required: '.$counts['review_required']);
        $this->line('Mantidos ativos: '.$counts['active']);
        $this->line('Atualizados: '.$counts['updated']);
        $this->line('Ignorados por alteração concorrente: '.$counts['skipped']);

        return self::SUCCESS;
    }
}
