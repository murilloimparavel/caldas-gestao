export type PeriodFilter = 'today' | '7days' | '30days' | 'this_month' | 'custom';

export type DashboardMetric = {
    label: string;
    value: string;
    detail: string;
    tone: 'brand' | 'success' | 'warning' | 'neutral';
};

export type KpiCardData = {
    id: string;
    title: string;
    value: string;
    changePercentage: number;
    trend: 'up' | 'down' | 'neutral';
    sparklineData: number[];
    unit?: string;
};

export type DailyVisitTrend = {
    date: string;
    label: string;
    visits: number;
};

export type AppointmentStatusCount = {
    status: string;
    label: string;
    count: number;
    percentage: number;
    color: string;
};

export type ProfessionalPerformance = {
    id: string;
    name: string;
    avatarUrl?: string;
    totalServices: number;
    changePercentage: number;
    averageTicket: string;
};

export type CategorySales = {
    category: string;
    label: string;
    totalAmount: string;
    percentage: number;
    color: string;
};

export type ScheduleHeatmapCell = {
    dayOfWeek: number; // 0 = Dom, 1 = Seg, ...
    dayLabel: string;
    hour: number; // 8 to 19
    occupancyPercentage: number; // 0 to 100
};

export type DashboardMode = 'empty' | 'active';

export type AppointmentSummary = {
    id: string;
    startsAt: string;
    client: string;
    service: string;
    professional: string;
    status: 'confirmed' | 'waiting' | 'in-service';
};

export type AttentionItem = {
    id: string;
    label: string;
    detail: string;
    level: 'high' | 'medium' | 'low';
};

export type DashboardSnapshot = {
    mode: DashboardMode;
    userName?: string;
    period?: PeriodFilter;
    startDate?: string;
    endDate?: string;
    metrics: DashboardMetric[];
    topKpis?: {
        totalSales: KpiCardData;
        appointments: KpiCardData;
        tickets: KpiCardData;
    };
    visitsTrend?: DailyVisitTrend[];
    statusBreakdown?: AppointmentStatusCount[];
    professionalPerformance?: ProfessionalPerformance[];
    salesCategoryBreakdown?: CategorySales[];
    scheduleHeatmap?: ScheduleHeatmapCell[];
    appointments: AppointmentSummary[];
    attentionItems: AttentionItem[];
};
