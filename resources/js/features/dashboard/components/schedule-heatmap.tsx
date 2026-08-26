import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import type { ScheduleHeatmapCell } from '../types';

type ScheduleHeatmapProps = {
    data?: ScheduleHeatmapCell[];
};

const DAYS = [
    { id: 1, label: 'Seg' },
    { id: 2, label: 'Ter' },
    { id: 3, label: 'Qua' },
    { id: 4, label: 'Qui' },
    { id: 5, label: 'Sex' },
    { id: 6, label: 'Sáb' },
];

const HOURS = Array.from({ length: 12 }, (_, i) => i + 8); // 8h to 19h

// Generate default mock occupancy map
const generateDefaultHeatmap = (): ScheduleHeatmapCell[] => {
    const cells: ScheduleHeatmapCell[] = [];
    DAYS.forEach((day) => {
        HOURS.forEach((hour) => {
            let pct = Math.floor(Math.sin(hour) * 40 + Math.cos(day.id) * 30 + 40);

            if (hour >= 10 && hour <= 16) {
pct += 30;
} // Peak hours

            if (day.id === 5 || day.id === 6) {
pct += 20;
} // Fri/Sat peak

            pct = Math.max(0, Math.min(100, pct));
            cells.push({
                dayOfWeek: day.id,
                dayLabel: day.label,
                hour,
                occupancyPercentage: pct,
            });
        });
    });

    return cells;
};

const DEFAULT_HEATMAP = generateDefaultHeatmap();

export function ScheduleHeatmap({ data = DEFAULT_HEATMAP }: ScheduleHeatmapProps) {
    const getIntensityClass = (pct: number) => {
        if (pct < 20) {
return 'bg-muted/40 text-muted-foreground/60';
}

        if (pct < 40) {
return 'bg-primary/20 text-primary dark:text-primary';
}

        if (pct < 70) {
return 'bg-primary/50 text-primary-foreground';
}

        return 'bg-primary text-primary-foreground font-bold';
    };

    return (
        <Card className="border-border/60">
            <CardHeader className="pb-3">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <CardTitle className="text-base font-semibold">Ocupação de Horários</CardTitle>
                        <CardDescription className="text-xs">Mapa de calor dos horários de pico (8h às 19h)</CardDescription>
                    </div>
                    {/* Legend scale */}
                    <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                        <span>Livre</span>
                        <div className="flex h-2.5 w-16 overflow-hidden rounded">
                            <div className="flex-1 bg-muted/40" />
                            <div className="flex-1 bg-primary/20" />
                            <div className="flex-1 bg-primary/50" />
                            <div className="flex-1 bg-primary" />
                        </div>
                        <span>Lotado</span>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="pt-2">
                <div className="overflow-x-auto">
                    <div className="min-w-[500px]">
                        {/* Header Hours */}
                        <div className="grid grid-cols-[48px_repeat(12,1fr)] gap-1 text-center text-[10px] font-medium text-muted-foreground pb-1.5">
                            <div />
                            {HOURS.map((h) => (
                                <div key={h}>{h}h</div>
                            ))}
                        </div>

                        {/* Rows per Day */}
                        <div className="grid gap-1">
                            {DAYS.map((day) => (
                                <div key={day.id} className="grid grid-cols-[48px_repeat(12,1fr)] gap-1 items-center">
                                    <span className="text-xs font-medium text-muted-foreground">{day.label}</span>
                                    {HOURS.map((hour) => {
                                        const cell = data.find(
                                            (c) => c.dayOfWeek === day.id && c.hour === hour
                                        );
                                        const pct = cell ? cell.occupancyPercentage : 0;

                                        return (
                                            <div
                                                key={hour}
                                                className={`group relative flex h-7 items-center justify-center rounded text-[10px] transition-all hover:scale-105 hover:z-10 ${getIntensityClass(
                                                    pct
                                                )}`}
                                            >
                                                {pct > 0 ? `${pct}%` : '-'}
                                                {/* Tooltip */}
                                                <div className="absolute -top-8 z-20 hidden rounded bg-popover px-2 py-1 text-[11px] font-medium text-popover-foreground shadow-md group-hover:block whitespace-nowrap">
                                                    {day.label} às {hour}h: {pct}% de ocupação
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
