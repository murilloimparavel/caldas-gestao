import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import type { DailyVisitTrend } from '../types';

type VisitsTrendChartProps = {
    data?: DailyVisitTrend[];
};

const DEFAULT_TREND_DATA: DailyVisitTrend[] = [
    { date: '2026-08-20', label: 'Seg', visits: 24 },
    { date: '2026-08-21', label: 'Ter', visits: 38 },
    { date: '2026-08-22', label: 'Qua', visits: 42 },
    { date: '2026-08-23', label: 'Qui', visits: 35 },
    { date: '2026-08-24', label: 'Sex', visits: 58 },
    { date: '2026-08-25', label: 'Sáb', visits: 64 },
    { date: '2026-08-26', label: 'Dom', visits: 18 },
];

export function VisitsTrendChart({ data = DEFAULT_TREND_DATA }: VisitsTrendChartProps) {
    const maxVisits = Math.max(...data.map((item) => item.visits), 1);
    const totalVisits = data.reduce((acc, curr) => acc + curr.visits, 0);

    return (
        <Card className="border-border/60">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div>
                    <CardTitle className="text-base font-semibold">Tendência de Visitas</CardTitle>
                    <CardDescription className="text-xs">Fluxo diário de clientes no estabelecimento</CardDescription>
                </div>
                <div className="text-right">
                    <span className="text-xl font-bold text-foreground">{totalVisits}</span>
                    <span className="ml-1 text-xs text-muted-foreground">visitas totais</span>
                </div>
            </CardHeader>
            <CardContent className="pt-4">
                <div className="flex h-44 items-end gap-2 sm:gap-4">
                    {data.map((item, idx) => {
                        const heightPct = Math.max(10, Math.round((item.visits / maxVisits) * 100));
                        const isHighest = item.visits === maxVisits;

                        return (
                            <div key={idx} className="group relative flex flex-1 flex-col items-center gap-2">
                                {/* Tooltip hover */}
                                <div className="absolute -top-9 z-10 hidden rounded bg-popover px-2 py-1 text-xs font-semibold text-popover-foreground shadow-md group-hover:block">
                                    {item.visits} visitas
                                </div>

                                <div className="relative flex w-full flex-1 items-end justify-center rounded-t bg-muted/40 p-1">
                                    <div
                                        style={{ height: `${heightPct}%` }}
                                        className={`w-full max-w-[32px] rounded-t transition-all duration-300 group-hover:brightness-110 ${
                                            isHighest
                                                ? 'bg-primary shadow-xs'
                                                : 'bg-primary/60 dark:bg-primary/40'
                                        }`}
                                    />
                                </div>
                                <span className="text-xs font-medium text-muted-foreground">{item.label}</span>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}
