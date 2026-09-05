<?php

namespace App\Actions\Marketing\Retention;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\LegalHold;
use App\Models\RetentionCampaign;
use App\Models\RetentionCampaignRecipient;
use App\Models\RetentionDelivery;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DispatchRetentionCampaign extends OperationalAction
{
    /** @return array{queued: int, existing: int, blocked: int, dry_run: bool} */
    public function handle(User $actor, TenantContext $context, RetentionCampaign $campaign, bool $dryRun = false): array
    {
        $unit = $this->unit($actor, $context, 'retention.manage');
        abort_unless($campaign->tenant_id === $context->tenant->getKey() && $campaign->unit_id === $unit->getKey(), 403);

        return DB::transaction(function () use ($actor, $context, $campaign, $dryRun, $unit): array {
            $lockedCampaign = RetentionCampaign::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($campaign->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCampaign->status !== 'active') {
                throw new \InvalidArgumentException('Only active campaigns can be dispatched.');
            }
            if ($lockedCampaign->audience_snapshot_at === null) {
                throw new \InvalidArgumentException('Build the campaign audience before dispatching.');
            }

            $queued = $existing = $blocked = 0;
            $recipients = RetentionCampaignRecipient::query()
                ->where('tenant_id', $lockedCampaign->tenant_id)
                ->where('unit_id', $lockedCampaign->unit_id)
                ->where('retention_campaign_id', $lockedCampaign->getKey())
                ->where('status', 'selected')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($recipients as $recipient) {
                $customer = Customer::query()
                    ->where('tenant_id', $lockedCampaign->tenant_id)
                    ->where('unit_id', $lockedCampaign->unit_id)
                    ->whereKey($recipient->customer_id)
                    ->first();
                $allowed = $this->canDispatchTo($lockedCampaign, $customer);
                $key = $this->deliveryKey($lockedCampaign, $recipient);

                if ($dryRun) {
                    $allowed ? $queued++ : $blocked++;

                    continue;
                }

                $delivery = RetentionDelivery::query()
                    ->where('tenant_id', $lockedCampaign->tenant_id)
                    ->where('idempotency_key', $key)
                    ->lockForUpdate()
                    ->first();

                if ($delivery !== null) {
                    $existing++;

                    continue;
                }

                $delivery = RetentionDelivery::query()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $lockedCampaign->tenant_id,
                    'unit_id' => $lockedCampaign->unit_id,
                    'retention_campaign_id' => $lockedCampaign->getKey(),
                    'retention_campaign_recipient_id' => $recipient->getKey(),
                    'customer_id' => $recipient->customer_id,
                    'channel' => $lockedCampaign->channel,
                    'status' => $allowed ? 'pending' : 'blocked',
                    'idempotency_key' => $key,
                    'available_at' => $allowed ? now() : null,
                ]);
                $this->events->record(
                    $actor,
                    $context,
                    $allowed ? 'retention.delivery.queued' : 'retention.delivery.blocked',
                    $delivery,
                    ['status' => $delivery->status, 'customer_id' => $delivery->customer_id],
                );

                $allowed ? $queued++ : $blocked++;
            }

            return compact('queued', 'existing', 'blocked') + ['dry_run' => $dryRun];
        }, 5);
    }

    private function canDispatchTo(RetentionCampaign $campaign, ?Customer $customer): bool
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

    private function deliveryKey(RetentionCampaign $campaign, RetentionCampaignRecipient $recipient): string
    {
        return 'retention:'.$campaign->getKey().':'.$recipient->customer_id.':'.$campaign->channel;
    }
}
