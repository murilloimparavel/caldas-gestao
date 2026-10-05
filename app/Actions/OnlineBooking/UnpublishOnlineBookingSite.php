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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UnpublishOnlineBookingSite extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, ?int $expectedVersion = null): OnlineBookingSite
    {
        $unit = $this->unit($actor, $context, 'unit.update');

        return DB::transaction(function () use ($context, $unit, $expectedVersion): OnlineBookingSite {
            $site = OnlineBookingSite::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->lockForUpdate()->firstOrFail();

            if ($expectedVersion !== null && $site->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The booking site was modified concurrently.');
            }

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
