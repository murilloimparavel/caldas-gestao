<?php

namespace App\Actions\OnlineBooking;

use App\Actions\Operational\OperationalAction;
use App\Enums\OnlineBookingHandleStatus;
use App\Enums\OnlineBookingPublicationStatus;
use App\Models\OnlineBookingHandle;
use App\Models\OnlineBookingSite;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class UnpublishOnlineBookingSite extends OperationalAction
{
    public function handle(User $actor, TenantContext $context): OnlineBookingSite
    {
        $unit = $this->unit($actor, $context, 'unit.update');

        return DB::transaction(function () use ($context, $unit): OnlineBookingSite {
            $site = OnlineBookingSite::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->lockForUpdate()->firstOrFail();
            OnlineBookingHandle::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('status', OnlineBookingHandleStatus::Current->value)
                ->update([
                    'status' => OnlineBookingHandleStatus::Reserved->value,
                    'redirect_until' => null,
                    'updated_at' => now(),
                ]);
            $site->forceFill(['active_publication_id' => null, 'status' => OnlineBookingPublicationStatus::Unpublished, 'unpublished_at' => now(), 'lock_version' => $site->lock_version + 1])->save();

            return $site->fresh();
        });
    }
}
