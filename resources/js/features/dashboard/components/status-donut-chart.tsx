import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { AppointmentStatusCount } from '../types';

type StatusDonutChartProps = {
    data?: AppointmentStatusCount[];
};

export function StatusDonutChart({ data = [] }: StatusDonutChartProps) {
    const totalCount = data.reduce((acc, item) => acc + item.count, 0);
    const hasData = data.length > 0 && totalCount > 0;

    // SVG Donut calculation
    const size = 160;
    const strokeWidth = 24;
    const center = size / 2;
    const radius = center - strokeWidth / 2;
    const circumference = 2 * Math.PI * radius;

    // Compute donut segment offsets pure calculation using reduce
    const segments = data.reduce<
        Array<
            AppointmentStatusCount & {
                strokeDasharray: string;
                strokeDashoffset: number;
            }
        >
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
                <CardTitle className="text-base font-semibold">
                    Status dos Agendamentos
                </CardTitle>
                <CardDescription className="text-xs">
                    Distribuição percentual por situação
                </CardDescription>
            </CardHeader>
            <CardContent className="pt-4">
                {!hasData ? (
                    <div className="flex h-[160px] items-center justify-center rounded-lg border border-dashed border-border/60 text-xs text-muted-foreground">
                        Nenhum agendamento no período
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-6 sm:flex-row sm:justify-between">
                        <p className="sr-only" id="status-donut-summary">
                            Distribuição de status:{' '}
                            {data
                                .map(
                                    (item) =>
                                        `${item.label}, ${item.count} (${item.percentage}%)`,
                                )
                                .join('; ')}
                            . Total de {totalCount} agendamentos.
                        </p>
                        {/* SVG Donut */}
                        <div className="relative flex shrink-0 items-center justify-center">
                            <svg
                                width={size}
                                height={size}
                                className="rotate-[-90deg]"
                                aria-hidden="true"
                            >
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
                                <span className="text-2xl font-bold tracking-tight text-foreground">
                                    {totalCount}
                                </span>
                                <span className="text-2xs font-medium text-muted-foreground uppercase">
                                    Total
                                </span>
                            </div>
                        </div>

                        {/* Legend */}
                        <ul
                            className="grid w-full flex-1 gap-2.5"
                            aria-label="Detalhamento dos status"
                        >
                            {data.map((item) => (
                                <li
                                    key={item.status}
                                    className="flex items-center justify-between text-xs"
                                >
                                    <div className="flex items-center gap-2">
                                        <span
                                            className="size-2.5 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: item.color,
                                            }}
                                        />
                                        <span className="font-medium text-foreground">
                                            {item.label}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold text-foreground">
                                            {item.count}
                                        </span>
                                        <span className="text-muted-foreground">
                                            ({item.percentage}%)
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
