<?php

namespace App\Console\Commands;

use App\Actions\Marketing\Retention\BuildRetentionCampaignAudience as Action;
use App\Models\RetentionCampaign;
use App\Support\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('retention:campaign-audience {campaign?} {--dry-run}')]
#[Description('Snapshot eligible consented customers for retention campaigns')]
class BuildRetentionCampaignAudience extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Action $action): int
    {
        $query = RetentionCampaign::query()->whereNull('audience_snapshot_at');
        if ($this->argument('campaign')) {
            $query->whereKey($this->argument('campaign'));
        }
        foreach ($query->get() as $campaign) {
            $actor = $campaign->createdBy;
            if ($actor === null) {
                continue;
            }
            $result = $action->handle($actor, TenantContext::forUser($actor, $campaign->tenant_id, $campaign->unit_id), $campaign, (bool) $this->option('dry-run'));
            $this->line($campaign->getKey().': '.$result['selected'].' selected');
        }

        return self::SUCCESS;
    }
}
