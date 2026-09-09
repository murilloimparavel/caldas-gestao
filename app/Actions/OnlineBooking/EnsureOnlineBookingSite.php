<?php

namespace App\Actions\OnlineBooking;

use App\Models\OnlineBookingDraft;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

final class EnsureOnlineBookingSite
{
    public function handle(TenantContext $context): OnlineBookingSite
    {
        $unit = $context->unit;

        abort_unless($unit instanceof Unit, 403);

        return DB::transaction(function () use ($context, $unit): OnlineBookingSite {
            $setting = $unit->onlineBookingSetting;
            $site = OnlineBookingSite::query()->firstOrCreate(
                ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey()],
                ['public_domain_id' => $setting?->public_domain_id, 'public_slug' => $setting?->public_slug ?? $unit->slug],
            );
            $site->forceFill([
                'public_domain_id' => $setting?->public_domain_id ?? $site->public_domain_id,
                'public_slug' => $setting?->public_slug ?? $site->public_slug,
            ])->save();

            if (! $site->draft()->exists()) {
                $content = $this->content($context);
                $draft = new OnlineBookingDraft;
                $draft->forceFill([
                    'tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey(), 'site_id' => $site->getKey(),
                    'revision' => 1, 'content' => $content, 'content_hash' => hash('sha256', json_encode($content, JSON_THROW_ON_ERROR)),
                    'updated_by' => $context->user->getKey(),
                ])->save();
                $site->forceFill(['draft_revision' => $draft->revision])->save();
            }

            return $site->fresh(['draft', 'activePublication']);
        });
    }

    /** @return array<string, mixed> */
    public function content(TenantContext $context): array
    {
        $unit = $context->unit;
        abort_unless($unit instanceof Unit, 403);
        $setting = $unit->onlineBookingSetting;

        return [
            'schema_version' => 1,
            'theme' => ['brand_color' => $setting?->brand_color ?? '#2563eb'],
            'seo' => ['title' => $unit->name, 'description' => $setting?->description],
            'identity' => [
                'description' => $setting?->description, 'cover_image_path' => $setting?->cover_image_path,
                'whatsapp_phone' => $setting?->whatsapp_phone, 'phone' => $setting?->phone,
                'instagram_url' => $setting?->instagram_url, 'facebook_url' => $setting?->facebook_url, 'website_url' => $setting?->website_url,
            ],
            'sections' => [
                ['key' => 'hero', 'enabled' => true], ['key' => 'services', 'enabled' => true], ['key' => 'professionals', 'enabled' => true],
                ['key' => 'gallery', 'enabled' => true], ['key' => 'hours', 'enabled' => true], ['key' => 'contact', 'enabled' => true],
            ],
            'service_ids' => Service::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('online_booking_enabled', true)->pluck('id')->values()->all(),
            'professional_ids' => Professional::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('online_booking_enabled', true)->pluck('id')->values()->all(),
            'gallery' => $unit->onlineBookingGalleryImages->map(fn ($image): array => ['path' => $image->path, 'thumbnail_path' => $image->thumbnail_path, 'alt_text' => $image->alt_text])->values()->all(),
            'public_hours' => $setting?->public_hours ?? [],
            'booking_policy' => ['booking_flow' => $setting?->booking_flow ?? 'service_first', 'minimum_notice_minutes' => $setting?->minimum_notice_minutes ?? 0],
        ];
    }
}
