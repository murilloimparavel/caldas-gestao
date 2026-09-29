<?php

namespace App\Actions\OnlineBooking;

use App\Models\OnlineBookingDraft;
use App\Models\OnlineBookingSetting;
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
            $setting = $unit->onlineBookingSetting()->first();
            $publicDomainId = $setting instanceof OnlineBookingSetting ? $setting->public_domain_id : null;
            $publicSlug = $setting instanceof OnlineBookingSetting ? ($setting->public_slug ?? $unit->slug) : $unit->slug;
            $site = OnlineBookingSite::query()->firstOrCreate(
                ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey()],
                ['public_domain_id' => $publicDomainId, 'public_slug' => $publicSlug],
            );
            $site->forceFill([
                'public_domain_id' => $publicDomainId ?? $site->public_domain_id,
                'public_slug' => $setting instanceof OnlineBookingSetting ? ($setting->public_slug ?? $site->public_slug) : $site->public_slug,
                'template_key' => $site->template_key ?: 'essential',
            ])->save();

            if (! $site->draft()->exists()) {
                $content = $this->content($context, $site->template_key);
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
    public function content(TenantContext $context, ?string $templateKey = null): array
    {
        $unit = $context->unit;
        abort_unless($unit instanceof Unit, 403);
        $setting = $unit->onlineBookingSetting()->first();
        $brandColor = $setting instanceof OnlineBookingSetting ? $setting->brand_color : null;
        $description = $setting instanceof OnlineBookingSetting ? $setting->description : null;
        $publicHours = $setting instanceof OnlineBookingSetting ? $setting->public_hours : null;
        $bookingFlow = $setting instanceof OnlineBookingSetting ? $setting->booking_flow : null;
        $minimumNoticeMinutes = $setting instanceof OnlineBookingSetting ? $setting->minimum_notice_minutes : null;
        $identity = $setting instanceof OnlineBookingSetting ? [
            'description' => $setting->description,
            'cover_image_path' => $setting->cover_image_path,
            'logo_image_path' => $setting->logo_image_path,
            'whatsapp_phone' => $setting->whatsapp_phone,
            'phone' => $setting->phone,
            'instagram_url' => $setting->instagram_url,
            'facebook_url' => $setting->facebook_url,
            'website_url' => $setting->website_url,
        ] : [];

        return [
            'schema_version' => 1,
            'theme' => ['brand_color' => $brandColor ?? '#2563eb'],
            'seo' => ['title' => $unit->name, 'description' => $description],
            'identity' => $identity,
            'sections' => [
                ['key' => 'hero', 'enabled' => true], ['key' => 'services', 'enabled' => true], ['key' => 'professionals', 'enabled' => true],
                ['key' => 'gallery', 'enabled' => true], ['key' => 'hours', 'enabled' => true], ['key' => 'contact', 'enabled' => true],
            ],
            'service_ids' => Service::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('online_booking_enabled', true)->pluck('id')->values()->all(),
            'professional_ids' => Professional::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->where('online_booking_enabled', true)->pluck('id')->values()->all(),
            'gallery' => $unit->onlineBookingGalleryImages->map(fn ($image): array => ['path' => $image->path, 'thumbnail_path' => $image->thumbnail_path, 'alt_text' => $image->alt_text])->values()->all(),
            'public_hours' => $publicHours ?? [],
            'booking_policy' => ['booking_flow' => $bookingFlow ?? 'service_first', 'minimum_notice_minutes' => $minimumNoticeMinutes ?? 0],
            'appearance' => OnlineBookingAppearance::defaults($templateKey, $unit->name),
        ];
    }
}
