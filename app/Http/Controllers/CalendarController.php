<?php

namespace App\Http\Controllers;

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\CreateAppointment;
use App\Actions\Appointments\UpdateAppointment;
use App\Http\Requests\AppointmentRequest;
use App\Http\Requests\CalendarIndexRequest;
use App\Http\Requests\CancelAppointmentRequest;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
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

        return Inertia::render('calendar/index', ['appointments' => $appointments, 'options' => $options, 'filters' => $filters, 'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()]]);
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
}
