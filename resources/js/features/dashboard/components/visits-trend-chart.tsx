import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import type { DailyVisitTrend } from '../types';

type VisitsTrendChartProps = {
    data?: DailyVisitTrend[];
};

export function VisitsTrendChart({ data = [] }: VisitsTrendChartProps) {
    const totalVisits = data.reduce((acc, curr) => acc + curr.visits, 0);
    const maxVisits = Math.max(...data.map((item) => item.visits), 0);
    const hasData = data.length > 0 && totalVisits > 0;

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
                {!hasData ? (
                    <div className="flex h-44 items-center justify-center rounded-lg border border-dashed border-border/60 text-xs text-muted-foreground">
                        Nenhuma visita registrada no período
                    </div>
                ) : (
                    <div className="flex h-44 items-end gap-2 sm:gap-4">
                        {data.map((item, idx) => {
                            const heightPct = maxVisits > 0 ? Math.max(10, Math.round((item.visits / maxVisits) * 100)) : 0;
                            const isHighest = item.visits > 0 && item.visits === maxVisits;

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
                )}
            </CardContent>
        </Card>
    );
}
