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
    id: string;
    name: string;
    phone?: string | null;
    status?: string;
};

export type CalendarAppointmentItem = {
    service?: CalendarOption | null;
    service_id?: string | null;
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

export type CalendarRange = {
    end: string;
    label?: string;
    start: string;
};

export type CalendarProps = {
    appointments?: CalendarAppointment[];
    calendar?: {
        appointments?: CalendarAppointment[];
        filters?: CalendarFilters;
        range?: CalendarRange;
        timezone?: string;
    };
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
        statuses?: AppointmentStatus[];
    };
    professionals?: CalendarOption[];
    range?: CalendarRange;
    services?: CalendarOption[];
};
