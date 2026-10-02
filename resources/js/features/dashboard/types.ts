export type PeriodFilter = 'today' | '7d' | '30d' | 'this_month' | 'custom';

export type PeriodDetails = {
    preset: string;
    startDate: string;
    endDate: string;
    previousStartDate: string;
    previousEndDate: string;
};

export type DashboardMetric = {
    label: string;
    value: string;
    detail: string;
    tone: 'brand' | 'success' | 'warning' | 'neutral';
};

export type KpiCardData = {
    id?: string;
    title: string;
    value: string;
    changePercentage: number;
    trend: 'up' | 'down' | 'neutral';
    sparklineData: number[];
    todayValue?: string;
    conversionRate?: number;
    unit?: string;
};

export type DailyVisitTrend = {
    date: string;
    label: string;
    visits: number;
    salesCents: number;
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
    avatarUrl: string;
    totalServices: number;
    changePercentage: number;
    averageTicket: string;
    /** Legacy aliases used by the chart while its renderer is migrated. */
    avatar_url?: string | null;
    services_count?: number;
    variation_percentage?: number;
    average_ticket_cents?: number;
};

export type ProfessionalOccupancyItem = {
    id: string;
    name: string;
    avatarUrl: string | null;
    bookedMinutes: number;
    availableMinutes: number;
    occupancyPercentage: number | null;
};

export type ProfessionalOccupancy = {
    overallPercentage: number | null;
    bookedMinutes: number;
    availableMinutes: number;
    professionals: ProfessionalOccupancyItem[];
};

export type CategorySales = {
    category: string;
    label: string;
    totalAmount: string;
    total_cents?: number;
    percentage: number;
    color: string;
};

export type ScheduleHeatmapCell = {
    dayOfWeek: number;
    dayLabel: string;
    hour: number;
    count: number;
};

/** Compatibility shapes kept for isolated chart components during migration. */
export type ScheduleHeatmapHour = {
    hour: number;
    label: string;
    count: number;
};
export type ScheduleHeatmapDay = {
    day_of_week: number;
    day_label: string;
    hours: ScheduleHeatmapHour[];
};
export type SalesByCategoryBackend = {
    services: { total_cents: number; percentage: number };
    products: { total_cents: number; percentage: number };
    packages: { total_cents: number; percentage: number };
};

export type DashboardMode = 'empty' | 'active';

export type AppointmentSummary = {
    id: string;
    startsAt: string;
    client: string;
    service: string;
    professional: string;
    status: 'scheduled' | 'confirmed' | 'checked_in' | 'in_service';
};

export type AttentionItem = {
    id: string;
    label: string;
    detail: string;
    level: 'high' | 'medium' | 'low';
};

export type TicketMedioDetails = {
    current_cents: number;
    previous_cents: number;
    variation_percentage: number;
};

export type AppointmentFunnel = {
    total: number;
    confirmed: number;
    billed: number;
};

export type DashboardSnapshot = {
    period: PeriodDetails;
    userName: string;
    topKpis: {
        totalSales: KpiCardData;
        appointments: KpiCardData;
        tickets: KpiCardData;
    };
    visitsTrend: DailyVisitTrend[];
    statusBreakdown: AppointmentStatusCount[];
    professionalPerformance: ProfessionalPerformance[];
    professionalOccupancy: ProfessionalOccupancy;
    salesCategoryBreakdown: CategorySales[];
    scheduleHeatmap: ScheduleHeatmapCell[];
    appointments: AppointmentSummary[];
    attentionItems: AttentionItem[];
};
