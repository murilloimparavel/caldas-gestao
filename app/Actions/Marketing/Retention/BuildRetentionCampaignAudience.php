<?php

namespace App\Actions\Marketing\Retention;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\LegalHold;
use App\Models\RetentionCampaign;
use App\Models\RetentionCampaignRecipient;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BuildRetentionCampaignAudience extends OperationalAction
{
    /** @return array{campaign: RetentionCampaign, selected: int, existing: bool} */
    public function handle(User $actor, TenantContext $context, RetentionCampaign $campaign, bool $dryRun = false): array
    {
        $unit = $this->unit($actor, $context, 'retention.manage');
        abort_unless($campaign->tenant_id === $context->tenant->getKey() && $campaign->unit_id === $unit->getKey(), 403);

        return DB::transaction(function () use ($actor, $context, $campaign, $dryRun, $unit): array {
            $locked = RetentionCampaign::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($campaign->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->audience_snapshot_at !== null) {
                return [
                    'campaign' => $locked,
                    'selected' => $locked->recipients()->where('status', 'selected')->count(),
                    'existing' => true,
                ];
            }

            $definition = $locked->segment_definition;
            $inactiveDays = data_get($definition, 'inactive_days');
            $retentionStatus = data_get($definition, 'retention_status');
            $customers = Customer::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('unit_id', $locked->unit_id)
                ->where('status', 'active')
                ->whereNull('anonymized_at')
                ->when($inactiveDays !== null, fn ($query) => $query->inactiveFor(max(1, (int) $inactiveDays)))
                ->when($retentionStatus !== null, fn ($query) => $query->where('retention_status', $retentionStatus))
                ->with([
                    'communicationPreferences' => fn ($query) => $query
                        ->where('channel', $locked->channel)
                        ->where('opted_in', true),
                ])
                ->orderBy('id')
                ->get();

            $heldCustomerIds = LegalHold::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('unit_id', $locked->unit_id)
                ->whereIn('customer_id', $customers->modelKeys())
                ->whereNull('released_at')
                ->pluck('customer_id')
                ->flip();

            $eligible = $customers->filter(fn (Customer $customer): bool => $customer->communicationPreferences->isNotEmpty()
                && ! $heldCustomerIds->has($customer->getKey()));

            if ($dryRun) {
                return ['campaign' => $locked, 'selected' => $eligible->count(), 'existing' => false];
            }

            foreach ($customers as $customer) {
                $preference = $customer->communicationPreferences->first();
                $selected = $preference !== null && ! $heldCustomerIds->has($customer->getKey());

                RetentionCampaignRecipient::query()->firstOrCreate(
                    [
                        'tenant_id' => $locked->tenant_id,
                        'retention_campaign_id' => $locked->getKey(),
                        'customer_id' => $customer->getKey(),
                        'channel' => $locked->channel,
                    ],
                    [
                        'id' => (string) Str::uuid7(),
                        'unit_id' => $locked->unit_id,
                        'status' => $selected ? 'selected' : 'excluded',
                        'selected_at' => now(),
                        'consent_snapshot_at' => $preference?->consented_at,
                        'last_activity_snapshot_at' => $customer->last_activity_at,
                        'retention_status_snapshot' => $customer->retention_status,
                    ],
                );
            }

            $locked->forceFill(['audience_snapshot_at' => now(), 'lock_version' => $locked->lock_version + 1])->save();
            $this->events->record($actor, $context, 'retention.campaign.audience_snapshotted', $locked, ['quantity' => $eligible->count()]);

            return ['campaign' => $locked->fresh(), 'selected' => $eligible->count(), 'existing' => false];
        }, 5);
    }
}
