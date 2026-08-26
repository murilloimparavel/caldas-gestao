<?php

namespace App\Http\Controllers;

use App\Actions\PublicBooking\CreatePublicAppointment;
use App\Http\Requests\PublicBookingAppointmentRequest;
use App\Http\Requests\PublicBookingAvailabilityRequest;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\CalendarAvailability;
use App\Support\CalendarConflictException;
use App\Support\IdempotencyService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
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
                'slug' => $unit->slug,
                'name' => $unit->name,
                'timezone' => $unit->timezone ?? $tenant->timezone,
                'address' => $this->safeAddress($unit->address),
            ],
            ...$catalog,
        ];

        return request()->expectsJson()
            ? response()->json($payload)
            : Inertia::render('public-booking/show', $payload);
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
        ], $result->replayed ? 200 : 201);
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
            ->get(['id', 'name', 'description', 'duration_minutes', 'price_cents'])
            ->map(fn (Service $service): array => [
                'id' => $service->getKey(), 'name' => $service->name, 'description' => $service->description,
                'duration_minutes' => $service->duration_minutes, 'price_cents' => $service->price_cents,
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
