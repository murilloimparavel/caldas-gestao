import { TrendingUp, TrendingDown, Minus, DollarSign, CalendarCheck, Receipt } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import type { KpiCardData } from '../types';

type TopKpiCardsProps = {
    totalSales?: KpiCardData;
    appointments?: KpiCardData;
    tickets?: KpiCardData;
};

const DEFAULT_SALES: KpiCardData = {
    id: 'sales',
    title: 'Vendas Totais',
    value: 'R$ 14.850,00',
    changePercentage: 12.5,
    trend: 'up',
    sparklineData: [40, 55, 35, 60, 75, 80, 95],
};

const DEFAULT_APPOINTMENTS: KpiCardData = {
    id: 'appointments',
    title: 'Agendamentos',
    value: '142',
    changePercentage: 8.2,
    trend: 'up',
    sparklineData: [15, 22, 18, 25, 30, 28, 35],
};

const DEFAULT_TICKETS: KpiCardData = {
    id: 'tickets',
    title: 'Comandas',
    value: '98',
    changePercentage: -3.1,
    trend: 'down',
    sparklineData: [20, 18, 15, 14, 16, 12, 11],
};

function Sparkline({ data, trend }: { data: number[]; trend: 'up' | 'down' | 'neutral' }) {
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

    const strokeColor =
        trend === 'up'
            ? '#10b981' // emerald-500
            : trend === 'down'
              ? '#ef4444' // red-500
              : '#9ca3af'; // gray-400

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className="h-8 w-24 overflow-visible">
            <polyline
                fill="none"
                stroke={strokeColor}
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                points={points}
            />
        </svg>
    );
}

export function TopKpiCards({
    totalSales = DEFAULT_SALES,
    appointments = DEFAULT_APPOINTMENTS,
    tickets = DEFAULT_TICKETS,
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
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {kpis.map((kpi) => {
                const Icon = kpi.icon;
                const isUp = kpi.trend === 'up';
                const isDown = kpi.trend === 'down';

                return (
                    <Card key={kpi.id} className="relative overflow-hidden border-border/60 transition-all hover:shadow-sm">
                        <CardContent className="p-5">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className={`flex size-10 items-center justify-center rounded-lg ${kpi.iconBg}`}>
                                        <Icon className="size-5" />
                                    </div>
                                    <span className="text-sm font-medium text-muted-foreground">{kpi.title}</span>
                                </div>
                                <div
                                    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ${
                                        isUp
                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : isDown
                                              ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                                              : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {isUp && <TrendingUp className="size-3" />}
                                    {isDown && <TrendingDown className="size-3" />}
                                    {!isUp && !isDown && <Minus className="size-3" />}
                                    <span>
                                        {kpi.changePercentage > 0 ? '+' : ''}
                                        {kpi.changePercentage}%
                                    </span>
                                </div>
                            </div>

                            <div className="mt-4 flex items-end justify-between">
                                <div>
                                    <p className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                        {kpi.value}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">vs. período anterior</p>
                                </div>
                                <Sparkline data={kpi.sparklineData} trend={kpi.trend} />
                            </div>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
