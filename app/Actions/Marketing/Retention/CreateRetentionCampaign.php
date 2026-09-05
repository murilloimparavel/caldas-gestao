<?php

namespace App\Actions\Marketing\Retention;

use App\Actions\Operational\OperationalAction;
use App\Models\RetentionCampaign;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateRetentionCampaign extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): RetentionCampaign
    {
        $unit = $this->unit($actor, $context, 'retention.manage');

        return DB::transaction(function () use ($actor, $context, $unit, $data): RetentionCampaign {
            $campaign = RetentionCampaign::query()->create([
                'id' => (string) Str::uuid7(), 'tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey(),
                'created_by_user_id' => $actor->getKey(), ...$data,
            ]);
            $this->events->record($actor, $context, 'retention.campaign.created', $campaign);

            return $campaign;
        }, 5);
    }
}
