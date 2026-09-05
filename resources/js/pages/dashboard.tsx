import { Head } from '@inertiajs/react';
import { AttentionQueue } from '@/features/dashboard/components/attention-queue';
import { DashboardHeader } from '@/features/dashboard/components/dashboard-header';
import { NextAppointments } from '@/features/dashboard/components/next-appointments';
import { ProfessionalPerformanceTable } from '@/features/dashboard/components/professional-performance-table';
import { SalesCategoryBreakdown } from '@/features/dashboard/components/sales-category-breakdown';
import { ScheduleHeatmap } from '@/features/dashboard/components/schedule-heatmap';
import { StatusDonutChart } from '@/features/dashboard/components/status-donut-chart';
import { TopKpiCards } from '@/features/dashboard/components/top-kpi-cards';
import { VisitsTrendChart } from '@/features/dashboard/components/visits-trend-chart';
import type {
    DashboardSnapshot,
    KpiCardData,
} from '@/features/dashboard/types';
import { dashboard } from '@/routes';

type Props = {
    dashboard: DashboardSnapshot;
};

export default function Dashboard({ dashboard: snapshot }: Props) {
    // 1. Format Top KPIs from snapshot if not explicitly provided
    const formatCurrency = (cents?: number) => {
        return ((cents ?? 0) / 100).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
    };

    const totalSalesKpi: KpiCardData = snapshot.topKpis?.totalSales ?? {
        id: 'sales',
        title: 'Vendas Totais',
        value: formatCurrency(snapshot.total_sales_cents),
        changePercentage: snapshot.sales_variation_percentage ?? 0,
        trend:
            (snapshot.sales_variation_percentage ?? 0) > 0
                ? 'up'
                : (snapshot.sales_variation_percentage ?? 0) < 0
                  ? 'down'
                  : 'neutral',
        sparklineData: [],
    };

    const appointmentsKpi: KpiCardData = snapshot.topKpis?.appointments ?? {
        id: 'appointments',
        title: 'Agendamentos',
        value: String(snapshot.total_appointments ?? 0),
        changePercentage: snapshot.growth_rate_percentage ?? 0,
        trend:
            (snapshot.growth_rate_percentage ?? 0) > 0
                ? 'up'
                : (snapshot.growth_rate_percentage ?? 0) < 0
                  ? 'down'
                  : 'neutral',
        sparklineData: [],
    };

    const ticketsKpi: KpiCardData = snapshot.topKpis?.tickets ?? {
        id: 'tickets',
        title: 'Ticket Médio',
        value: formatCurrency(snapshot.ticket_medio?.current_cents),
        changePercentage: snapshot.ticket_medio?.variation_percentage ?? 0,
        trend:
            (snapshot.ticket_medio?.variation_percentage ?? 0) > 0
                ? 'up'
                : (snapshot.ticket_medio?.variation_percentage ?? 0) < 0
                  ? 'down'
                  : 'neutral',
        sparklineData: [],
    };

    const periodValue =
        typeof snapshot.period === 'string'
            ? snapshot.period
            : snapshot.period?.preset;
    const startDateValue =
        snapshot.startDate ??
        (typeof snapshot.period === 'object'
            ? snapshot.period.start_date
            : undefined);
    const endDateValue =
        snapshot.endDate ??
        (typeof snapshot.period === 'object'
            ? snapshot.period.end_date
            : undefined);

    return (
        <>
            <Head title="Dashboard Operacional" />
            <div className="dashboard-canvas flex min-h-full flex-1 flex-col gap-6 px-4 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8 lg:py-8">
                {/* 1. Header with greeting, filters and refresh */}
                <DashboardHeader
                    userName={snapshot.userName ?? 'Usuário'}
                    period={periodValue as any}
                    startDate={startDateValue}
                    endDate={endDateValue}
                />

                {/* 2. Top KPI Cards (Vendas, Agendamentos, Comandas/Ticket Médio) */}
                <TopKpiCards
                    totalSales={totalSalesKpi}
                    appointments={appointmentsKpi}
                    tickets={ticketsKpi}
                />

                {/* 3. Main Operational Charts Grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <VisitsTrendChart
                            data={snapshot.visitsTrend ?? snapshot.visits_trend}
                        />
                    </div>
                    <div className="lg:col-span-1">
                        <StatusDonutChart
                            data={
                                snapshot.statusBreakdown ??
                                snapshot.status_breakdown
                            }
                        />
                    </div>
                </div>

                {/* 4. Sales Category & Schedule Heatmap Grid */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <SalesCategoryBreakdown
                        data={
                            snapshot.salesCategoryBreakdown ??
                            snapshot.sales_by_category
                        }
                    />
                    <ScheduleHeatmap
                        data={
                            snapshot.scheduleHeatmap ??
                            snapshot.schedule_heatmap
                        }
                    />
                </div>

                {/* 5. Professional Performance & Today's Operational Queue */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <ProfessionalPerformanceTable
                        data={
                            snapshot.professionalPerformance ??
                            snapshot.professionals_performance
                        }
                    />
                    <div className="flex flex-col gap-6">
                        <NextAppointments
                            appointments={snapshot.appointments ?? []}
                        />
                        <AttentionQueue
                            items={
                                snapshot.attentionItems ??
                                snapshot.attention_items ??
                                []
                            }
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
