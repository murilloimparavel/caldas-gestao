<?php

namespace App\Actions\Privacy;

use App\Actions\Operational\OperationalAction;
use App\Models\LegalHold;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ReleaseLegalHold extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, LegalHold $hold): LegalHold
    {
        $unit = $this->unit($actor, $context, 'retention.legal_hold');
        if ($hold->tenant_id !== $context->tenant->getKey() || $hold->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The legal hold belongs to another workspace.');
        }

        return DB::transaction(function () use ($actor, $context, $hold): LegalHold {
            $locked = LegalHold::query()->whereKey($hold->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->released_at === null) {
                $locked->forceFill(['released_by_user_id' => $actor->getKey(), 'released_at' => now()])->save();
                $this->events->record($actor, $context, 'privacy.legal_hold.released', $locked, ['legal_hold_id' => $locked->getKey()]);
            }

            return $locked->fresh();
        }, 5);
    }
}
