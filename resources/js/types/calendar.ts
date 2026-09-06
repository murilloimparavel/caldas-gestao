export type CalendarView = 'day' | 'week' | 'month';

export type AppointmentStatus =
    | 'draft'
    | 'scheduled'
    | 'confirmed'
    | 'checked_in'
    | 'in_service'
    | 'completed'
    | 'no_show'
    | 'cancelled';

export type CalendarOption = {
    avatar_url?: string | null;
    id: string;
    name: string;
    phone?: string | null;
    status?: string;
};

export type CalendarAppointmentItem = {
    service?: CalendarOption | null;
    service_id?: string | null;
};

export type CalendarAppointmentSale = {
    id: string;
    reference_label?: string | null;
    status: string;
};

export type CalendarAppointmentSaleLink = {
    id: string;
    sale?: CalendarAppointmentSale | null;
    sale_id: string;
};

export type CalendarAppointment = {
    color?: string | null;
    customer?: CalendarOption | null;
    customer_id?: string | null;
    description?: string | null;
    duration_minutes?: number | null;
    ends_at: string;
    id: string;
    items?: CalendarAppointmentItem[];
    lock_version: number;
    notes?: string | null;
    professional?: CalendarOption | null;
    professional_id?: string | null;
    reminder_enabled?: boolean;
    sale_link?: CalendarAppointmentSaleLink | null;
    sale_links?: CalendarAppointmentSaleLink[];
    service?: CalendarOption | null;
    service_id?: string | null;
    starts_at: string;
    status: AppointmentStatus;
};

export type CalendarFilters = {
    date?: string;
    professional_ids?: string[];
    status?: AppointmentStatus[];
    view?: CalendarView;
};

export type ScheduleBlock = {
    ends_at: string;
    id: string;
    lock_version: number;
    professional?: CalendarOption | null;
    professional_id?: string | null;
    reason?: string | null;
    starts_at: string;
    status: 'active' | 'cancelled';
    timezone: string;
};

export type AvailabilityRule = {
    ends_at: string;
    id: string;
    lock_version: number;
    professional?: CalendarOption | null;
    professional_id: string;
    starts_at: string;
    status: 'active' | 'inactive';
    timezone: string;
    weekday: number;
};

export type CalendarSettings = {
    availability_rules?: AvailabilityRule[];
    schedule_blocks?: ScheduleBlock[];
};

export type CalendarRange = {
    end: string;
    label?: string;
    start: string;
};

export type SaleCategoryOptionSummary = {
    id: string;
    name: string;
    type: string;
    uniqueness_scope: string;
};

export type CalendarProps = {
    appointments?: CalendarAppointment[];
    calendar?: {
        appointments?: CalendarAppointment[];
        filters?: CalendarFilters;
        range?: CalendarRange;
        schedule_blocks?: ScheduleBlock[];
        timezone?: string;
    };
    calendarSettings?: CalendarSettings;
    customers?: CalendarOption[];
    error?: string;
    errors?: Record<string, string | string[]>;
    filters?: CalendarFilters;
    loading?: boolean;
    timezone?: string;
    unitTimezone?: string;
    options?: {
        customers?: CalendarOption[];
        professionals?: CalendarOption[];
        services?: CalendarOption[];
        sale_categories?: SaleCategoryOptionSummary[];
        statuses?: AppointmentStatus[];
    };
    professionals?: CalendarOption[];
    range?: CalendarRange;
    schedule_blocks?: ScheduleBlock[];
    services?: CalendarOption[];
};
