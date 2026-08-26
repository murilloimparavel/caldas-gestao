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
import type { DashboardSnapshot } from '@/features/dashboard/types';
import { dashboard } from '@/routes';

type Props = {
    dashboard: DashboardSnapshot;
};

export default function Dashboard({ dashboard: snapshot }: Props) {
    return (
        <>
            <Head title="Dashboard Operacional" />
            <div className="dashboard-canvas flex min-h-full flex-1 flex-col gap-6 px-4 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8 lg:py-8">
                {/* 1. Header with greeting, filters and refresh */}
                <DashboardHeader
                    userName={snapshot.userName ?? 'Usuário'}
                    period={snapshot.period}
                    startDate={snapshot.startDate}
                    endDate={snapshot.endDate}
                />

                {/* 2. Top KPI Cards (Vendas, Agendamentos, Comandas) */}
                <TopKpiCards
                    totalSales={snapshot.topKpis?.totalSales}
                    appointments={snapshot.topKpis?.appointments}
                    tickets={snapshot.topKpis?.tickets}
                />

                {/* 3. Main Operational Charts Grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <VisitsTrendChart data={snapshot.visitsTrend} />
                    </div>
                    <div className="lg:col-span-1">
                        <StatusDonutChart data={snapshot.statusBreakdown} />
                    </div>
                </div>

                {/* 4. Sales Category & Schedule Heatmap Grid */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <SalesCategoryBreakdown data={snapshot.salesCategoryBreakdown} />
                    <ScheduleHeatmap data={snapshot.scheduleHeatmap} />
                </div>

                {/* 5. Professional Performance & Today's Operational Queue */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <ProfessionalPerformanceTable data={snapshot.professionalPerformance} />
                    <div className="flex flex-col gap-6">
                        <NextAppointments appointments={snapshot.appointments ?? []} />
                        <AttentionQueue items={snapshot.attentionItems ?? []} />
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
