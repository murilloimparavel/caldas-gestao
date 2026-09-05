<?php

namespace App\Console\Commands;

use App\Actions\Marketing\Retention\DispatchRetentionCampaign as Action;
use App\Models\RetentionCampaign;
use App\Support\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('retention:campaign-dispatch {campaign?} {--dry-run}')]
#[Description('Queue internal retention deliveries without calling an external provider')]
class DispatchRetentionCampaign extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(Action $action): int
    {
        $query = RetentionCampaign::query()->where('status', 'active')->whereNotNull('audience_snapshot_at');
        if ($this->argument('campaign')) {
            $query->whereKey($this->argument('campaign'));
        }
        foreach ($query->get() as $campaign) {
            $actor = $campaign->createdBy;
            if ($actor === null) {
                continue;
            }
            $result = $action->handle($actor, TenantContext::forUser($actor, $campaign->tenant_id, $campaign->unit_id), $campaign, (bool) $this->option('dry-run'));
            $this->line($campaign->getKey().': '.$result['queued'].' queued, '.$result['blocked'].' blocked');
        }

        return self::SUCCESS;
    }
}
