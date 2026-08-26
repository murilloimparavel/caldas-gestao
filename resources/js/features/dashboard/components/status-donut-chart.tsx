import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { AppointmentStatusCount } from '../types';

type StatusDonutChartProps = {
    data?: AppointmentStatusCount[];
};

const DEFAULT_STATUS_DATA: AppointmentStatusCount[] = [
    { status: 'completed', label: 'Concluídos', count: 85, percentage: 60, color: '#10b981' },
    { status: 'confirmed', label: 'Confirmados', count: 32, percentage: 22, color: '#3b82f6' },
    { status: 'canceled', label: 'Cancelados', count: 15, percentage: 11, color: '#ef4444' },
    { status: 'no_show', label: 'Faltas', count: 10, percentage: 7, color: '#f59e0b' },
];

export function StatusDonutChart({ data = DEFAULT_STATUS_DATA }: StatusDonutChartProps) {
    const totalCount = data.reduce((acc, item) => acc + item.count, 0);

    // SVG Donut calculation
    const size = 160;
    const strokeWidth = 24;
    const center = size / 2;
    const radius = center - strokeWidth / 2;
    const circumference = 2 * Math.PI * radius;

    // Compute donut segment offsets pure calculation using reduce
    const segments = data.reduce<
        Array<AppointmentStatusCount & { strokeDasharray: string; strokeDashoffset: number }>
    >((acc, item) => {
        const currentSum = acc.reduce((sum, prev) => sum + prev.percentage, 0);
        const offset = -((currentSum / 100) * circumference);

        acc.push({
            ...item,
            strokeDasharray: `${(item.percentage / 100) * circumference} ${circumference}`,
            strokeDashoffset: offset,
        });

        return acc;
    }, []);

    return (
        <Card className="border-border/60">
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-semibold">Status dos Agendamentos</CardTitle>
                <CardDescription className="text-xs">Distribuição percentual por situação</CardDescription>
            </CardHeader>
            <CardContent className="pt-4">
                <div className="flex flex-col items-center gap-6 sm:flex-row sm:justify-between">
                    {/* SVG Donut */}
                    <div className="relative flex shrink-0 items-center justify-center">
                        <svg width={size} height={size} className="rotate-[-90deg]">
                            <circle
                                cx={center}
                                cy={center}
                                r={radius}
                                fill="transparent"
                                stroke="currentColor"
                                strokeWidth={strokeWidth}
                                className="text-muted/30"
                            />
                            {segments.map((item, idx) => (
                                <circle
                                    key={idx}
                                    cx={center}
                                    cy={center}
                                    r={radius}
                                    fill="transparent"
                                    stroke={item.color}
                                    strokeWidth={strokeWidth}
                                    strokeDasharray={item.strokeDasharray}
                                    strokeDashoffset={item.strokeDashoffset}
                                    className="transition-all duration-500 hover:opacity-80"
                                />
                            ))}
                        </svg>
                        <div className="absolute flex flex-col items-center text-center">
                            <span className="text-2xl font-bold tracking-tight text-foreground">{totalCount}</span>
                            <span className="text-[10px] font-medium text-muted-foreground uppercase">Total</span>
                        </div>
                    </div>

                    {/* Legend */}
                    <div className="grid w-full flex-1 gap-2.5">
                        {data.map((item) => (
                            <div key={item.status} className="flex items-center justify-between text-xs">
                                <div className="flex items-center gap-2">
                                    <span
                                        className="size-2.5 shrink-0 rounded-full"
                                        style={{ backgroundColor: item.color }}
                                    />
                                    <span className="font-medium text-foreground">{item.label}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="font-semibold text-foreground">{item.count}</span>
                                    <span className="text-muted-foreground">({item.percentage}%)</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
