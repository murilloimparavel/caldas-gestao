<?php

namespace App\Http\Controllers;

use App\Actions\Calendar\CreateAvailabilityRule;
use App\Actions\Calendar\CreateScheduleBlock;
use App\Actions\Calendar\DeleteAvailabilityRule;
use App\Actions\Calendar\DeleteScheduleBlock;
use App\Actions\Calendar\UpdateAvailabilityRule;
use App\Actions\Calendar\UpdateScheduleBlock;
use App\Http\Requests\AvailabilityRuleRequest;
use App\Http\Requests\ScheduleBlockRequest;
use App\Models\AvailabilityRule;
use App\Models\ScheduleBlock;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;

final class CalendarAvailabilityController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function storeAvailabilityRule(AvailabilityRuleRequest $request, TenantContext $context, CreateAvailabilityRule $create): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $create->handle($request->user(), $context, $data)->getKey(), 'resource_type' => 'availability_rule']);

        return to_route('professionals.show', ['professional' => $data['professional_id']])->with('success', 'Regra de disponibilidade criada.');
    }

    public function updateAvailabilityRule(AvailabilityRuleRequest $request, TenantContext $context, AvailabilityRule $availabilityRule, UpdateAvailabilityRule $update): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $update->handle($request->user(), $context, $availabilityRule, $data)->getKey(), 'resource_type' => 'availability_rule']);

        return to_route('professionals.show', ['professional' => $data['professional_id']])->with('success', 'Regra de disponibilidade atualizada.');
    }

    public function deleteAvailabilityRule(AvailabilityRuleRequest $request, TenantContext $context, AvailabilityRule $availabilityRule, DeleteAvailabilityRule $delete): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $delete->handle($request->user(), $context, $availabilityRule, (int) $data['lock_version'])->getKey(), 'resource_type' => 'availability_rule']);

        return to_route('professionals.show', ['professional' => $availabilityRule->professional_id])->with('success', 'Regra de disponibilidade removida.');
    }

    public function storeScheduleBlock(ScheduleBlockRequest $request, TenantContext $context, CreateScheduleBlock $create): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $create->handle($request->user(), $context, $data)->getKey(), 'resource_type' => 'schedule_block']);

        return to_route('calendar.index', ['schedule_block' => $reference['resource_id']])->with('success', 'Bloqueio de agenda criado.');
    }

    public function updateScheduleBlock(ScheduleBlockRequest $request, TenantContext $context, ScheduleBlock $scheduleBlock, UpdateScheduleBlock $update): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $update->handle($request->user(), $context, $scheduleBlock, $data)->getKey(), 'resource_type' => 'schedule_block']);

        return to_route('calendar.index', ['schedule_block' => $reference['resource_id']])->with('success', 'Bloqueio de agenda atualizado.');
    }

    public function deleteScheduleBlock(ScheduleBlockRequest $request, TenantContext $context, ScheduleBlock $scheduleBlock, DeleteScheduleBlock $delete): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, fn (): array => ['resource_id' => $delete->handle($request->user(), $context, $scheduleBlock, (int) $data['lock_version'])->getKey(), 'resource_type' => 'schedule_block']);

        return to_route('calendar.index')->with('success', 'Bloqueio de agenda removido.');
    }
}
