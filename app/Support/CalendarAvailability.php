<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\ScheduleBlock;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class CalendarAvailability
{
    /**
     * @param  list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $candidates
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function availableCandidates(string $tenantId, string $unitId, string $professionalId, array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $from = min(array_column($candidates, 'starts_at'));
        $to = max(array_column($candidates, 'ends_at'));
        $appointments = Appointment::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)
            ->where('professional_id', $professionalId)->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('starts_at', '<', $to->utc())->where('ends_at', '>', $from->utc())->get(['starts_at', 'ends_at']);
        $blocks = ScheduleBlock::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('status', 'active')
            ->where(fn ($query) => $query->where('professional_id', $professionalId)->orWhereNull('professional_id'))
            ->where('starts_at', '<', $to->utc())->where('ends_at', '>', $from->utc())->get(['starts_at', 'ends_at']);
        $rules = AvailabilityRule::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)
            ->where('professional_id', $professionalId)->where('status', 'active')->get(['weekday', 'starts_at', 'ends_at', 'timezone']);
        $timezone = $this->unitTimezone($tenantId, $unitId);

        return array_values(array_filter($candidates, function (array $candidate) use ($appointments, $blocks, $rules, $timezone): bool {
            $start = $candidate['starts_at'];
            $end = $candidate['ends_at'];
            $conflicts = fn ($item): bool => $item->starts_at->lt($end) && $item->ends_at->gt($start);
            if ($appointments->contains($conflicts) || $blocks->contains($conflicts)) {
                return false;
            }

            $localStart = $start->setTimezone($timezone);
            $localEnd = $end->setTimezone($timezone);

            return $rules->contains(function (AvailabilityRule $rule) use ($localStart, $localEnd): bool {
                $ruleStart = $localStart->setTimezone($rule->timezone);
                $ruleEnd = $localEnd->setTimezone($rule->timezone);

                return $ruleStart->dayOfWeek === (int) $rule->weekday
                    && $ruleStart->toDateString() === $ruleEnd->toDateString()
                    && $ruleStart->format('H:i:s') >= (string) $rule->starts_at
                    && $ruleEnd->format('H:i:s') <= (string) $rule->ends_at;
            });
        }));
    }

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
            ->where('starts_at', '<', $endsAt->utc())
            ->where('ends_at', '>', $startsAt->utc())
            ->exists();

        $blockConflict = ScheduleBlock::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('professional_id', $professionalId)->orWhereNull('professional_id'))
            ->where('starts_at', '<', $endsAt->utc())
            ->where('ends_at', '>', $startsAt->utc())
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
