export type PeriodFilter = 'today' | '7d' | '30d' | 'this_month' | 'custom';

export type PeriodDetails = {
    preset: string;
    start_date: string;
    end_date: string;
    previous_start_date: string;
    previous_end_date: string;
};

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
    sales_cents?: number;
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
    avatar_url?: string | null;
    avatarUrl?: string | null;
    services_count: number;
    totalServices?: number;
    variation_percentage: number;
    changePercentage?: number;
    average_ticket_cents: number;
    averageTicket?: string;
};

export type CategorySales = {
    category: string;
    label: string;
    total_cents?: number;
    totalAmount?: string;
    percentage: number;
    color: string;
};

export type SalesByCategoryBackend = {
    services: { total_cents: number; percentage: number };
    products: { total_cents: number; percentage: number };
    packages: { total_cents: number; percentage: number };
};

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

export type ScheduleHeatmapCell = {
    dayOfWeek: number;
    dayLabel: string;
    hour: number;
    occupancyPercentage: number;
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
    mode?: DashboardMode;
    userName?: string;
    period?: PeriodFilter | PeriodDetails;
    startDate?: string;
    endDate?: string;
    metrics?: DashboardMetric[];
    topKpis?: {
        totalSales: KpiCardData;
        appointments: KpiCardData;
        tickets: KpiCardData;
    };
    total_sales_cents?: number;
    today_sales_cents?: number;
    sales_variation_percentage?: number;
    total_appointments?: number;
    growth_rate_percentage?: number;
    total_sales_count?: number;
    conversion_rate_percentage?: number;
    visitsTrend?: DailyVisitTrend[];
    visits_trend?: DailyVisitTrend[];
    statusBreakdown?: AppointmentStatusCount[];
    status_breakdown?: AppointmentStatusCount[];
    ticket_medio?: TicketMedioDetails;
    professionalPerformance?: ProfessionalPerformance[];
    professionals_performance?: ProfessionalPerformance[];
    salesCategoryBreakdown?: CategorySales[];
    sales_by_category?: SalesByCategoryBackend;
    scheduleHeatmap?: ScheduleHeatmapCell[] | ScheduleHeatmapDay[];
    schedule_heatmap?: ScheduleHeatmapDay[];
    appointment_funnel?: AppointmentFunnel;
    appointments?: AppointmentSummary[];
    attentionItems?: AttentionItem[];
    attention_items?: AttentionItem[];
};

