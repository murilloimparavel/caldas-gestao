<?php

namespace App\Actions\OnlineBooking;

use App\Models\OnlineBookingDraft;
use App\Models\OnlineBookingPublication;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class RestoreOnlineBookingPublication
{
    public function handle(User $actor, TenantContext $context, OnlineBookingPublication $publication): OnlineBookingDraft
    {
        if ($publication->tenant_id !== $context->tenant->getKey() || $publication->unit_id !== $context->unit?->getKey()) {
            throw new AuthorizationException('A publicação não pertence à unidade ativa.');
        }

        return DB::transaction(function () use ($actor, $publication): OnlineBookingDraft {
            $site = $publication->site()->lockForUpdate()->firstOrFail();
            $draft = $site->draft()->lockForUpdate()->firstOrFail();
            $draft->forceFill(['revision' => $draft->revision + 1, 'content' => $publication->content, 'content_hash' => $publication->content_hash, 'updated_by' => $actor->getKey()])->save();
            $site->forceFill(['draft_revision' => $draft->revision, 'lock_version' => $site->lock_version + 1])->save();

            return $draft->fresh();
        });
    }
}
