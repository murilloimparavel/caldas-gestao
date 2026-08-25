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
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
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
        $unitTimezone = $context->unit === null ? config('app.timezone') : ($context->unit->timezone ?? config('app.timezone'));
        $start = CarbonImmutable::parse((string) ($filters['date'] ?? now($unitTimezone)->toDateString()), $unitTimezone)->startOfDay();
        $end = $start->addDays(42);
        $appointments = Appointment::query()->with(['customer:id,name,phone', 'professional:id,name', 'items.service:id,name'])->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unitId)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->when(isset($filters['professional_ids']), fn ($query) => $query->whereIn('professional_id', $filters['professional_ids']))->when(isset($filters['status']), fn ($query) => $query->whereIn('status', $filters['status']))->orderBy('starts_at')->get();
        $appointments = $appointments->map(function (Appointment $appointment): array {
            $item = $appointment->items->first();

            return [...$appointment->toArray(), 'duration_minutes' => $item?->duration_minutes, 'service_id' => $item?->service_id, 'service' => $item?->service?->only(['id', 'name'])];
        })->values();

        $options = [
            'customers' => Customer::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unitId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'phone']),
            'professionals' => Professional::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unitId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'services' => Service::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $unitId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'duration_minutes', 'price_cents']),
            'timezone' => $context->unit === null ? config('app.timezone') : ($context->unit->timezone ?? config('app.timezone')),
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

        return Inertia::render('calendar/index', ['appointments' => $appointments, 'options' => $options, 'filters' => $filters, 'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()], 'calendarSettings' => $calendarSettings]);
    }

    public function store(AppointmentRequest $request, TenantContext $context, CreateAppointment $create): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $create->handle($request->user(), $context, $data)->getKey(), 'resource_type' => 'appointment']);

        return to_route('calendar.index', ['appointment' => $reference['resource_id']])->with('success', 'Agendamento criado.');
    }

    public function update(AppointmentRequest $request, TenantContext $context, Appointment $appointment, UpdateAppointment $update): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $update->handle($request->user(), $context, $appointment, $data)->getKey(), 'resource_type' => 'appointment']);

        return to_route('calendar.index', ['appointment' => $reference['resource_id']])->with('success', 'Agendamento atualizado.');
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
}
