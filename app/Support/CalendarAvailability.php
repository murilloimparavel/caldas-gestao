<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\ScheduleBlock;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class CalendarAvailability
{
    public function assertAvailable(
        string $tenantId,
        string $unitId,
        string $professionalId,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $exceptAppointmentId = null,
    ): void {
        $this->lockProfessional($tenantId, $unitId, $professionalId);

        $appointmentConflict = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('professional_id', $professionalId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($exceptAppointmentId !== null, fn ($query) => $query->where((new Appointment)->getTable().'.id', '<>', $exceptAppointmentId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        $blockConflict = ScheduleBlock::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('professional_id', $professionalId)->orWhereNull('professional_id'))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        $localStartsAt = $startsAt->setTimezone($this->unitTimezone($tenantId, $unitId));
        $localEndsAt = $endsAt->setTimezone($localStartsAt->getTimezone());
        $hasAvailability = AvailabilityRule::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('professional_id', $professionalId)
            ->where('status', 'active')
            ->get(['weekday', 'starts_at', 'ends_at', 'timezone'])
            ->contains(function (AvailabilityRule $rule) use ($localStartsAt, $localEndsAt): bool {
                $ruleStartsAt = $localStartsAt->setTimezone($rule->timezone);
                $ruleEndsAt = $localEndsAt->setTimezone($rule->timezone);

                return $ruleStartsAt->dayOfWeek === (int) $rule->weekday
                    && $ruleStartsAt->toDateString() === $ruleEndsAt->toDateString()
                    && $ruleStartsAt->format('H:i:s') >= (string) $rule->starts_at
                    && $ruleEndsAt->format('H:i:s') <= (string) $rule->ends_at;
            });

        if ($appointmentConflict || $blockConflict) {
            throw new CalendarConflictException('The professional is not available for the selected time window.');
        }

        if (! $hasAvailability) {
            throw new CalendarConflictException('No active availability rule covers the selected time window.');
        }
    }

    private function lockProfessional(string $tenantId, string $unitId, string $professionalId): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne(
            'select pg_advisory_xact_lock(hashtextextended(?, 0))',
            ["{$tenantId}:{$unitId}:{$professionalId}"],
        );
    }

    private function unitTimezone(string $tenantId, string $unitId): string
    {
        return (string) (Unit::query()->whereKey($unitId)->where('tenant_id', $tenantId)->value('timezone') ?? config('app.timezone'));
    }
}
