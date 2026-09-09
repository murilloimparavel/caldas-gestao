<?php

namespace App\Actions\OnlineBooking;

use App\Actions\Operational\OperationalAction;
use App\Models\OnlineBookingDraft;
use App\Models\OnlineBookingSite;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SaveOnlineBookingDraft extends OperationalAction
{
    /** @param array<string, mixed> $content */
    public function handle(User $actor, TenantContext $context, array $content, int $expectedRevision): OnlineBookingDraft
    {
        $unit = $this->unit($actor, $context, 'unit.update');

        return DB::transaction(function () use ($actor, $context, $unit, $content, $expectedRevision): OnlineBookingDraft {
            $site = $this->site($context, $unit);
            $draft = $site->draft()->lockForUpdate()->first();

            if ($draft !== null && $draft->revision !== $expectedRevision) {
                throw new ConflictHttpException('O rascunho foi alterado em outra sessão.');
            }

            $normalized = $this->normalize($content, is_array($draft?->content) ? $draft->content : []);
            $revision = ($draft?->revision ?? 0) + 1;
            $hash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
            $draft ??= new OnlineBookingDraft;
            $draft->forceFill([
                'tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey(), 'site_id' => $site->getKey(),
                'revision' => $revision, 'content' => $normalized, 'content_hash' => $hash, 'updated_by' => $actor->getKey(),
            ])->save();
            $site->forceFill(['draft_revision' => $revision, 'lock_version' => $site->lock_version + 1])->save();

            return $draft->fresh();
        });
    }

    /** @param array<string, mixed> $content */
    /** @param array<string, mixed> $content @param array<string, mixed> $base */
    private function normalize(array $content, array $base = []): array
    {
        $merged = array_replace($base, $content);

        return [
            'schema_version' => (int) ($merged['schema_version'] ?? 1),
            'theme' => is_array($merged['theme'] ?? null) ? $merged['theme'] : [],
            'seo' => is_array($merged['seo'] ?? null) ? $merged['seo'] : [],
            'identity' => is_array($merged['identity'] ?? null) ? $merged['identity'] : [],
            'sections' => is_array($merged['sections'] ?? null) ? array_values($merged['sections']) : [],
            'service_ids' => is_array($merged['service_ids'] ?? null) ? array_values($merged['service_ids']) : [],
            'professional_ids' => is_array($merged['professional_ids'] ?? null) ? array_values($merged['professional_ids']) : [],
            'public_hours' => is_array($merged['public_hours'] ?? null) ? $merged['public_hours'] : [],
            'booking_policy' => is_array($merged['booking_policy'] ?? null) ? $merged['booking_policy'] : [],
        ];
    }

    private function site(TenantContext $context, Unit $unit): OnlineBookingSite
    {
        $setting = $unit->onlineBookingSetting;

        return OnlineBookingSite::query()->firstOrCreate(
            ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey()],
            ['public_domain_id' => $setting?->public_domain_id, 'public_slug' => $setting?->public_slug ?? $unit->slug],
        );
    }
}
