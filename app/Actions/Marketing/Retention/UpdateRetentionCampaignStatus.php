<?php

namespace App\Actions\Marketing\Retention;

use App\Actions\Operational\OperationalAction;
use App\Models\RetentionCampaign;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class UpdateRetentionCampaignStatus extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, RetentionCampaign $campaign, string $status): RetentionCampaign
    {
        $unit = $this->unit($actor, $context, 'retention.manage');
        if ($campaign->tenant_id !== $context->tenant->getKey() || $campaign->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The campaign belongs to another workspace.');
        }

        return DB::transaction(function () use ($actor, $context, $campaign, $status): RetentionCampaign {
            $locked = RetentionCampaign::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();
            $allowed = ['draft' => ['active'], 'active' => ['paused', 'completed'], 'paused' => ['active', 'completed'], 'completed' => []];
            if (! in_array($status, $allowed[$locked->status] ?? [], true) && $locked->status !== $status) {
                throw new AuthorizationException('The campaign status transition is not allowed.');
            }
            if ($locked->status !== $status) {
                $locked->forceFill(['status' => $status, 'lock_version' => $locked->lock_version + 1])->save();
                $this->events->record($actor, $context, 'retention.campaign.status_changed', $locked, [
                    'from_status' => $campaign->status,
                    'to_status' => $status,
                ]);
            }

            return $locked->fresh();
        }, 5);
    }
}
