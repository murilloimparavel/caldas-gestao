<?php

namespace App\Actions\OnlineBooking;

use App\Actions\Operational\OperationalAction;
use App\Models\OnlineBookingDraft;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

            $baseContent = is_array($draft?->content) ? $draft->content : [];
            $this->validateDocument($context, $unit, $content, $baseContent);
            $normalized = $this->normalize($content, $baseContent, $site->template_key, $unit->name);
            $revision = ($draft !== null ? $draft->revision : 0) + 1;
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

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function normalize(array $content, array $base = [], ?string $templateKey = null, ?string $brandName = null): array
    {
        $merged = array_replace($base, $content);
        $baseAppearance = is_array($base['appearance'] ?? null) ? $base['appearance'] : null;
        $appearance = is_array($content['appearance'] ?? null) ? $content['appearance'] : null;

        return [
            'schema_version' => (int) ($merged['schema_version'] ?? 1),
            'theme' => is_array($merged['theme'] ?? null) ? $merged['theme'] : [],
            'seo' => is_array($merged['seo'] ?? null) ? $merged['seo'] : [],
            'identity' => is_array($merged['identity'] ?? null) ? $merged['identity'] : [],
            'sections' => is_array($merged['sections'] ?? null) ? array_values($merged['sections']) : [],
            'gallery' => is_array($merged['gallery'] ?? null) ? array_values($merged['gallery']) : [],
            'service_ids' => is_array($merged['service_ids'] ?? null) ? array_values($merged['service_ids']) : [],
            'professional_ids' => is_array($merged['professional_ids'] ?? null) ? array_values($merged['professional_ids']) : [],
            'public_hours' => is_array($merged['public_hours'] ?? null) ? $merged['public_hours'] : [],
            'booking_policy' => is_array($merged['booking_policy'] ?? null) ? $merged['booking_policy'] : [],
            'appearance' => OnlineBookingAppearance::normalize($appearance, $templateKey, $baseAppearance, $brandName),
        ];
    }

    private function site(TenantContext $context, Unit $unit): OnlineBookingSite
    {
        $setting = $unit->onlineBookingSetting()->first();
        $publicDomainId = $setting instanceof OnlineBookingSetting ? $setting->public_domain_id : null;
        $publicSlug = $setting instanceof OnlineBookingSetting ? ($setting->public_slug ?? $unit->slug) : $unit->slug;

        return OnlineBookingSite::query()->firstOrCreate(
            ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey()],
            ['public_domain_id' => $publicDomainId, 'public_slug' => $publicSlug],
        );
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $base
     */
    private function validateDocument(TenantContext $context, Unit $unit, array $content, array $base): void
    {
        $serviceIds = array_values(array_filter($content['service_ids'] ?? [], 'is_string'));
        $professionalIds = array_values(array_filter($content['professional_ids'] ?? [], 'is_string'));
        $validServices = Service::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->whereIn('id', $serviceIds)->count();
        $validProfessionals = Professional::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unit->getKey())->whereIn('id', $professionalIds)->count();
        $allowedSections = ['hero', 'services', 'professionals', 'gallery', 'hours', 'contact', 'confirmation', 'seo'];
        $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
        $invalidSection = collect($sections)->first(fn (mixed $section): bool => ! is_array($section) || ! in_array($section['key'] ?? null, $allowedSections, true) || ! is_bool($section['enabled'] ?? null));
        $identity = is_array($content['identity'] ?? null) ? $content['identity'] : [];
        $baseIdentity = is_array($base['identity'] ?? null) ? $base['identity'] : [];
        $gallery = is_array($content['gallery'] ?? null)
            ? $content['gallery']
            : (is_array($base['gallery'] ?? null) ? $base['gallery'] : []);
        $invalidIdentityAsset = collect(['cover_image_path', 'logo_image_path'])->contains(
            fn (string $key): bool => array_key_exists($key, $identity)
                && $identity[$key] !== null
                && ! $this->isUnitBookingAssetPath($unit, $identity[$key]),
        ) || collect(['cover_image_path', 'logo_image_path'])->contains(
            fn (string $key): bool => ! array_key_exists($key, $identity)
                && array_key_exists($key, $baseIdentity)
                && $baseIdentity[$key] !== null
                && ! $this->isUnitBookingAssetPath($unit, $baseIdentity[$key]),
        );
        $invalidGalleryAsset = collect($gallery)->contains(
            fn (mixed $image): bool => ! is_array($image)
                || ! $this->isUnitBookingAssetPath($unit, $image['path'] ?? null)
                || (($image['thumbnail_path'] ?? null) !== null
                    && ! $this->isUnitBookingAssetPath($unit, $image['thumbnail_path'])),
        );

        if ($validServices !== count($serviceIds) || $validProfessionals !== count($professionalIds) || $invalidSection !== null || $invalidIdentityAsset || $invalidGalleryAsset) {
            throw ValidationException::withMessages(['content' => 'O rascunho contém serviços, profissionais, seções ou imagens inválidos para esta unidade.']);
        }
    }

    private function isUnitBookingAssetPath(Unit $unit, mixed $path): bool
    {
        return is_string($path)
            && Str::startsWith($path, 'online-booking/'.$unit->getKey().'/')
            && Str::endsWith($path, '.webp')
            && ! Str::contains($path, ['..', '\\']);
    }
}
