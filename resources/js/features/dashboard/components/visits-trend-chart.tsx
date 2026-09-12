import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import type { DailyVisitTrend } from '../types';

type VisitsTrendChartProps = {
    data?: DailyVisitTrend[];
};

export function VisitsTrendChart({ data = [] }: VisitsTrendChartProps) {
    const totalVisits = data.reduce((acc, curr) => acc + curr.visits, 0);
    const maxVisits = Math.max(...data.map((item) => item.visits), 0);
    // Keep the timeline visible when the period has no visits. This makes an
    // empty period distinguishable from a missing/failed dashboard response.
    const hasPeriod = data.length > 0;
    const chartData =
        data.length > 60
            ? data.filter(
                  (_, index) => index % Math.ceil(data.length / 60) === 0,
              )
            : data;
    const formatDayLabel = (item: DailyVisitTrend): string => {
        if (item.date) {
            const parsedDate = new Date(`${item.date}T12:00:00`);

            if (!Number.isNaN(parsedDate.getTime())) {
                return parsedDate.toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                });
            }
        }

        return /^\d{1,2}\/\d{1,2}$/.test(item.label) ? item.label : '—';
    };

    return (
        <Card className="min-w-0 overflow-hidden border-border/60">
            <CardHeader className="flex flex-col gap-3 pb-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <CardTitle className="text-base font-semibold">
                        Tendência de Visitas
                    </CardTitle>
                    <CardDescription className="text-xs">
                        Fluxo diário de clientes no estabelecimento
                    </CardDescription>
                </div>
                <div className="text-right">
                    <span className="text-xl font-bold text-foreground">
                        {totalVisits}
                    </span>
                    <span className="ml-1 text-xs text-muted-foreground">
                        visitas totais
                    </span>
                </div>
            </CardHeader>
            <CardContent className="min-w-0 pt-4">
                {!hasPeriod ? (
                    <div className="flex h-44 items-center justify-center rounded-lg border border-dashed border-border/60 text-xs text-muted-foreground">
                        Nenhum dado de visitas disponível para o período
                    </div>
                ) : (
                    <div className="relative min-w-0 overflow-x-auto pb-1">
                        <div
                            className="flex h-44 min-w-full items-end gap-1.5 border-b border-border/50 px-1 sm:gap-2"
                            style={{
                                minWidth:
                                    data.length > 14
                                        ? `${data.length * 44}px`
                                        : undefined,
                            }}
                            role="img"
                            aria-label={`Visitas diárias no período: ${totalVisits} no total`}
                        >
                            {chartData.map((item, idx) => {
                                const heightPct =
                                    maxVisits > 0
                                        ? Math.max(
                                              10,
                                              Math.round(
                                                  (item.visits / maxVisits) *
                                                      100,
                                              ),
                                          )
                                        : 0;
                                const isHighest =
                                    item.visits > 0 &&
                                    item.visits === maxVisits;

                                const accessibleDate = item.date || item.label;
                                const dayLabel = formatDayLabel(item);

                                return (
                                    <div
                                        key={`${item.date}-${idx}`}
                                        className="group relative flex h-full w-7 shrink-0 flex-col items-center justify-end gap-2 sm:w-8"
                                    >
                                        <span className="sr-only">
                                            {accessibleDate}: {item.visits}{' '}
                                            visitas
                                        </span>

                                        <div className="pointer-events-none absolute bottom-10 z-10 hidden rounded-md bg-popover px-2 py-1 text-xs font-semibold whitespace-nowrap text-popover-foreground shadow-md group-focus-within:block group-hover:block">
                                            {accessibleDate}: {item.visits}{' '}
                                            visitas
                                        </div>

                                        <div className="relative flex min-h-0 w-full flex-1 items-end justify-center rounded-t bg-muted/40 p-1">
                                            <div
                                                style={{
                                                    height:
                                                        item.visits > 0
                                                            ? `${heightPct}%`
                                                            : '4px',
                                                    minHeight:
                                                        item.visits > 0
                                                            ? '8px'
                                                            : '4px',
                                                }}
                                                className={`w-full rounded-t transition-all duration-300 group-hover:brightness-110 ${
                                                    isHighest
                                                        ? 'bg-primary shadow-xs'
                                                        : item.visits > 0
                                                          ? 'bg-primary/60 dark:bg-primary/40'
                                                          : 'bg-muted-foreground/20'
                                                }`}
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <span className="text-[10px] font-medium text-muted-foreground sm:text-xs">
                                            {dayLabel}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                        <p className="sr-only" id="visits-series-summary">
                            Série completa de visitas:{' '}
                            {data
                                .map(
                                    (item) =>
                                        `${item.date || item.label}: ${item.visits}`,
                                )
                                .join('; ')}
                            .
                        </p>
                        {totalVisits === 0 && (
                            <p className="mt-3 text-center text-xs text-muted-foreground">
                                Nenhuma visita registrada no período
                            </p>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
