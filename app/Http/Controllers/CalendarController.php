<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\CreateAppointment;
use App\Actions\Appointments\UpdateAppointment;
use App\Actions\Calendar\CheckInAppointment;
use App\Http\Requests\AppointmentRequest;
use App\Http\Requests\CalendarIndexRequest;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\CheckInAppointmentRequest;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class CalendarController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(CalendarIndexRequest $request, TenantContext $context): Response
    {
        $filters = $request->validated();
        Gate::authorize('viewAny', Appointment::class);
        $unitId = $context->unit?->getKey();
        $unitTimezone = $context->unit?->timezone ?: $context->tenant->timezone ?: config('app.timezone');
        $start = CarbonImmutable::parse((string) ($filters['date'] ?? now($unitTimezone)->toDateString()), $unitTimezone)->startOfDay();
        $end = $start->addDays(42);
        $appointments = Appointment::query()
            ->with([
                'customer:id,name,phone,notes',
                'professional:id,name',
                'items.service:id,name',
                'saleLinks.sale:id,status,reference_label',
            ])
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unitId)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when(isset($filters['professional_ids']), fn ($query) => $query->whereIn('professional_id', $filters['professional_ids']))
            ->when(isset($filters['status']), fn ($query) => $query->whereIn('status', $filters['status']))
            ->orderBy('starts_at')
            ->get();

        $appointments = $appointments->map(function (Appointment $appointment): array {
            $item = $appointment->items->first();
            $saleLink = $appointment->saleLinks->first();
            $saleMetadata = $saleLink?->sale?->source_metadata;
            $createdAutomatically = is_array($saleMetadata)
                && (($saleMetadata['created_automatically'] ?? false) === true
                    || in_array(($saleMetadata['origin'] ?? null), ['appointment_automation', 'automatic'], true));

            return [
                ...$appointment->toArray(),
                'duration_minutes' => $item?->duration_minutes,
                'service_id' => $item?->service_id,
                'service' => $item?->service?->only(['id', 'name']),
                'online_booking' => $appointment->source === 'online' || $appointment->online_booking_campaign_link_id !== null,
                'sale_link' => $saleLink ? [
                    'id' => $saleLink->getKey(),
                    'sale_id' => $saleLink->sale_id,
                    'sale' => $saleLink->sale ? [
                        'id' => $saleLink->sale->getKey(),
                        'status' => $saleLink->sale->status,
                        'reference_label' => $saleLink->sale->reference_label,
                        'created_automatically' => $createdAutomatically,
                        'automatic' => $createdAutomatically,
                        'origin' => is_array($saleMetadata) ? ($saleMetadata['origin'] ?? null) : null,
                    ] : null,
                ] : null,
            ];
        })->values();

        $customerSeeds = $appointments
            ->pluck('customer')
            ->filter()
            ->unique('id')
            ->values();

        $options = [
            'customers' => $customerSeeds,
            'professionals' => Professional::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unitId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'avatar_path'])->map(fn (Professional $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'avatar_path' => $p->avatar_path,
                'avatar_url' => $p->avatar_url,
            ]),
            'timezone' => $unitTimezone,
            'statuses' => ['draft', 'scheduled', 'confirmed', 'checked_in', 'in_service', 'completed', 'no_show', 'cancelled'],
        ];

        $calendarSettings = [
            'availability_rules' => AvailabilityRule::query()
                ->with('professional:id,name')
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unitId)
                ->orderBy('weekday')
                ->orderBy('starts_at')
                ->get(['id', 'professional_id', 'weekday', 'starts_at', 'ends_at', 'timezone', 'status', 'lock_version'])
                ->map(fn (AvailabilityRule $rule): array => [
                    'id' => $rule->getKey(), 'professional_id' => $rule->professional_id,
                    'professional' => $rule->professional?->only(['id', 'name']), 'weekday' => $rule->weekday,
                    'starts_at' => $rule->starts_at, 'ends_at' => $rule->ends_at,
                    'timezone' => $rule->timezone, 'status' => $rule->status, 'lock_version' => $rule->lock_version,
                ])->values(),
            'schedule_blocks' => ScheduleBlock::query()
                ->with('professional:id,name')
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unitId)
                ->where('status', 'active')
                ->where('ends_at', '>', $start)
                ->where('starts_at', '<', $end)
                ->orderBy('starts_at')
                ->get(['id', 'professional_id', 'starts_at', 'ends_at', 'timezone', 'reason', 'status', 'lock_version'])
                ->map(fn (ScheduleBlock $block): array => [
                    'id' => $block->getKey(), 'professional_id' => $block->professional_id,
                    'professional' => $block->professional?->only(['id', 'name']),
                    'starts_at' => $block->starts_at?->toIso8601String(), 'ends_at' => $block->ends_at?->toIso8601String(),
                    'timezone' => $block->timezone, 'reason' => $block->reason,
                    'status' => $block->status, 'lock_version' => $block->lock_version,
                ])->values(),
        ];

        return Inertia::render('calendar/index', [
            'appointments' => $appointments,
            'options' => $options,
            'filters' => $filters,
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'calendarSettings' => $calendarSettings,
            'scheduleBlocks' => $calendarSettings['schedule_blocks'],
        ]);
    }

    public function store(AppointmentRequest $request, TenantContext $context, CreateAppointment $create): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $create->handle($request->user(), $context, $data)->getKey(), 'resource_type' => 'appointment']);
        $appointment = Appointment::query()->where('tenant_id', $context->tenant->getKey())->findOrFail($reference['resource_id']);
        $message = 'Agendamento criado.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('calendar.index', $this->calendarReturnQuery($request, $context, $appointment))->with('success', $message);
    }

    public function update(AppointmentRequest $request, TenantContext $context, Appointment $appointment, UpdateAppointment $update): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $update->handle($request->user(), $context, $appointment, $data)->getKey(), 'resource_type' => 'appointment']);
        $appointment = Appointment::query()->where('tenant_id', $context->tenant->getKey())->findOrFail($reference['resource_id']);
        $message = 'Agendamento atualizado.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('calendar.index', $this->calendarReturnQuery($request, $context, $appointment))->with('success', $message);
    }

    public function cancel(CancelAppointmentRequest $request, TenantContext $context, Appointment $appointment, CancelAppointment $cancel): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $cancel->handle($request->user(), $context, $appointment, $data)->getKey(), 'resource_type' => 'appointment']);

        return to_route('calendar.index')->with('success', 'Agendamento cancelado.');
    }

    public function checkIn(CheckInAppointmentRequest $request, TenantContext $context, Appointment $appointment, CheckInAppointment $checkIn): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $checkIn->handle($request->user(), $context, $appointment, (int) $data['lock_version'])->getKey(), 'resource_type' => 'appointment']);

        return to_route('calendar.index', ['appointment' => $reference['resource_id']])->with('success', 'Cliente registrado como presente.');
    }

    /** @return array{date:string,view:string,professional_ids?:list<string>,status?:list<string>} */
    private function calendarReturnQuery(AppointmentRequest $request, TenantContext $context, Appointment $appointment): array
    {
        $timezone = $appointment->timezone ?: $context->unit?->timezone ?: $context->tenant->timezone ?: config('app.timezone');
        $calendarContext = $request->input('calendar_context', []);
        $calendarContext = is_array($calendarContext) ? $calendarContext : [];

        $query = [
            'date' => $appointment->starts_at->timezone($timezone)->toDateString(),
            'view' => in_array($calendarContext['view'] ?? null, ['day', 'week', 'month'], true) ? $calendarContext['view'] : 'week',
        ];

        $professionalIds = $calendarContext['professional_ids'] ?? [];

        if (is_array($professionalIds)) {
            $professionalIds = array_values(array_filter($professionalIds, fn (mixed $id): bool => is_string($id) && Str::isUuid($id)));

            if ($professionalIds !== [] && in_array((string) $appointment->professional_id, $professionalIds, true)) {
                $query['professional_ids'] = $professionalIds;
            }
        }

        $statuses = $calendarContext['status'] ?? [];

        if (is_array($statuses)) {
            $statuses = array_values(array_filter($statuses, fn (mixed $status): bool => is_string($status) && in_array($status, ['draft', 'scheduled', 'confirmed', 'checked_in', 'in_service', 'completed', 'no_show', 'cancelled'], true)));

            if ($statuses !== [] && in_array($appointment->status, $statuses, true)) {
                $query['status'] = $statuses;
            }
        }

        return $query;
    }
}
