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

    return (
        <Card className="border-border/60">
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
                <div className="overflow-x-auto">
                    <div className="min-w-[500px]">
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
                                                aria-label={`${day.label}, ${hour} horas: ${count > 0 ? `${count} agendamentos, ${Math.round(pct)}% do maior movimento` : 'nenhum agendamento'}`}
                                                className={`group relative flex h-7 items-center justify-center rounded text-2xs transition-all hover:z-10 hover:scale-105 focus-visible:z-10 focus-visible:scale-105 ${getIntensityClass(
                                                    pct,
                                                )}`}
                                            >
                                                {count > 0
                                                    ? `${Math.round(pct)}%`
                                                    : 'Livre'}
                                                {/* Tooltip */}
                                                <div className="absolute -top-8 z-20 hidden rounded bg-popover px-2 py-1 text-3xs font-medium whitespace-nowrap text-popover-foreground shadow-md group-hover:block">
                                                    {day.label} às {hour}h:{' '}
                                                    {count > 0
                                                        ? `${count} ${count === 1 ? 'agendamento' : 'agendamentos'} · ${Math.round(pct)}% da faixa mais ocupada`
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
