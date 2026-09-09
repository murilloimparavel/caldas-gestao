<?php

namespace App\Http\Controllers;

use App\Actions\OnlineBooking\UpdateOnlineBookingSettings;
use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use App\Http\Requests\Settings\OnlineBookingCoverStoreRequest;
use App\Http\Requests\Settings\OnlineBookingGalleryReorderRequest;
use App\Http\Requests\Settings\OnlineBookingGalleryStoreRequest;
use App\Http\Requests\Settings\OnlineBookingGalleryUpdateRequest;
use App\Http\Requests\Settings\OnlineBookingSettingsRequest;
use App\Models\OnlineBookingGalleryImage;
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
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class OnlineBookingSettingsController extends Controller
{
    public function index(TenantContext $context): Response|JsonResponse
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
        $publicDomains = $context->tenant->domains()
            ->where('kind', TenantDomainKind::Public->value)
            ->where('status', TenantDomainStatus::Active->value)
            ->orderBy('hostname')
            ->get(['id', 'hostname', 'kind', 'status']);
        $publicSlug = $setting instanceof OnlineBookingSetting ? ($setting->public_slug ?? $context->unit->slug) : $context->unit->slug;

        $props = [
            'unit' => $context->unit->only(['id', 'name', 'slug', 'online_booking_enabled', 'lock_version']),
            'settings' => $setting,
            'gallery' => $context->unit->onlineBookingGalleryImages,
            'cover' => $setting?->cover_image_url,
            'tenant' => ['slug' => $context->tenant->slug],
            'publicUrl' => $readiness['publishable'] ? $this->publicBookingUrl($context->tenant->getKey(), $context->unit, $setting, $publicSlug) : null,
            'canonicalUrl' => $readiness['publishable'] ? route('public_booking.slug', ['public_slug' => $publicSlug]) : null,
            'publicDomains' => $publicDomains,
            'services' => $services,
            'professionals' => $professionals,
            'readiness' => $readiness,
        ];

        return request()->expectsJson()
            ? response()->json($props)
            : Inertia::render('online-booking/index', $props);
    }

    private function publicBookingUrl(string $tenantId, Unit $unit, ?OnlineBookingSetting $setting, string $publicSlug): string
    {
        $domain = $setting?->publicDomain;
        if ($domain instanceof TenantDomain && $domain->tenant_id === $tenantId && $domain->kind === TenantDomainKind::Public && $domain->status === TenantDomainStatus::Active) {
            return 'https://'.$domain->hostname.'/book/'.rawurlencode($publicSlug);
        }

        return route('public_booking.show', [$unit->tenant, $unit]);
    }

    public function storeCover(OnlineBookingCoverStoreRequest $request, TenantContext $context): JsonResponse
    {
        $setting = $context->unit->onlineBookingSetting;

        abort_unless($setting instanceof OnlineBookingSetting, 422, 'Salve as configurações do agendamento antes de enviar a capa.');

        $image = $request->file('image');
        $path = 'online-booking/'.$context->unit->getKey().'/cover/'.Str::random(40).'.webp';
        app(UploadedImageOptimizer::class)->storeWebp($image, Storage::disk((string) config('filesystems.media_disk')), $path);
        $oldPath = $setting->cover_image_path;
        $setting->forceFill(['cover_image_path' => $path])->save();

        if ($oldPath !== null) {
            Storage::disk(config('filesystems.media_disk'))->delete($oldPath);
        }

        return response()->json(['cover' => $setting->fresh()->cover_image_url]);
    }

    public function destroyCover(TenantContext $context): JsonResponse
    {
        Gate::authorize('update', $context->unit);
        $setting = $context->unit->onlineBookingSetting;

        if (! $setting instanceof OnlineBookingSetting || $setting->cover_image_path === null) {
            return response()->json(['cover' => null]);
        }

        Storage::disk(config('filesystems.media_disk'))->delete($setting->cover_image_path);
        $setting->forceFill(['cover_image_path' => null])->save();

        return response()->json(['cover' => null]);
    }

    public function update(OnlineBookingSettingsRequest $request, TenantContext $context, UpdateOnlineBookingSettings $update, OperationalMutation $mutation): RedirectResponse
    {
        $data = $request->validated();
        try {
            $mutation->execute($request, $context, $request->user(), $data, function () use ($update, $request, $context, $data): array {
                $unit = $update->handle($request->user(), $context, $data);

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
        app(UploadedImageOptimizer::class)->storeWebp($image, Storage::disk((string) config('filesystems.media_disk')), $path);
        $gallery = $context->unit->onlineBookingGalleryImages()->create([
            'id' => (string) Str::uuid7(), 'tenant_id' => $context->tenant->getKey(), 'path' => $path,
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
        Storage::disk(config('filesystems.media_disk'))->delete($gallery->path);
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
