<?php

namespace App\Actions\Privacy;

use App\Models\Customer;
use App\Models\DataRetentionPolicy;
use App\Models\LegalHold;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnonymizeExpiredCustomers
{
    public function __construct(
        private readonly AuditEventWriter $audit = new AuditEventWriter,
        private readonly OutboxEventStore $outbox = new OutboxEventStore,
        private readonly AuthorizationService $authorization = new AuthorizationService,
    ) {}

    /**
     * @return array{eligible:int, anonymized:int, held:int, skipped:int, dry_run:bool}
     */
    public function handle(?User $actor = null, ?TenantContext $context = null, bool $dryRun = false, ?int $limit = null): array
    {
        if (($actor === null) !== ($context === null)) {
            throw new AuthorizationException('An authenticated actor and tenant context must be provided together.');
        }

        $tenantId = $context?->tenant->getKey();
        if ($actor !== null && $context !== null && ! $this->authorization->can($actor, $context, 'retention.anonymize', $context->unit)) {
            throw new AuthorizationException('An explicit retention anonymization permission is required.');
        }

        $summary = ['eligible' => 0, 'anonymized' => 0, 'held' => 0, 'skipped' => 0, 'dry_run' => $dryRun];
        $policies = DataRetentionPolicy::query()
            ->where('data_class', 'customer')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->get();

        foreach ($policies as $policy) {
            if (! $policy->enabled) {
                continue;
            }

            $query = Customer::query()
                ->where('tenant_id', $policy->tenant_id)
                ->whereNull('anonymized_at')
                ->when($policy->unit_id !== null, fn ($builder) => $builder->where('unit_id', $policy->unit_id));

            if ($policy->unit_id === null) {
                $overriddenUnitIds = $policies
                    ->where('tenant_id', $policy->tenant_id)
                    ->whereNotNull('unit_id')
                    ->pluck('unit_id')
                    ->all();

                if ($overriddenUnitIds !== []) {
                    $query->whereNotIn('unit_id', $overriddenUnitIds);
                }
            }

            if ($context?->unit !== null) {
                $query->where('unit_id', $context->unit->getKey());
            }

            $anchor = $policy->anchor === 'created_at' ? 'created_at' : 'last_activity_at';
            $query->whereNotNull($anchor)->where($anchor, '<=', now()->subDays($policy->retention_days));
            $customers = $query->orderBy('id')->limit($limit ?? 1000)->get();

            foreach ($customers as $customer) {
                $summary['eligible']++;
                if ($this->isHeld($customer)) {
                    $summary['held']++;

                    continue;
                }

                if ($dryRun) {
                    continue;
                }

                if ($this->anonymize($customer, $actor, $context)) {
                    $summary['anonymized']++;
                } else {
                    $summary['skipped']++;
                }
            }
        }

        return $summary;
    }

    private function isHeld(Customer $customer): bool
    {
        return LegalHold::query()
            ->where('tenant_id', $customer->tenant_id)
            ->whereNull('released_at')
            ->where(function ($query) use ($customer): void {
                $query->where(function ($target) use ($customer): void {
                    $target->where('customer_id', $customer->getKey())
                        ->where(function ($unit) use ($customer): void {
                            $unit->whereNull('unit_id')->orWhere('unit_id', $customer->unit_id);
                        });
                })->orWhere(function ($target) use ($customer): void {
                    $target->where('resource_type', 'customer')->where('resource_id', $customer->getKey());
                });
            })->exists();
    }

    private function anonymize(Customer $customer, ?User $actor, ?TenantContext $context): bool
    {
        return DB::transaction(function () use ($customer, $actor): bool {
            $locked = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->anonymized_at !== null || $this->isHeld($locked)) {
                return false;
            }

            $now = now();
            $identifier = substr(str_replace('-', '', (string) $locked->getKey()), 0, 20);
            $locked->forceFill([
                'name' => 'ANON-'.$identifier,
                'email' => 'anon-'.$identifier.'@deleted.local',
                'phone' => null,
                'birth_date' => null,
                'notes' => null,
                'status' => 'inactive',
                'retention_status' => 'anonymized',
                'anonymized_at' => $now,
                'anonymization_version' => 'v1',
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $eventId = (string) Str::uuid7();
            $metadata = ['customer_id' => $locked->getKey(), 'anonymization_version' => 'v1'];
            $this->audit->record([
                'event_id' => $eventId,
                'tenant_id' => $locked->tenant_id,
                'unit_id' => $locked->unit_id,
                'actor_user_id' => $actor?->getKey(),
                'action' => 'privacy.customer.anonymized',
                'resource_type' => 'customer',
                'resource_id' => $locked->getKey(),
                'metadata' => $metadata,
                'occurred_at' => $now,
            ]);
            $this->outbox->enqueue([
                'event_id' => $eventId,
                'tenant_id' => $locked->tenant_id,
                'unit_id' => $locked->unit_id,
                'actor_user_id' => $actor?->getKey(),
                'aggregate_type' => 'customer',
                'aggregate_id' => $locked->getKey(),
                'aggregate_version' => $locked->lock_version,
                'event_type' => 'privacy.customer.anonymized',
                'payload' => $metadata,
                'occurred_at' => $now,
            ]);

            return true;
        }, 5);
    }
}
