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
    duration_minutes?: number | null;
    id: string;
    name: string;
    notes?: string | null;
    phone?: string | null;
    price_cents?: number | null;
    status?: string;
};

export type CalendarAppointmentItem = {
    duration_minutes?: number | null;
    price_cents?: number | null;
    service?: CalendarOption | null;
    service_id?: string | null;
};

export type CalendarAppointmentSale = {
    automatic?: boolean | null;
    created_automatically?: boolean | null;
    id: string;
    origin?: string | null;
    reference_label?: string | null;
    source?: string | null;
    status: string;
    synchronization_status?: 'active' | 'paused' | 'review' | string | null;
};

export type CalendarAppointmentSaleLink = {
    automatic?: boolean | null;
    created_automatically?: boolean | null;
    id: string;
    sale?: CalendarAppointmentSale | null;
    sale_id: string;
};

export type CalendarAppointment = {
    cancel_reason?: string | null;
    cancelled_at?: string | null;
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
    online_booking?: boolean | null;
    online_booking_campaign_link_id?: string | null;
    professional?: CalendarOption | null;
    professional_id?: string | null;
    reminder_enabled?: boolean;
    sale_link?: CalendarAppointmentSaleLink | null;
    sale_links?: CalendarAppointmentSaleLink[];
    service?: CalendarOption | null;
    service_id?: string | null;
    source?: 'internal' | 'online' | 'imported' | string | null;
    starts_at: string;
    status: AppointmentStatus;
};

export type AppointmentSaleAutomation = {
    category?: SaleCategoryOptionSummary | null;
    category_id?: string | null;
    enabled?: boolean | null;
    last_error?: string | null;
    is_enabled?: boolean | null;
    supported?: boolean | null;
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
        appointment_sale_automation?: AppointmentSaleAutomation | null;
        customers?: CalendarOption[];
        professionals?: CalendarOption[];
        services?: CalendarOption[];
        sale_categories?: SaleCategoryOptionSummary[];
        statuses?: AppointmentStatus[];
        timezone?: string;
    };
    professionals?: CalendarOption[];
    range?: CalendarRange;
    schedule_blocks?: ScheduleBlock[];
    scheduleBlocks?: ScheduleBlock[];
    services?: CalendarOption[];
};
