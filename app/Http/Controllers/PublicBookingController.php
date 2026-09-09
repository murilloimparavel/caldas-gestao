<?php

namespace App\Http\Controllers;

use App\Actions\PublicBooking\CreatePublicAppointment;
use App\Http\Requests\PublicBookingAppointmentRequest;
use App\Http\Requests\PublicBookingAvailabilityRequest;
use App\Jobs\RecordOnlineBookingVisit;
use App\Models\Appointment;
use App\Models\OnlineBookingCampaignLink;
use App\Models\OnlineBookingPublication;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\CalendarAvailability;
use App\Support\CalendarConflictException;
use App\Support\IdempotencyService;
use App\Support\Images\MediaUrl;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicBookingController extends Controller
{
    public function __construct(
        private readonly CalendarAvailability $availability,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function show(Tenant $tenant, Unit $unit): Response|JsonResponse
    {
        $preview = request()->attributes->get('online_booking_preview_content');
        if (! is_array($preview)) {
            $this->assertPublicBookingEnabled($tenant, $unit);
        }
        $setting = $unit->onlineBookingSetting;
        $publication = is_array($preview) || ! config('online_booking.use_publication_resolver', true) ? null : OnlineBookingSite::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->whereNotNull('active_publication_id')
            ->with('activePublication')
            ->first()?->activePublication;
        $content = is_array($preview) ? $preview : (is_array($publication?->content) ? $publication->content : []);
        $sections = collect(is_array($content['sections'] ?? null) ? $content['sections'] : [])
            ->filter(fn (mixed $section): bool => is_array($section) && isset($section['key']))
            ->mapWithKeys(fn (array $section): array => [(string) $section['key'] => (bool) ($section['enabled'] ?? true)])
            ->all();
        $sections += array_fill_keys(['hero', 'services', 'professionals', 'gallery', 'hours', 'contact'], true);
        if (! is_array($preview)) {
            $campaign = $this->campaignFromRequest($tenant, $unit);
            RecordOnlineBookingVisit::dispatch([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'publication_id' => $publication?->getKey(),
                'campaign_link_id' => $campaign?->getKey(),
                'visitor_hash' => hash('sha256', request()->session()->getId().'|'.request()->userAgent()),
                'landing_path' => request()->path(),
                'referer_host' => parse_url((string) request()->header('referer'), PHP_URL_HOST),
                'utm_source' => $this->utm('utm_source'),
                'utm_medium' => $this->utm('utm_medium'),
                'utm_campaign' => $this->utm('utm_campaign'),
                'utm_term' => $this->utm('utm_term'),
                'utm_content' => $this->utm('utm_content'),
                'consent' => false,
                'occurred_at' => now(),
            ]);
        }
        $catalog = $this->catalog($tenant, $unit, $publication, $content, is_array($preview));
        $identity = is_array($content['identity'] ?? null) ? $content['identity'] : [];
        $theme = is_array($content['theme'] ?? null) ? $content['theme'] : [];
        $policy = is_array($content['booking_policy'] ?? null) ? $content['booking_policy'] : [];
        $description = array_key_exists('description', $identity) ? $identity['description'] : $setting?->description;
        $brandColor = $theme['brand_color'] ?? $setting?->brand_color;
        $publicHours = array_key_exists('public_hours', $content) ? $content['public_hours'] : $setting?->public_hours;
        $gallery = ($publication !== null || is_array($preview)) && is_array($content['gallery'] ?? null)
            ? collect($content['gallery'])->map(fn (array $image): array => ['url' => MediaUrl::for((string) ($image['path'] ?? '')), 'thumbnail_url' => MediaUrl::for($image['thumbnail_path'] ?? ($image['path'] ?? null)), 'alt_text' => $image['alt_text'] ?? null])->values()->all()
            : $unit->onlineBookingGalleryImages->map(fn ($image): array => ['url' => MediaUrl::for($image->path), 'thumbnail_url' => MediaUrl::for($image->thumbnail_path ?? $image->path), 'alt_text' => $image->alt_text])->values()->all();
        $coverImagePath = $identity['cover_image_path'] ?? $setting?->cover_image_path;
        $payload = [
            'unit' => [
                'tenant_slug' => $tenant->slug,
                'slug' => $unit->slug,
                'name' => $unit->name,
                'timezone' => $unit->timezone ?? $tenant->timezone,
                'address' => $this->safeAddress($unit->address),
                'description' => $description,
                'seo' => is_array($content['seo'] ?? null) ? $content['seo'] : ['title' => $unit->name, 'description' => $description],
                'canonical_url' => url('/book/'.rawurlencode((string) ($publication?->public_slug ?? $unit->slug))),
                'is_preview' => is_array($preview),
                'cover_image_url' => $coverImagePath === null ? null : MediaUrl::for((string) $coverImagePath),
                'brand_color' => $brandColor,
                'booking_flow' => $policy['booking_flow'] ?? ($setting instanceof OnlineBookingSetting ? ($setting->booking_flow ?? 'service_first') : 'service_first'),
                'public_hours' => $publicHours,
                'minimum_notice_minutes' => $policy['minimum_notice_minutes'] ?? ($setting instanceof OnlineBookingSetting ? ($setting->minimum_notice_minutes ?? 0) : 0),
                'contacts' => ['whatsapp' => $identity['whatsapp_phone'] ?? $setting?->whatsapp_phone, 'phone' => $identity['phone'] ?? $setting?->phone, 'instagram_url' => $identity['instagram_url'] ?? $setting?->instagram_url, 'facebook_url' => $identity['facebook_url'] ?? $setting?->facebook_url, 'website_url' => $identity['website_url'] ?? $setting?->website_url],
                'gallery' => $gallery,
                'sections' => $sections,
            ],
            ...$catalog,
        ];

        if (! request()->expectsJson()) {
            return Inertia::render('public-booking/show', $payload);
        }

        $response = response()->json($payload);
        if ($publication !== null) {
            $etag = '"'.$publication->content_hash.'"';
            $response->setEtag($publication->content_hash);
            $response->setLastModified($publication->published_at);
            $response->setPublic();
            $response->setMaxAge(60);
            if (request()->getEtags() !== [] && in_array($etag, request()->getEtags(), true)) {
                $response->setNotModified();
            }
        }

        return $response;
    }

    public function preview(Tenant $tenant, Unit $unit): Response|JsonResponse
    {
        Gate::authorize('view', $unit);
        $draft = OnlineBookingSite::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->with('draft')
            ->firstOrFail()
            ->draft;

        abort_unless($draft !== null, 404);
        request()->attributes->set('online_booking_preview_content', $draft->content);

        return $this->show($tenant, $unit);
    }

    public function showBySlug(string $publicSlug): Response|JsonResponse
    {
        $site = OnlineBookingSite::query()->where('public_slug', $publicSlug)->with(['tenant', 'unit'])->first();
        if ($site instanceof OnlineBookingSite) {
            return $this->show($site->tenant, $site->unit);
        }

        $setting = OnlineBookingSetting::query()->where('public_slug', $publicSlug)->with(['tenant', 'unit'])->firstOrFail();

        return $this->show($setting->tenant, $setting->unit);
    }

    public function availability(PublicBookingAvailabilityRequest $request, Tenant $tenant, Unit $unit): JsonResponse
    {
        $this->assertPublicBookingEnabled($tenant, $unit);
        $data = $request->validated();
        $service = $this->publicService($tenant, $unit, $data['service_id']);
        $professional = $this->publicProfessional($tenant, $unit, $data['professional_id']);

        if (! $professional->services()->whereKey($service->getKey())->exists()) {
            throw new NotFoundHttpException;
        }

        $timezone = (string) ($unit->timezone ?? $tenant->timezone ?? config('app.timezone'));
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['date'], $timezone);
        $from = CarbonImmutable::parse($date->toDateString().' '.($data['from'] ?? '00:00'), $timezone);
        $to = CarbonImmutable::parse($date->toDateString().' '.($data['to'] ?? '23:59'), $timezone);
        $setting = $unit->onlineBookingSetting;
        $minimumStart = CarbonImmutable::now($timezone)->addMinutes((int) ($setting instanceof OnlineBookingSetting ? ($setting->minimum_notice_minutes ?? 0) : 0));
        if ($from->lt($minimumStart)) {
            $from = $minimumStart->second(0);
        }
        $publicWindow = $this->publicWindow($setting instanceof OnlineBookingSetting ? (array) ($setting->public_hours ?? []) : [], $date->dayOfWeek);
        if ($publicWindow === null) {
            return response()->json(['date' => $date->toDateString(), 'timezone' => $timezone, 'slots' => []]);
        }
        $from = $from->max(CarbonImmutable::parse($date->toDateString().' '.$publicWindow['starts_at'], $timezone));
        $to = $to->min(CarbonImmutable::parse($date->toDateString().' '.$publicWindow['ends_at'], $timezone));
        $slots = [];

        for ($cursor = $from; $cursor->addMinutes((int) $service->duration_minutes)->lte($to); $cursor = $cursor->addMinutes(15)) {
            try {
                $this->availability->assertAvailable(
                    (string) $tenant->getKey(),
                    (string) $unit->getKey(),
                    (string) $professional->getKey(),
                    $cursor,
                    $cursor->addMinutes((int) $service->duration_minutes),
                );
                $slots[] = [
                    'starts_at' => $cursor->toIso8601String(),
                    'ends_at' => $cursor->addMinutes((int) $service->duration_minutes)->toIso8601String(),
                ];
            } catch (CalendarConflictException) {
                continue;
            }
        }

        return response()->json(['date' => $date->toDateString(), 'timezone' => $timezone, 'slots' => $slots]);
    }

    public function store(PublicBookingAppointmentRequest $request, Tenant $tenant, Unit $unit, CreatePublicAppointment $create): JsonResponse
    {
        $this->assertPublicBookingEnabled($tenant, $unit);
        $key = trim((string) $request->header('X-Idempotency-Key'));

        if ($key === '' || Str::length($key) > 200) {
            return response()->json(['message' => 'A valid idempotency key is required.'], 422);
        }

        $data = $request->validated();
        $campaign = OnlineBookingCampaignLink::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->where('is_active', true)
            ->where('utm_source', $request->query('utm_source'))
            ->where('utm_medium', $request->query('utm_medium'))
            ->where('utm_campaign', $request->query('utm_campaign'))
            ->when($request->query('utm_term') !== null, fn ($query) => $query->where('utm_term', $request->query('utm_term')))
            ->when($request->query('utm_content') !== null, fn ($query) => $query->where('utm_content', $request->query('utm_content')))
            ->first();
        $data['online_booking_campaign_link_id'] = $campaign?->getKey();
        $result = $this->idempotency->execute(
            $tenant,
            null,
            $key,
            [...$data, 'unit_id' => $unit->getKey()],
            fn (): array => [
                'resource_id' => $create->handle($tenant, $unit, $data)->getKey(),
                'resource_type' => 'appointment',
                'status' => 'scheduled',
            ],
        );
        $appointmentId = $result->key->resource_id ?? ($result->value['resource_id'] ?? null);

        return response()->json([
            'appointment' => ['id' => $appointmentId, 'status' => 'scheduled'],
            'replayed' => $result->replayed,
            'whatsapp_url' => $this->whatsappUrl($appointmentId, $unit),
        ], $result->replayed ? 200 : 201);
    }

    private function whatsappUrl(string $appointmentId, Unit $unit): ?string
    {
        $appointment = Appointment::query()->with(['professional', 'items'])->find($appointmentId);
        $phone = $this->normalizePhone($appointment?->professional?->phone ?: $unit->onlineBookingSetting?->whatsapp_phone);

        return $phone === '' ? null : 'https://wa.me/'.$phone.'?text='.rawurlencode('Olá! Solicitei um agendamento para '.$appointment->items->first()?->service_name_snapshot.'.');
    }

    private function normalizePhone(?string $phone): string
    {
        return (string) preg_replace('/\D+/', '', (string) $phone);
    }

    private function utm(string $key): ?string
    {
        $value = request()->query($key);

        return is_string($value) && preg_match('/^[a-zA-Z0-9_-]{1,150}$/', $value) === 1 ? $value : null;
    }

    private function campaignFromRequest(Tenant $tenant, Unit $unit): ?OnlineBookingCampaignLink
    {
        $source = $this->utm('utm_source');
        $medium = $this->utm('utm_medium');
        $campaign = $this->utm('utm_campaign');
        if ($source === null || $medium === null || $campaign === null) {
            return null;
        }

        return OnlineBookingCampaignLink::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->where('is_active', true)
            ->where('utm_source', $source)
            ->where('utm_medium', $medium)
            ->where('utm_campaign', $campaign)
            ->where(function ($query): void {
                $query->whereNull('utm_term')->orWhere('utm_term', $this->utm('utm_term'));
            })
            ->where(function ($query): void {
                $query->whereNull('utm_content')->orWhere('utm_content', $this->utm('utm_content'));
            })
            ->first();
    }

    /**
     * @param  array<int|string, mixed>  $hours
     * @return array{starts_at: string, ends_at: string}|null
     */
    private function publicWindow(array $hours, int $weekday): ?array
    {
        $window = $hours[(string) $weekday] ?? $hours[$weekday] ?? null;
        if (! is_array($window) || ($window['enabled'] ?? true) === false) {
            return null;
        }
        if (! isset($window['starts_at'], $window['ends_at'])) {
            return null;
        }

        return ['starts_at' => (string) $window['starts_at'], 'ends_at' => (string) $window['ends_at']];
    }

    /** @return array{services: array<int, array<string, mixed>>, professionals: array<int, array<string, mixed>>} */
    private function catalog(Tenant $tenant, Unit $unit, ?OnlineBookingPublication $publication = null, array $content = [], bool $preview = false): array
    {
        $serviceIds = array_values(array_filter($content['service_ids'] ?? [], 'is_string'));
        $professionalIds = array_values(array_filter($content['professional_ids'] ?? [], 'is_string'));
        /** @var list<array<string, mixed>> $services */
        $services = Service::query()
            ->whereBelongsTo($tenant)
            ->whereBelongsTo($unit)
            ->where('status', 'active')
            ->where('online_booking_enabled', true)
            ->when($publication !== null || $preview, fn ($query) => $query->whereIn('services.id', $serviceIds))
            ->whereHas('professionals', fn ($query) => $query->where('professionals.tenant_id', $tenant->getKey())->where('professionals.unit_id', $unit->getKey())->where('professionals.status', 'active')->where('professionals.online_booking_enabled', true))
            ->with(['professionals' => fn ($query) => $query->select('professionals.id', 'professionals.name', 'professionals.avatar_path')->where('professionals.tenant_id', $tenant->getKey())->where('professionals.unit_id', $unit->getKey())->where('professionals.status', 'active')->where('professionals.online_booking_enabled', true)->orderBy('professionals.name')])
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'duration_minutes', 'price_cents', 'image_path'])
            ->map(fn (Service $service): array => [
                'id' => $service->getKey(), 'name' => $service->name, 'description' => $service->description,
                'duration_minutes' => $service->duration_minutes, 'price_cents' => $service->price_cents,
                'image_url' => $service->image_url,
                'thumbnail_url' => $service->thumbnail_url,
                'professionals' => $service->professionals->map(fn (Professional $professional): array => ['id' => $professional->getKey(), 'name' => $professional->name, 'avatar_url' => $professional->avatar_url])->values()->all(),
            ])->values()->all();
        /** @var list<array<string, mixed>> $professionals */
        $professionals = Professional::query()
            ->whereBelongsTo($tenant)->whereBelongsTo($unit)->where('status', 'active')->where('online_booking_enabled', true)
            ->when($publication !== null || $preview, fn ($query) => $query->whereIn('professionals.id', $professionalIds))
            ->whereHas('services', fn ($query) => $query->where('services.tenant_id', $tenant->getKey())->where('services.unit_id', $unit->getKey())->where('services.status', 'active')->where('services.online_booking_enabled', true))
            ->orderBy('name')->get(['id', 'name', 'avatar_path'])
            ->map(fn (Professional $professional): array => ['id' => $professional->getKey(), 'name' => $professional->name, 'avatar_url' => $professional->avatar_url])->values()->all();

        return compact('services', 'professionals');
    }

    private function publicService(Tenant $tenant, Unit $unit, string $id): Service
    {
        return Service::query()->whereKey($id)->whereBelongsTo($tenant)->whereBelongsTo($unit)->where('status', 'active')->where('online_booking_enabled', true)->firstOrFail();
    }

    private function publicProfessional(Tenant $tenant, Unit $unit, string $id): Professional
    {
        return Professional::query()->whereKey($id)->whereBelongsTo($tenant)->whereBelongsTo($unit)->where('status', 'active')->where('online_booking_enabled', true)->firstOrFail();
    }

    private function assertPublicBookingEnabled(Tenant $tenant, Unit $unit): void
    {
        if ($tenant->status->value !== 'active' || $unit->tenant_id !== $tenant->getKey() || $unit->status->value !== 'active' || ! $unit->online_booking_enabled) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * @param  array<string, mixed>|string|null  $address
     * @return array<string, mixed>|null
     */
    private function safeAddress(array|string|null $address): ?array
    {
        if ($address === null) {
            return null;
        }
        $addrArray = is_array($address) ? $address : json_decode($address, true);

        return is_array($addrArray) ? collect($addrArray)->only(['street', 'number', 'neighborhood', 'city', 'state', 'postal_code'])->all() : null;
    }
}
