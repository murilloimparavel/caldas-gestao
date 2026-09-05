<?php

namespace App\Console\Commands;

use App\Models\CustomerPackage;
use App\Support\AuditEventWriter;
use App\Support\OutboxEventStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('app:expire-customer-packages
    {--limit=500 : Maximum packages to expire in one run}')]
#[Description('Expire active or exhausted customer packages that reached their expiry date')]
final class ExpireCustomerPackages extends Command
{
    public function handle(AuditEventWriter $audit, OutboxEventStore $outbox): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $expiredCount = 0;

        CustomerPackage::query()
            ->select('id')
            ->whereIn('status', ['active', 'exhausted'])
            ->whereDate('expires_at', '<', today())
            ->orderBy('id')
            ->limit($limit)
            ->chunkById(100, function ($packages) use (&$expiredCount, $audit, $outbox): void {
                foreach ($packages as $package) {
                    $expired = DB::transaction(function () use ($package, $audit, $outbox): bool {
                        /** @var CustomerPackage|null $locked */
                        $locked = CustomerPackage::query()
                            ->whereKey($package->getKey())
                            ->whereIn('status', ['active', 'exhausted'])
                            ->whereDate('expires_at', '<', today())
                            ->lockForUpdate()
                            ->first();

                        if ($locked === null) {
                            return false;
                        }

                        $locked->forceFill([
                            'status' => 'expired',
                            'lock_version' => $locked->lock_version + 1,
                        ])->save();

                        $eventId = (string) Str::uuid7();
                        $occurredAt = now();
                        $metadata = [
                            'customer_package_id' => $locked->getKey(),
                            'remaining_sessions' => $locked->remaining_sessions,
                            'expires_at' => $locked->expires_at?->toDateString(),
                            'status' => 'expired',
                        ];

                        $audit->record([
                            'event_id' => $eventId,
                            'tenant_id' => $locked->tenant_id,
                            'unit_id' => $locked->unit_id,
                            'action' => 'customer_package.expired',
                            'resource_type' => 'customer_package',
                            'resource_id' => $locked->getKey(),
                            'request_id' => $eventId,
                            'correlation_id' => $eventId,
                            'metadata' => $metadata,
                            'occurred_at' => $occurredAt,
                        ]);

                        $outbox->enqueue([
                            'event_id' => $eventId,
                            'tenant_id' => $locked->tenant_id,
                            'unit_id' => $locked->unit_id,
                            'aggregate_type' => 'customer_package',
                            'aggregate_id' => $locked->getKey(),
                            'aggregate_version' => $locked->lock_version,
                            'event_type' => 'customer_package.expired',
                            'correlation_id' => $eventId,
                            'payload' => [
                                'resource_type' => 'customer_package',
                                'resource_id' => $locked->getKey(),
                                'status' => 'expired',
                            ],
                            'occurred_at' => $occurredAt,
                            'available_at' => $occurredAt,
                            'created_at' => $occurredAt,
                        ]);

                        return true;
                    }, 5);

                    $expiredCount += $expired ? 1 : 0;
                }
            });

        $this->components->info("Expired {$expiredCount} customer package(s).");

        return self::SUCCESS;
    }
}
