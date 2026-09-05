<?php

namespace App\Console\Commands;

use App\Actions\Marketing\Retention\ProcessRetentionCampaignDeliveries as Action;
use App\Models\RetentionCampaign;
use App\Support\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('retention:campaign-process {campaign?} {--dry-run} {--limit=100}')]
#[Description('Process internal retention delivery handoffs without calling an external provider')]
final class ProcessRetentionCampaignDeliveries extends Command
{
    public function handle(Action $action): int
    {
        $query = RetentionCampaign::query()
            ->where('status', 'active')
            ->whereNotNull('audience_snapshot_at')
            ->with('createdBy')
            ->orderBy('id');

        if ($this->argument('campaign')) {
            $query->whereKey($this->argument('campaign'));
        }

        foreach ($query->cursor() as $campaign) {
            $actor = $campaign->createdBy;

            if ($actor === null) {
                continue;
            }

            $result = $action->handle(
                $actor,
                TenantContext::forUser($actor, $campaign->tenant_id, $campaign->unit_id),
                $campaign,
                (bool) $this->option('dry-run'),
                max(1, (int) $this->option('limit')),
            );

            $this->line($campaign->getKey().': '.$result['sent'].' sent, '.$result['blocked'].' blocked');
        }

        return self::SUCCESS;
    }
}
