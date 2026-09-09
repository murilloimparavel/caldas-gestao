<?php

namespace App\Actions\OnlineBooking;

use App\Actions\Operational\OperationalAction;
use App\Enums\OnlineBookingPublicationStatus;
use App\Models\OnlineBookingPublication;
use App\Models\OnlineBookingSite;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PublishOnlineBookingSite extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, int $expectedRevision): OnlineBookingPublication
    {
        $unit = $this->unit($actor, $context, 'unit.update');

        return DB::transaction(function () use ($actor, $context, $unit, $expectedRevision): OnlineBookingPublication {
            $site = OnlineBookingSite::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->lockForUpdate()->firstOrFail();
            $draft = $site->draft()->lockForUpdate()->firstOrFail();

            if ($draft->revision !== $expectedRevision) {
                throw new ConflictHttpException('O rascunho foi alterado em outra sessão.');
            }

            $active = $site->activePublication()->lockForUpdate()->first();
            $active?->update(['superseded_at' => now()]);
            $version = ((int) OnlineBookingPublication::query()->where('site_id', $site->getKey())->max('version')) + 1;
            $publication = OnlineBookingPublication::query()->create([
                'tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey(), 'site_id' => $site->getKey(),
                'public_domain_id' => $site->public_domain_id, 'version' => $version, 'source_revision' => $draft->revision,
                'content' => $draft->content, 'content_hash' => $draft->content_hash, 'template_key' => $site->template_key,
                'public_slug' => $site->public_slug, 'published_by' => $actor->getKey(), 'published_at' => now(),
            ]);
            $site->forceFill(['active_publication_id' => $publication->getKey(), 'status' => OnlineBookingPublicationStatus::Published, 'published_at' => now(), 'unpublished_at' => null, 'lock_version' => $site->lock_version + 1])->save();

            return $publication;
        });
    }
}
