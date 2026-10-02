import { DollarSign, CalendarCheck, Receipt } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import type { KpiCardData } from '../types';
import { MetricDelta } from './metric-delta';

type TopKpiCardsProps = {
    totalSales?: KpiCardData;
    appointments?: KpiCardData;
    tickets?: KpiCardData;
};

function Sparkline({
    data,
    trend,
}: {
    data: number[];
    trend: 'up' | 'down' | 'neutral';
}) {
    if (!data || data.length < 2) {
        return null;
    }

    const min = Math.min(...data);
    const max = Math.max(...data);
    const range = max - min || 1;

    const width = 100;
    const height = 32;

    const points = data
        .map((val, idx) => {
            const x = (idx / (data.length - 1)) * width;
            const y = height - ((val - min) / range) * (height - 8) - 4;

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');

    const strokeClass =
        trend === 'up'
            ? 'text-emerald-500'
            : trend === 'down'
              ? 'text-destructive'
              : 'text-muted-foreground';

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className={`h-8 w-24 overflow-visible ${strokeClass}`}
            role="img"
            aria-label={`Tendência ${trend === 'up' ? 'de alta' : trend === 'down' ? 'de queda' : 'estável'}`}
        >
            <polyline
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                points={points}
            />
        </svg>
    );
}

export function TopKpiCards({
    totalSales = {
        id: 'sales',
        title: 'Vendas Totais',
        value: 'R$ 0,00',
        changePercentage: 0,
        trend: 'neutral',
        sparklineData: [],
    },
    appointments = {
        id: 'appointments',
        title: 'Agendamentos',
        value: '0',
        changePercentage: 0,
        trend: 'neutral',
        sparklineData: [],
    },
    tickets = {
        id: 'tickets',
        title: 'Comandas',
        value: 'R$ 0,00',
        changePercentage: 0,
        trend: 'neutral',
        sparklineData: [],
    },
}: TopKpiCardsProps) {
    const kpis = [
        {
            ...totalSales,
            icon: DollarSign,
            iconBg: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        },
        {
            ...appointments,
            icon: CalendarCheck,
            iconBg: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
        },
        {
            ...tickets,
            icon: Receipt,
            iconBg: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
        },
    ];

    return (
        <div className="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
            {kpis.map((kpi) => {
                const Icon = kpi.icon;

                return (
                    <Card
                        key={kpi.id}
                        className="relative overflow-hidden border-border/60 transition-all hover:shadow-sm"
                    >
                        <CardContent className="p-3.5 sm:p-5">
                            <div className="flex min-w-0 items-center justify-between gap-2">
                                <div className="flex items-center gap-3">
                                    <div
                                        className={`flex size-10 items-center justify-center rounded-lg ${kpi.iconBg}`}
                                    >
                                        <Icon className="size-5" />
                                    </div>
                                    <span className="min-w-0 truncate text-xs font-medium text-muted-foreground sm:text-sm">
                                        {kpi.title}
                                    </span>
                                </div>
                                <MetricDelta
                                    value={kpi.changePercentage}
                                    label=""
                                />
                            </div>

                            <div className="mt-3 flex flex-wrap items-end justify-between gap-2 sm:mt-4">
                                <div>
                                    <p className="text-xl font-bold tracking-tight break-words text-foreground min-[380px]:text-2xl sm:text-3xl">
                                        {kpi.value}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        vs. período anterior
                                    </p>
                                </div>
                                <Sparkline
                                    data={kpi.sparklineData}
                                    trend={kpi.trend}
                                />
                            </div>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
