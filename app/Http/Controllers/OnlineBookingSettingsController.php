<?php

namespace App\Http\Controllers;

use App\Actions\OnlineBooking\UpdateOnlineBookingSettings;
use App\Http\Requests\Settings\OnlineBookingSettingsRequest;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

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
            ->get(['id', 'name', 'status', 'online_booking_enabled', 'lock_version']);
        $readiness = $this->readiness($context->unit, $services, $professionals);

        $props = [
            'unit' => $context->unit->only(['id', 'name', 'slug', 'online_booking_enabled', 'lock_version']),
            'tenant' => ['slug' => $context->tenant->slug],
            'publicUrl' => $readiness['publishable'] ? route('public_booking.show', [$context->tenant, $context->unit]) : null,
            'services' => $services,
            'professionals' => $professionals,
            'readiness' => $readiness,
        ];

        return request()->expectsJson()
            ? response()->json($props)
            : Inertia::render('online-booking/index', $props);
    }

    public function update(OnlineBookingSettingsRequest $request, TenantContext $context, UpdateOnlineBookingSettings $update, OperationalMutation $mutation): RedirectResponse
    {
        $data = $request->validated();
        $mutation->execute($request, $context, $request->user(), $data, function () use ($update, $request, $context, $data): array {
            $unit = $update->handle($request->user(), $context, $data);

            return ['resource_id' => $unit->getKey(), 'resource_type' => 'unit'];
        });

        return to_route('online_booking.index')->with('success', 'Agendamento online atualizado.');
    }

    /**
     * @param  Collection<int, Service>  $services
     * @param  Collection<int, Professional>  $professionals
     * @return array{unit_enabled: bool, has_active_service: bool, has_active_professional: bool, has_service_professional_pair: bool, publishable: bool}
     */
    private function readiness(Unit $unit, Collection $services, Collection $professionals): array
    {
        $hasActiveService = $services->contains(fn (Service $service): bool => $service->status === 'active' && $service->online_booking_enabled);
        $hasActiveProfessional = $professionals->contains(fn (Professional $professional): bool => $professional->status === 'active' && $professional->online_booking_enabled);
        $hasPair = $services->contains(fn (Service $service): bool => $service->status === 'active' && $service->online_booking_enabled && $service->professionals()->where('professionals.unit_id', $unit->getKey())->where('professionals.status', 'active')->where('professionals.online_booking_enabled', true)->exists());

        return [
            'unit_enabled' => $unit->online_booking_enabled,
            'has_active_service' => $hasActiveService,
            'has_active_professional' => $hasActiveProfessional,
            'has_service_professional_pair' => $hasPair,
            'publishable' => $unit->online_booking_enabled && $hasPair,
        ];
    }
}
