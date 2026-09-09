<?php

namespace App\Actions\OnlineBooking;

use App\Actions\Operational\OperationalAction;
use App\Models\OnlineBookingCampaignLink;
use App\Models\OnlineBookingSite;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

final class CreateOnlineBookingCampaignLink extends OperationalAction
{
    /** @param array<string, string|null> $data */
    public function handle(User $actor, TenantContext $context, array $data): OnlineBookingCampaignLink
    {
        $unit = $this->unit($actor, $context, 'unit.update');
        $site = OnlineBookingSite::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->firstOrFail();

        return OnlineBookingCampaignLink::query()->create([
            ...$data,
            'id' => (string) Str::uuid7(),
            'tenant_id' => $context->tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'site_id' => $site->getKey(),
            'short_code' => Str::lower(Str::random(10)),
            'created_by' => $actor->getKey(),
        ]);
    }
}
