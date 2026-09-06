<?php

namespace App\Http\Controllers;

use App\Actions\PublicBooking\CreatePublicAppointment;
use App\Http\Requests\PublicBookingAppointmentRequest;
use App\Http\Requests\PublicBookingAvailabilityRequest;
use App\Models\Appointment;
use App\Models\OnlineBookingSetting;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\CalendarAvailability;
use App\Support\CalendarConflictException;
use App\Support\IdempotencyService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
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
        $this->assertPublicBookingEnabled($tenant, $unit);
        $catalog = $this->catalog($tenant, $unit);
        $payload = [
            'unit' => [
                'tenant_slug' => $tenant->slug,
                'slug' => $unit->slug,
                'name' => $unit->name,
                'timezone' => $unit->timezone ?? $tenant->timezone,
                'address' => $this->safeAddress($unit->address),
                'description' => $unit->onlineBookingSetting?->description,
                'cover_image_url' => $unit->onlineBookingSetting?->cover_image_url,
                'brand_color' => $unit->onlineBookingSetting?->brand_color,
                'booking_flow' => $unit->onlineBookingSetting instanceof OnlineBookingSetting ? ($unit->onlineBookingSetting->booking_flow ?? 'service_first') : 'service_first',
                'public_hours' => $unit->onlineBookingSetting?->public_hours,
                'minimum_notice_minutes' => $unit->onlineBookingSetting instanceof OnlineBookingSetting ? ($unit->onlineBookingSetting->minimum_notice_minutes ?? 0) : 0,
                'contacts' => ['whatsapp' => $unit->onlineBookingSetting?->whatsapp_phone, 'phone' => $unit->onlineBookingSetting?->phone, 'instagram_url' => $unit->onlineBookingSetting?->instagram_url, 'facebook_url' => $unit->onlineBookingSetting?->facebook_url, 'website_url' => $unit->onlineBookingSetting?->website_url],
                'gallery' => $unit->onlineBookingGalleryImages->map(fn ($image): array => ['url' => Storage::disk('public')->url($image->path), 'alt_text' => $image->alt_text])->values()->all(),
            ],
            ...$catalog,
        ];

        return request()->expectsJson()
            ? response()->json($payload)
            : Inertia::render('public-booking/show', $payload);
    }

    public function showBySlug(string $publicSlug): Response|JsonResponse
    {
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
    private function catalog(Tenant $tenant, Unit $unit): array
    {
        $services = Service::query()
            ->whereBelongsTo($tenant)
            ->whereBelongsTo($unit)
            ->where('status', 'active')
            ->where('online_booking_enabled', true)
            ->whereHas('professionals', fn ($query) => $query->where('professionals.tenant_id', $tenant->getKey())->where('professionals.unit_id', $unit->getKey())->where('professionals.status', 'active')->where('professionals.online_booking_enabled', true))
            ->with(['professionals' => fn ($query) => $query->select('professionals.id', 'professionals.name', 'professionals.avatar_path')->where('professionals.tenant_id', $tenant->getKey())->where('professionals.unit_id', $unit->getKey())->where('professionals.status', 'active')->where('professionals.online_booking_enabled', true)->orderBy('professionals.name')])
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'duration_minutes', 'price_cents', 'image_path'])
            ->map(fn (Service $service): array => [
                'id' => $service->getKey(), 'name' => $service->name, 'description' => $service->description,
                'duration_minutes' => $service->duration_minutes, 'price_cents' => $service->price_cents,
                'image_url' => $service->image_url,
                'professionals' => $service->professionals->map(fn (Professional $professional): array => ['id' => $professional->getKey(), 'name' => $professional->name, 'avatar_url' => $professional->avatar_url])->values()->all(),
            ])->values()->all();
        $professionals = Professional::query()
            ->whereBelongsTo($tenant)->whereBelongsTo($unit)->where('status', 'active')->where('online_booking_enabled', true)
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
