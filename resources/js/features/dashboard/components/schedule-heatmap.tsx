import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import type { ScheduleHeatmapCell, ScheduleHeatmapDay } from '../types';

type ScheduleHeatmapProps = {
    data?: ScheduleHeatmapCell[] | ScheduleHeatmapDay[];
};

const DAYS = [
    { id: 1, label: 'Seg' },
    { id: 2, label: 'Ter' },
    { id: 3, label: 'Qua' },
    { id: 4, label: 'Qui' },
    { id: 5, label: 'Sex' },
    { id: 6, label: 'Sáb' },
    { id: 7, label: 'Dom' },
];

const HOURS = Array.from({ length: 12 }, (_, i) => i + 8); // 8h to 19h
const PERIODS = [
    { id: 'morning', label: 'Manhã', range: '8–11h', hours: [8, 9, 10, 11] },
    {
        id: 'afternoon',
        label: 'Tarde',
        range: '12–15h',
        hours: [12, 13, 14, 15],
    },
    {
        id: 'evening',
        label: 'Fim do dia',
        range: '16–19h',
        hours: [16, 17, 18, 19],
    },
] as const;

export function ScheduleHeatmap({ data = [] }: ScheduleHeatmapProps) {
    const getIntensityClass = (pct: number) => {
        if (pct === 0) {
            return 'border border-border/40 bg-muted/20 text-muted-foreground/60';
        }

        if (pct < 20) {
            return 'bg-primary/15 text-primary dark:text-primary';
        }

        if (pct < 40) {
            return 'bg-primary/20 text-primary dark:text-primary';
        }

        if (pct < 70) {
            return 'bg-primary/50 text-primary-foreground';
        }

        return 'bg-primary text-primary-foreground font-bold';
    };

    const isDayGroupedData = (
        value: ScheduleHeatmapCell | ScheduleHeatmapDay,
    ): value is ScheduleHeatmapDay => 'hours' in value;

    // The dashboard endpoint currently returns flat cells with `count`.
    // Keep support for the previous grouped shape while using the real counts
    // to calculate a relative heat scale.
    const maxCount = data.reduce((max, entry) => {
        if (isDayGroupedData(entry)) {
            return Math.max(
                max,
                ...entry.hours.map((hourData) => hourData.count),
            );
        }

        return Math.max(max, entry.count);
    }, 0);

    const getOccupancyPercentage = (dayId: number, hour: number): number => {
        if (!data || data.length === 0) {
            return 0;
        }

        if (isDayGroupedData(data[0])) {
            const dayData = (data as ScheduleHeatmapDay[]).find(
                (d) => d.day_of_week === dayId,
            );

            if (!dayData) {
                return 0;
            }

            const hourData = dayData.hours.find((h) => h.hour === hour);

            if (!hourData || hourData.count === 0) {
                return 0;
            }

            return maxCount > 0 ? (hourData.count / maxCount) * 100 : 0;
        }

        const cell = (data as ScheduleHeatmapCell[]).find(
            (c) => c.dayOfWeek === dayId && c.hour === hour,
        );

        if (!cell || cell.count === 0) {
            return 0;
        }

        return maxCount > 0 ? (cell.count / maxCount) * 100 : 0;
    };

    const getCellCount = (dayId: number, hour: number): number => {
        if (data.length === 0) {
            return 0;
        }

        if (isDayGroupedData(data[0])) {
            const dayData = data.find(
                (entry): entry is ScheduleHeatmapDay =>
                    isDayGroupedData(entry) && entry.day_of_week === dayId,
            );

            return (
                dayData?.hours.find((hourData) => hourData.hour === hour)
                    ?.count ?? 0
            );
        }

        return (
            data.find(
                (entry): entry is ScheduleHeatmapCell =>
                    !isDayGroupedData(entry) &&
                    entry.dayOfWeek === dayId &&
                    entry.hour === hour,
            )?.count ?? 0
        );
    };

    const getPeriodCount = (dayId: number, hours: readonly number[]): number =>
        hours.reduce((total, hour) => total + getCellCount(dayId, hour), 0);

    const maxPeriodCount = DAYS.reduce(
        (max, day) =>
            Math.max(
                max,
                ...PERIODS.map((period) =>
                    getPeriodCount(day.id, period.hours),
                ),
            ),
        0,
    );

    const getLevelLabel = (
        count: number,
        referenceCount = maxCount,
    ): string => {
        if (count === 0) {
            return 'Livre';
        }

        const relativeCount =
            referenceCount > 0 ? (count / referenceCount) * 100 : 0;

        if (relativeCount < 20) {
            return 'Baixo';
        }

        if (relativeCount < 70) {
            return 'Médio';
        }

        return 'Alto';
    };

    const peak = DAYS.reduce<{
        count: number;
        dayLabel: string;
        hour: number | null;
    }>(
        (currentPeak, day) =>
            HOURS.reduce((dayPeak, hour) => {
                const count = getCellCount(day.id, hour);

                return count > dayPeak.count
                    ? { count, dayLabel: day.label, hour }
                    : dayPeak;
            }, currentPeak),
        { count: 0, dayLabel: '', hour: null },
    );

    return (
        <Card className="max-w-full min-w-0 overflow-hidden border-border/60">
            <CardHeader className="pb-3">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="text-base font-semibold">
                            Ocupação de Horários
                        </CardTitle>
                        <CardDescription className="text-xs">
                            Mapa de calor dos horários de pico (8h às 19h)
                        </CardDescription>
                    </div>
                    {/* Legend scale */}
                    <div
                        className="flex items-center gap-1.5 text-3xs text-muted-foreground"
                        aria-label="Escala de ocupação: menor para maior movimento"
                    >
                        <span>Menor movimento</span>
                        <div className="flex h-2.5 w-16 overflow-hidden rounded">
                            <div className="flex-1 bg-muted/40" />
                            <div className="flex-1 bg-primary/20" />
                            <div className="flex-1 bg-primary/50" />
                            <div className="flex-1 bg-primary" />
                        </div>
                        <span>Maior movimento</span>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="pt-2">
                <div className="mb-4 space-y-2 sm:hidden">
                    {peak.count > 0 ? (
                        <div className="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2.5">
                            <p className="text-3xs font-medium tracking-wide text-muted-foreground uppercase">
                                Horário de pico
                            </p>
                            <p className="mt-1 text-sm font-semibold text-foreground">
                                {peak.dayLabel} às {peak.hour}h · {peak.count}{' '}
                                {peak.count === 1
                                    ? 'agendamento'
                                    : 'agendamentos'}
                            </p>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed border-border px-3 py-2.5 text-xs text-muted-foreground">
                            Nenhum agendamento registrado neste período.
                        </div>
                    )}
                </div>

                <div
                    className="space-y-2 sm:hidden"
                    role="table"
                    aria-label="Resumo de agendamentos por dia e período"
                >
                    <div
                        className="grid grid-cols-[3.25rem_repeat(3,minmax(0,1fr))] gap-1 text-center text-3xs font-medium text-muted-foreground"
                        role="row"
                    >
                        <div role="columnheader" />
                        {PERIODS.map((period) => (
                            <div key={period.id} role="columnheader">
                                <span className="block">{period.label}</span>
                                <span className="font-normal">
                                    {period.range}
                                </span>
                            </div>
                        ))}
                    </div>
                    {DAYS.map((day) => (
                        <div
                            key={day.id}
                            className="grid grid-cols-[3.25rem_repeat(3,minmax(0,1fr))] items-stretch gap-1"
                            role="row"
                        >
                            <span
                                className="flex items-center text-xs font-medium text-muted-foreground"
                                role="rowheader"
                            >
                                {day.label}
                            </span>
                            {PERIODS.map((period) => {
                                const count = getPeriodCount(
                                    day.id,
                                    period.hours,
                                );
                                const level = getLevelLabel(
                                    count,
                                    maxPeriodCount,
                                );
                                const intensity =
                                    maxPeriodCount > 0
                                        ? (count / maxPeriodCount) * 100
                                        : 0;

                                return (
                                    <div
                                        key={period.id}
                                        role="cell"
                                        aria-label={`${day.label}, ${period.label} (${period.range}): ${count} ${count === 1 ? 'agendamento' : 'agendamentos'}, nível ${level}`}
                                        className={`flex min-h-12 flex-col items-center justify-center rounded-md px-1 py-1 text-center ${getIntensityClass(intensity)}`}
                                    >
                                        <span className="text-sm font-semibold tabular-nums">
                                            {count}
                                        </span>
                                        <span className="text-[9px] leading-tight">
                                            {level}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                </div>

                <div
                    className="hidden max-w-full min-w-0 overflow-x-auto overscroll-x-contain rounded-md pb-1 sm:block"
                    role="region"
                    aria-label="Mapa de calor rolável horizontalmente"
                    // The scroll region needs focus so keyboard users can pan it.
                    // eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex
                    tabIndex={0}
                >
                    <div className="w-[560px] min-w-[560px] sm:w-full sm:min-w-[540px]">
                        {/* Header Hours */}
                        <div className="grid grid-cols-[48px_repeat(12,1fr)] gap-1 pb-1.5 text-center text-2xs font-medium text-muted-foreground">
                            <div />
                            {HOURS.map((h) => (
                                <div key={h}>{h}h</div>
                            ))}
                        </div>

                        {/* Rows per Day */}
                        <div
                            className="grid gap-1"
                            aria-label="Ocupação por dia e horário"
                        >
                            {DAYS.map((day) => (
                                <div
                                    key={day.id}
                                    className="grid grid-cols-[48px_repeat(12,1fr)] items-center gap-1"
                                >
                                    <span className="text-xs font-medium text-muted-foreground">
                                        {day.label}
                                    </span>
                                    {HOURS.map((hour) => {
                                        const pct = getOccupancyPercentage(
                                            day.id,
                                            hour,
                                        );
                                        const count = getCellCount(
                                            day.id,
                                            hour,
                                        );

                                        return (
                                            <div
                                                key={hour}
                                                role="gridcell"
                                                tabIndex={0}
                                                aria-label={`${day.label}, ${hour} horas: ${count > 0 ? `${count} ${count === 1 ? 'agendamento' : 'agendamentos'}, nível ${getLevelLabel(count)}` : 'nenhum agendamento'}`}
                                                className={`group relative flex h-7 items-center justify-center rounded text-2xs transition-all hover:z-10 hover:scale-105 focus-visible:z-10 focus-visible:scale-105 ${getIntensityClass(
                                                    pct,
                                                )}`}
                                            >
                                                {count > 0 ? count : 'Livre'}
                                                {/* Tooltip */}
                                                <div className="absolute -top-8 z-20 hidden rounded bg-popover px-2 py-1 text-3xs font-medium whitespace-nowrap text-popover-foreground shadow-md group-hover:block">
                                                    {day.label} às {hour}h:{' '}
                                                    {count > 0
                                                        ? `${count} ${count === 1 ? 'agendamento' : 'agendamentos'} · nível ${getLevelLabel(count)}`
                                                        : 'Nenhum agendamento'}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
