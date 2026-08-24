export type DashboardMetric = {
    label: string;
    value: string;
    detail: string;
    tone: 'brand' | 'success' | 'warning' | 'neutral';
};

export type DashboardMode = 'empty';

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
    metrics: DashboardMetric[];
    appointments: AppointmentSummary[];
    attentionItems: AttentionItem[];
};
