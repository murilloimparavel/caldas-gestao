import { Head } from '@inertiajs/react';
import { AttentionQueue } from '@/features/dashboard/components/attention-queue';
import { DashboardHeader } from '@/features/dashboard/components/dashboard-header';
import { NextAppointments } from '@/features/dashboard/components/next-appointments';
import { ProfessionalPerformanceTable } from '@/features/dashboard/components/professional-performance-table';
import { ProfessionalOccupancyChart } from '@/features/dashboard/components/professional-occupancy-chart';
import { SalesCategoryBreakdown } from '@/features/dashboard/components/sales-category-breakdown';
import { ScheduleHeatmap } from '@/features/dashboard/components/schedule-heatmap';
import { StatusDonutChart } from '@/features/dashboard/components/status-donut-chart';
import { TopKpiCards } from '@/features/dashboard/components/top-kpi-cards';
import { VisitsTrendChart } from '@/features/dashboard/components/visits-trend-chart';
import type {
    DashboardSnapshot,
    PeriodFilter,
} from '@/features/dashboard/types';
import { dashboard } from '@/routes';

type Props = {
    dashboard: DashboardSnapshot;
};

export default function Dashboard({ dashboard: snapshot }: Props) {
    return (
        <>
            <Head title="Dashboard Operacional" />
            <div className="dashboard-canvas flex min-h-full min-w-0 flex-1 flex-col gap-5 px-3 py-4 pb-[calc(6rem+env(safe-area-inset-bottom))] min-[380px]:px-4 sm:gap-6 sm:px-6 sm:py-6 sm:pb-6 lg:px-8 lg:py-8">
                {/* 1. Header with greeting, filters and refresh */}
                <DashboardHeader
                    key={`${snapshot.period.preset}-${snapshot.period.startDate}-${snapshot.period.endDate}`}
                    userName={snapshot.userName}
                    period={snapshot.period.preset as PeriodFilter}
                    startDate={snapshot.period.startDate}
                    endDate={snapshot.period.endDate}
                />

                <section aria-labelledby="dashboard-kpis-title">
                    <h2 id="dashboard-kpis-title" className="sr-only">
                        Indicadores principais
                    </h2>
                    <TopKpiCards
                        totalSales={snapshot.topKpis.totalSales}
                        appointments={snapshot.topKpis.appointments}
                        tickets={snapshot.topKpis.tickets}
                    />
                </section>

                <section aria-labelledby="dashboard-agenda-title">
                    <h2 id="dashboard-agenda-title" className="sr-only">
                        Agenda e alertas operacionais
                    </h2>
                    <div className="grid min-w-0 gap-4 sm:gap-5 lg:grid-cols-2">
                        <NextAppointments
                            appointments={snapshot.appointments}
                        />
                        <AttentionQueue items={snapshot.attentionItems} />
                    </div>
                </section>

                <section aria-labelledby="dashboard-visits-title">
                    <h2 id="dashboard-visits-title" className="sr-only">
                        Visitas e status dos agendamentos
                    </h2>
                    <div className="grid min-w-0 gap-4 sm:gap-5 xl:grid-cols-2 2xl:grid-cols-3">
                        <div className="min-w-0 2xl:col-span-2">
                            <VisitsTrendChart data={snapshot.visitsTrend} />
                        </div>
                        <div className="min-w-0">
                            <StatusDonutChart data={snapshot.statusBreakdown} />
                        </div>
                    </div>
                </section>

                <section aria-labelledby="dashboard-sales-title">
                    <h2 id="dashboard-sales-title" className="sr-only">
                        Vendas e ocupação de horários
                    </h2>
                    <div className="grid min-w-0 gap-4 sm:gap-5 lg:grid-cols-2">
                        <SalesCategoryBreakdown
                            data={snapshot.salesCategoryBreakdown}
                        />
                        <ScheduleHeatmap data={snapshot.scheduleHeatmap} />
                    </div>
                </section>

                <section aria-labelledby="dashboard-professionals-title">
                    <h2 id="dashboard-professionals-title" className="sr-only">
                        Ocupação e desempenho por profissional
                    </h2>
                    <div className="grid min-w-0 gap-4 sm:gap-5 2xl:grid-cols-2">
                        <ProfessionalOccupancyChart
                            data={snapshot.professionalOccupancy}
                        />
                        <ProfessionalPerformanceTable
                            data={snapshot.professionalPerformance}
                        />
                    </div>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
