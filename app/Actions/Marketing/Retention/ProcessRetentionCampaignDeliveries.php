<?php

namespace App\Actions\Marketing\Retention;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\LegalHold;
use App\Models\RetentionCampaign;
use App\Models\RetentionDelivery;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class ProcessRetentionCampaignDeliveries extends OperationalAction
{
    /** @return array{sent: int, blocked: int, dry_run: bool} */
    public function handle(User $actor, TenantContext $context, RetentionCampaign $campaign, bool $dryRun = false, int $limit = 100): array
    {
        $unit = $this->unit($actor, $context, 'retention.manage');

        if ($campaign->tenant_id !== $context->tenant->getKey() || $campaign->unit_id !== $unit->getKey()) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $context, $campaign, $dryRun, $limit, $unit): array {
            $lockedCampaign = RetentionCampaign::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($campaign->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCampaign->status !== 'active') {
                throw new \InvalidArgumentException('Only active campaigns can process deliveries.');
            }

            $now = now();
            $query = RetentionDelivery::query()
                ->where('tenant_id', $lockedCampaign->tenant_id)
                ->where('unit_id', $lockedCampaign->unit_id)
                ->where('retention_campaign_id', $lockedCampaign->getKey())
                ->whereIn('status', ['pending', 'failed'])
                ->where(function ($query) use ($now): void {
                    $query->whereNull('available_at')->orWhere('available_at', '<=', $now);
                })
                ->orderBy('id')
                ->limit(max(1, $limit));

            $deliveries = DB::getDriverName() === 'pgsql'
                ? $query->lock('FOR UPDATE SKIP LOCKED')->get()
                : $query->lockForUpdate()->get();

            $sent = $blocked = 0;

            foreach ($deliveries as $delivery) {
                $customer = Customer::query()
                    ->where('tenant_id', $lockedCampaign->tenant_id)
                    ->where('unit_id', $lockedCampaign->unit_id)
                    ->whereKey($delivery->customer_id)
                    ->first();

                if (! $this->canDeliverTo($lockedCampaign, $customer)) {
                    if (! $dryRun) {
                        $delivery->forceFill([
                            'status' => 'blocked',
                            'available_at' => null,
                            'last_error' => 'Delivery blocked by current consent, legal hold, inactive, or anonymized customer state.',
                        ])->save();
                        $this->events->record($actor, $context, 'retention.delivery.blocked', $delivery, [
                            'status' => $delivery->status,
                            'customer_id' => $delivery->customer_id,
                        ]);
                    }

                    $blocked++;

                    continue;
                }

                if ($dryRun) {
                    $sent++;

                    continue;
                }

                $delivery->forceFill([
                    'status' => 'sent',
                    'attempts' => $delivery->attempts + 1,
                    'last_error' => null,
                    'available_at' => null,
                    'sent_at' => $now,
                ])->save();
                $this->events->record($actor, $context, 'retention.delivery.sent', $delivery, [
                    'status' => $delivery->status,
                    'customer_id' => $delivery->customer_id,
                ]);
                $sent++;
            }

            return compact('sent', 'blocked') + ['dry_run' => $dryRun];
        }, 5);
    }

    private function canDeliverTo(RetentionCampaign $campaign, ?Customer $customer): bool
    {
        return $customer !== null
            && $customer->status === 'active'
            && $customer->anonymized_at === null
            && $customer->communicationPreferences()
                ->where('channel', $campaign->channel)
                ->where('opted_in', true)
                ->exists()
            && ! LegalHold::query()
                ->where('tenant_id', $campaign->tenant_id)
                ->where('unit_id', $campaign->unit_id)
                ->where('customer_id', $customer->getKey())
                ->whereNull('released_at')
                ->exists();
    }
}
