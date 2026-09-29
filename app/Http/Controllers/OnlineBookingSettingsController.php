<?php

namespace App\Http\Controllers;

use App\Actions\OnlineBooking\EnsureOnlineBookingSite;
use App\Actions\OnlineBooking\PublishOnlineBookingSite;
use App\Actions\OnlineBooking\RestoreOnlineBookingPublication;
use App\Actions\OnlineBooking\SaveOnlineBookingDraft;
use App\Actions\OnlineBooking\UnpublishOnlineBookingSite;
use App\Actions\OnlineBooking\UpdateOnlineBookingSettings;
use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Http\Requests\Settings\OnlineBookingCoverStoreRequest;
use App\Http\Requests\Settings\OnlineBookingDraftRequest;
use App\Http\Requests\Settings\OnlineBookingGalleryReorderRequest;
use App\Http\Requests\Settings\OnlineBookingGalleryStoreRequest;
use App\Http\Requests\Settings\OnlineBookingGalleryUpdateRequest;
use App\Http\Requests\Settings\OnlineBookingLogoStoreRequest;
use App\Http\Requests\Settings\OnlineBookingPublishRequest;
use App\Http\Requests\Settings\OnlineBookingSettingsRequest;
use App\Models\OnlineBookingGalleryImage;
use App\Models\OnlineBookingHandle;
use App\Models\OnlineBookingPublication;
use App\Models\OnlineBookingSetting;
use App\Models\Professional;
use App\Models\Service;
use App\Models\TenantDomain;
use App\Models\Unit;
use App\Support\Images\UploadedImageOptimizer;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OnlineBookingSettingsController extends Controller
{
    public function index(TenantContext $context, EnsureOnlineBookingSite $ensureSite): Response|JsonResponse
    {
        abort_unless($context->unit instanceof Unit, 403);
        Gate::authorize('view', $context->unit);

        $services = Service::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit->getKey())
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'online_booking_enabled', 'lock_version']);
        $professionals = Professional::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit->getKey())
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'status', 'online_booking_enabled', 'lock_version']);
        $readiness = $this->readiness($context->unit, $services, $professionals);
        $setting = $context->unit->onlineBookingSetting;
        $site = $ensureSite->handle($context);
        $publicDomains = $context->tenant->domains()
            ->where('kind', TenantDomainKind::Public->value)
            ->where('status', TenantDomainStatus::Active->value)
            ->orderBy('hostname')
            ->get(['id', 'hostname', 'kind', 'status']);
        $activePublication = $site->activePublication;
        $publicSlug = $activePublication->public_slug ?? ($setting instanceof OnlineBookingSetting ? ($setting->public_slug ?? $context->unit->slug) : $context->unit->slug);
        $publicUrl = $activePublication instanceof OnlineBookingPublication
            ? $this->publicBookingUrl($context->tenant->getKey(), $context->unit, $setting, $publicSlug, $activePublication)
            : null;
        $draftContent = is_array($site->draft?->content) ? $site->draft->content : [];
        $publishedContent = is_array($site->activePublication?->content) ? $site->activePublication->content : [];
        $diffLabels = [
            'identity' => 'Identidade e contato', 'theme' => 'Cores e aparência', 'sections' => 'Seções visíveis',
            'appearance' => 'Personalização visual',
            'service_ids' => 'Serviços', 'professional_ids' => 'Profissionais', 'gallery' => 'Galeria',
            'public_hours' => 'Horários', 'booking_policy' => 'Regras de agendamento', 'seo' => 'SEO',
        ];
        $draftDiff = collect($diffLabels)->filter(fn (string $label, string $key): bool => ($draftContent[$key] ?? null) !== ($publishedContent[$key] ?? null))->values()->all();

        $props = [
            'unit' => $context->unit->only(['id', 'name', 'slug', 'online_booking_enabled', 'lock_version']),
            'settings' => $setting,
            'gallery' => $context->unit->onlineBookingGalleryImages,
            'cover' => $setting?->cover_image_url,
            'logo' => $setting?->logo_image_url,
            'tenant' => ['slug' => $context->tenant->slug],
            'publicUrl' => $publicUrl,
            'previewUrl' => $site->draft ? URL::temporarySignedRoute('online_booking.preview', now()->addMinutes((int) config('online_booking.preview_ttl_minutes', 30)), [$context->tenant, $context->unit]) : null,
            'canonicalUrl' => $activePublication instanceof OnlineBookingPublication ? $publicUrl : null,
            'publicDomains' => $publicDomains,
            'services' => $services,
            'professionals' => $professionals,
            'readiness' => $readiness,
            'publication' => $site->only(['id', 'status', 'draft_revision', 'published_at', 'unpublished_at', 'lock_version']),
            'template_key' => $site->template_key,
            'draft' => $site->draft,
            'activePublication' => $site->activePublication?->only(['id', 'version', 'source_revision', 'published_at', 'template_key']),
            'publicationHistory' => $site->publications()->with('publishedBy:id,name')->latest('version')->limit(10)->get(['id', 'version', 'source_revision', 'template_key', 'published_by', 'published_at', 'superseded_at'])->map(fn (OnlineBookingPublication $publication): array => [
                ...$publication->toArray(),
                'preview_url' => URL::temporarySignedRoute('online_booking.publication_preview', now()->addMinutes((int) config('online_booking.preview_ttl_minutes', 30)), ['publication' => $publication->getKey()]),
            ]),
            'draftDiff' => $draftDiff,
        ];

        return request()->expectsJson()
            ? response()->json($props)
            : Inertia::render('online-booking/index', $props);
    }

    public function saveDraft(OnlineBookingDraftRequest $request, TenantContext $context, SaveOnlineBookingDraft $save): JsonResponse
    {
        $draft = $save->handle($request->user(), $context, $request->validated('content'), (int) $request->validated('revision'));

        return response()->json(['draft' => $draft, 'status' => 'draft_saved']);
    }

    public function publish(OnlineBookingPublishRequest $request, TenantContext $context, PublishOnlineBookingSite $publish): JsonResponse|RedirectResponse
    {
        $publication = $publish->handle($request->user(), $context, (int) $request->validated('revision'));

        if ($request->header('X-Inertia') === 'true') {
            return to_route('online_booking.index')->with('success', 'Página pública publicada.');
        }

        return response()->json(['publication' => $publication, 'status' => 'published']);
    }

    public function unpublish(TenantContext $context, UnpublishOnlineBookingSite $unpublish): JsonResponse|RedirectResponse
    {
        $site = $unpublish->handle(request()->user(), $context);

        if (request()->header('X-Inertia') === 'true') {
            return to_route('online_booking.index')->with('success', 'Página pública retirada do ar.');
        }

        return response()->json(['publication' => $site, 'status' => 'unpublished']);
    }

    public function restore(string $publication, TenantContext $context, RestoreOnlineBookingPublication $restore): JsonResponse|RedirectResponse
    {
        $record = OnlineBookingPublication::query()->findOrFail($publication);
        $draft = $restore->handle(request()->user(), $context, $record);

        if (request()->header('X-Inertia') === 'true') {
            return to_route('online_booking.index')->with('success', 'Publicação restaurada como rascunho.');
        }

        return response()->json(['draft' => $draft, 'status' => 'draft_restored']);
    }

    private function publicBookingUrl(string $tenantId, Unit $unit, ?OnlineBookingSetting $setting, string $publicSlug, ?OnlineBookingPublication $publication = null): string
    {
        $publicDomainId = $publication instanceof OnlineBookingPublication
            ? $publication->public_domain_id
            : $setting?->public_domain_id;
        $domain = $publicDomainId === null ? null : TenantDomain::query()
            ->whereKey($publicDomainId)
            ->where('tenant_id', $tenantId)
            ->where('kind', TenantDomainKind::Public->value)
            ->where('status', TenantDomainStatus::Active->value)
            ->first();
        if ($domain instanceof TenantDomain && $domain->tenant_id === $tenantId && $domain->kind === TenantDomainKind::Public && $domain->status === TenantDomainStatus::Active) {
            return 'https://'.$domain->hostname.'/';
        }

        $handle = $this->currentHandle($tenantId, $unit->getKey()) ?? $publicSlug;

        return $this->sharedBookingUrl($handle);
    }

    private function currentHandle(string $tenantId, string $unitId): ?string
    {
        return OnlineBookingHandle::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'current')
            ->value('handle');
    }

    private function sharedBookingUrl(string $handle): string
    {
        return rtrim((string) request()->getScheme().'://'.config('domains.shared_booking_host'), '/').'/'.rawurlencode($handle);
    }

    public function storeCover(OnlineBookingCoverStoreRequest $request, TenantContext $context): JsonResponse
    {
        $setting = $context->unit->onlineBookingSetting;

        abort_unless($setting instanceof OnlineBookingSetting, 422, 'Salve as configurações do agendamento antes de enviar a capa.');

        $image = $request->file('image');
        $path = 'online-booking/'.$context->unit->getKey().'/cover/'.Str::random(40).'.webp';
        app(UploadedImageOptimizer::class)->storeWebp($image, Storage::disk((string) config('filesystems.media_disk')), $path);
        $setting->forceFill(['cover_image_path' => $path])->save();

        return response()->json(['cover' => $setting->fresh()->cover_image_url]);
    }

    public function destroyCover(TenantContext $context): JsonResponse
    {
        Gate::authorize('update', $context->unit);
        $setting = $context->unit->onlineBookingSetting;

        if (! $setting instanceof OnlineBookingSetting || $setting->cover_image_path === null) {
            return response()->json(['cover' => null]);
        }

        $setting->forceFill(['cover_image_path' => null])->save();

        return response()->json(['cover' => null]);
    }

    public function storeLogo(OnlineBookingLogoStoreRequest $request, TenantContext $context): JsonResponse
    {
        $setting = $context->unit->onlineBookingSetting;

        abort_unless($setting instanceof OnlineBookingSetting, 422, 'Salve as configurações do agendamento antes de enviar o logo.');

        $disk = Storage::disk((string) config('filesystems.media_disk'));
        $path = 'online-booking/'.$context->unit->getKey().'/logo/'.Str::random(40).'.webp';
        app(UploadedImageOptimizer::class)->storeWebp($request->file('image'), $disk, $path);
        $setting->forceFill(['logo_image_path' => $path])->save();

        return response()->json(['logo' => $setting->fresh()->logo_image_url]);
    }

    public function destroyLogo(TenantContext $context): JsonResponse
    {
        Gate::authorize('update', $context->unit);
        $setting = $context->unit->onlineBookingSetting;

        if (! $setting instanceof OnlineBookingSetting || $setting->logo_image_path === null) {
            return response()->json(['logo' => null]);
        }

        $setting->forceFill(['logo_image_path' => null])->save();

        return response()->json(['logo' => null]);
    }

    public function update(OnlineBookingSettingsRequest $request, TenantContext $context, UpdateOnlineBookingSettings $update, EnsureOnlineBookingSite $ensureSite, SaveOnlineBookingDraft $saveDraft, OperationalMutation $mutation): RedirectResponse
    {
        $data = $request->validated();
        try {
            $mutation->execute($request, $context, $request->user(), $data, function () use ($update, $ensureSite, $saveDraft, $request, $context, $data): array {
                $unit = $update->handle($request->user(), $context, $data);
                $site = $ensureSite->handle($context);
                $content = $ensureSite->content($context, $site->template_key);
                $existingSections = $site->draft?->content['sections'] ?? null;
                if (is_array($existingSections)) {
                    $content['sections'] = $existingSections;
                }
                $existingAppearance = $site->draft?->content['appearance'] ?? null;
                if (is_array($existingAppearance)) {
                    $content['appearance'] = $existingAppearance;
                }
                $saveDraft->handle($request->user(), $context, $content, (int) $site->draft_revision);

                return ['resource_id' => $unit->getKey(), 'resource_type' => 'unit'];
            });
        } catch (ConflictHttpException $exception) {
            if ($request->header('X-Inertia') === 'true') {
                return to_route('online_booking.index')->with('error', 'Esta tela estava desatualizada. Recarregamos as configurações atuais; revise e salve novamente.');
            }

            throw $exception;
        }

        return to_route('online_booking.index')->with('success', 'Agendamento online atualizado.');
    }

    public function storeGallery(OnlineBookingGalleryStoreRequest $request, TenantContext $context): JsonResponse
    {
        $image = $request->file('image');
        $path = 'online-booking/'.$context->unit->getKey().'/'.Str::random(40).'.webp';
        $thumbnailPath = 'online-booking/'.$context->unit->getKey().'/thumbs/'.Str::random(40).'.webp';
        $disk = Storage::disk((string) config('filesystems.media_disk'));
        $optimizer = app(UploadedImageOptimizer::class);
        $optimizer->storeWebp($image, $disk, $path);
        $optimizer->storeSquareWebp($image, $disk, $thumbnailPath);
        $gallery = $context->unit->onlineBookingGalleryImages()->create([
            'id' => (string) Str::uuid7(), 'tenant_id' => $context->tenant->getKey(), 'path' => $path, 'thumbnail_path' => $thumbnailPath,
            'alt_text' => $request->validated('alt_text'), 'position' => (int) $context->unit->onlineBookingGalleryImages()->max('position') + 1,
        ]);

        return response()->json(['gallery' => $gallery], 201);
    }

    public function updateGallery(OnlineBookingGalleryUpdateRequest $request, TenantContext $context, string $image): JsonResponse
    {
        $gallery = $this->galleryImage($context, $image);
        $gallery->update($request->validated());

        return response()->json(['gallery' => $gallery->fresh()]);
    }

    public function destroyGallery(TenantContext $context, string $image): JsonResponse
    {
        Gate::authorize('update', $context->unit);
        $gallery = $this->galleryImage($context, $image);
        $gallery->delete();

        return response()->json(['deleted' => true]);
    }

    public function reorderGallery(OnlineBookingGalleryReorderRequest $request, TenantContext $context): JsonResponse
    {
        $ids = $request->validated('image_ids');
        $images = $context->unit->onlineBookingGalleryImages()->whereIn('id', $ids)->get()->keyBy('id');
        abort_unless($images->count() === count($ids), 422, 'A galeria contém imagens inválidas.');
        DB::transaction(function () use ($ids, $images): void {
            foreach ($ids as $position => $id) {
                $images[$id]->update(['position' => $position]);
            }
        });

        return response()->json(['gallery' => $context->unit->onlineBookingGalleryImages()->get()]);
    }

    private function galleryImage(TenantContext $context, string $image): OnlineBookingGalleryImage
    {
        return $context->unit->onlineBookingGalleryImages()->whereKey($image)->firstOrFail();
    }

    /**
     * @param  Collection<int, Service>  $services
     * @param  Collection<int, Professional>  $professionals
     * @return array{unit_enabled: bool, has_active_service: bool, has_active_professional: bool, has_service_professional_pair: bool, has_whatsapp: bool, publishable: bool}
     */
    private function readiness(Unit $unit, Collection $services, Collection $professionals): array
    {
        $hasActiveService = $services->contains(fn (Service $service): bool => $service->status === 'active' && $service->online_booking_enabled);
        $hasActiveProfessional = $professionals->contains(fn (Professional $professional): bool => $professional->status === 'active' && $professional->online_booking_enabled);
        $hasPair = $services->contains(fn (Service $service): bool => $service->status === 'active' && $service->online_booking_enabled && $service->professionals()->where('professionals.unit_id', $unit->getKey())->where('professionals.status', 'active')->where('professionals.online_booking_enabled', true)->exists());
        $hasWhatsapp = filled($unit->onlineBookingSetting?->whatsapp_phone) || $professionals->contains(fn (Professional $professional): bool => filled($professional->phone));

        return [
            'unit_enabled' => $unit->online_booking_enabled,
            'has_active_service' => $hasActiveService,
            'has_active_professional' => $hasActiveProfessional,
            'has_service_professional_pair' => $hasPair,
            'has_whatsapp' => $hasWhatsapp,
            'publishable' => $unit->online_booking_enabled && $hasPair && $hasWhatsapp,
        ];
    }
}
