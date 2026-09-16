import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    Calendar,
    CalendarCheck2,
    CheckCircle2,
    Clock,
    Lock,
    MessageCircle,
    Phone,
    Plus,
    Receipt,
    RefreshCw,
    SlidersHorizontal,
    Sparkles,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactElement } from 'react';
import {
    CalendarError,
    CalendarLoading,
    CalendarToolbar,
    DayAgenda,
    EmptyCalendar,
    FilterSummary,
    MonthAgenda,
    StatusChip,
    WeekCalendar,
    addDays,
    asInstant,
    dateKey,
    dateTimeValue,
    formatDay,
    formatTime,
    statusLabel,
} from '@/components/calendar';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
} from '@/components/operational';
import { cn } from '@/lib/utils';
import {
    QuickCreateCustomerModal,
    QuickCreateProfessionalModal,
    QuickCreateServiceModal,
} from '@/components/operational/quick-create-dialogs';
import {
    AutomationFeedback,
    automationIssueFromErrors,
} from '@/components/appointment-sale-automation-feedback';
import type { CreatedEntity } from '@/components/operational/quick-create-dialogs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    cancel as cancelAppointment,
    check_in as checkInAppointment,
    store as storeAppointment,
    update as updateAppointment,
} from '@/routes/appointments';
import { index as calendarIndex } from '@/routes/calendar';
import googleCalendar from '@/routes/google_calendar';
import sales from '@/routes/sales';
import {
    destroy as destroyScheduleBlock,
    store as storeScheduleBlock,
} from '@/routes/schedule_blocks';
import type { SharedPageProps } from '@/types';
import type {
    AppointmentStatus,
    CalendarAppointment,
    CalendarAppointmentSaleLink,
    CalendarOption,
    CalendarProps,
    SaleCategoryOptionSummary,
    ScheduleBlock,
} from '@/types/calendar';

const statusOptions: AppointmentStatus[] = [
    'draft',
    'scheduled',
    'confirmed',
    'checked_in',
    'in_service',
    'completed',
    'no_show',
    'cancelled',
];

const CREATABLE_STATUSES: AppointmentStatus[] = [
    'draft',
    'scheduled',
    'confirmed',
];

const STATUS_TRANSITIONS: Record<AppointmentStatus, AppointmentStatus[]> = {
    draft: ['draft', 'scheduled', 'confirmed', 'cancelled'],
    scheduled: ['scheduled', 'confirmed', 'checked_in', 'no_show', 'cancelled'],
    confirmed: ['confirmed', 'checked_in', 'no_show', 'cancelled'],
    checked_in: ['checked_in', 'in_service', 'cancelled'],
    in_service: ['in_service', 'completed'],
    completed: ['completed'],
    no_show: ['no_show'],
    cancelled: ['cancelled'],
};

function getWhatsAppUrl(phone: string | null | undefined): string | null {
    if (!phone) {
        return null;
    }

    const digits = phone.replace(/\D/g, '');

    if (digits.length < 10) {
        return null;
    }

    const fullDigits =
        digits.startsWith('55') && digits.length >= 12 ? digits : `55${digits}`;

    return `https://wa.me/${fullDigits}`;
}

function formatAppointmentHeaderDate(
    startsAt: string,
    endsAt?: string | null,
    durationMinutes?: number | null,
    timeZone = 'UTC',
): string {
    const instant = asInstant(startsAt);

    if (!instant || Number.isNaN(instant.getTime())) {
        return '';
    }

    const formatter = new Intl.DateTimeFormat('pt-BR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone,
    });

    const rawDate = formatter.format(instant);
    const cleanedDate = rawDate.replace('-feira', '');
    const capitalized =
        cleanedDate.charAt(0).toUpperCase() + cleanedDate.slice(1);

    const startTime = formatTime(startsAt, timeZone);
    const endTime = endsAt ? formatTime(endsAt, timeZone) : '';

    let durationStr = '';

    if (durationMinutes && durationMinutes > 0) {
        durationStr = ` (${durationMinutes} min)`;
    } else if (endsAt) {
        const endInstant = asInstant(endsAt);
        const diffMin = Math.round(
            (endInstant.getTime() - instant.getTime()) / 60000,
        );

        if (diffMin > 0) {
            durationStr = ` (${diffMin} min)`;
        }
    }

    if (startTime && endTime) {
        return `${capitalized} • ${startTime} às ${endTime}${durationStr}`;
    }

    return `${capitalized} • ${startTime}`;
}

function formatPriceCents(cents: number | null | undefined): string | null {
    if (cents == null) {
        return null;
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);
}

function getAppointmentSaleLink(
    appointment: CalendarAppointment,
): CalendarAppointmentSaleLink | null {
    return (
        appointment.sale_link ??
        appointment.sale_links?.find((link) => link.sale) ??
        null
    );
}

function wasSaleCreatedAutomatically(
    link: CalendarAppointmentSaleLink | null,
): boolean {
    const sale = link?.sale;

    return [
        link?.automatic,
        link?.created_automatically,
        sale?.automatic,
        sale?.created_automatically,
        sale?.source === 'appointment',
        sale?.source === 'agenda',
        sale?.origin === 'appointment',
        sale?.origin === 'agenda',
    ].some((value) => value === true);
}

function optionList(
    primary: CalendarOption[] | undefined,
    fallback: CalendarOption[] | undefined,
): CalendarOption[] {
    return primary ?? fallback ?? [];
}

type GoogleCalendarStatus = {
    status: 'connected' | 'disconnected' | 'not_configured';
    configured: boolean;
    connection: {
        google_account_email?: string | null;
        calendar_name?: string | null;
        last_synced_at?: string | null;
    } | null;
};

function GoogleCalendarPanel(): ReactElement {
    const [state, setState] = useState<GoogleCalendarStatus | null>(null);
    const [loading, setLoading] = useState(true);
    const [disconnecting, setDisconnecting] = useState(false);

    const loadStatus = async (): Promise<void> => {
        setLoading(true);

        try {
            const response = await fetch(googleCalendar.status.url(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Unable to load Google Calendar status.');
            }

            setState((await response.json()) as GoogleCalendarStatus);
        } catch {
            setState(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            void loadStatus();
        }, 0);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, []);

    const connected = state?.status === 'connected';
    const unavailable = state?.status === 'not_configured';

    return (
        <section
            className="rounded-2xl border border-border bg-card/80 p-4 shadow-xs sm:p-5"
            aria-labelledby="google-calendar-heading"
        >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <CalendarCheck2 aria-hidden="true" className="size-5" />
                    </div>
                    <div>
                        <h2
                            id="google-calendar-heading"
                            className="font-semibold"
                        >
                            Google Calendar
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {loading
                                ? 'Verificando conexão…'
                                : unavailable
                                  ? 'Integração indisponível neste ambiente.'
                                  : connected
                                    ? `Conectado${state?.connection?.google_account_email ? ` como ${state.connection.google_account_email}` : ''}.`
                                    : 'Conecte o calendário desta unidade para sincronizar agendamentos.'}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Atualizar status do Google Calendar"
                        onClick={() => void loadStatus()}
                        disabled={loading || disconnecting}
                    >
                        <RefreshCw
                            aria-hidden="true"
                            className={loading ? 'animate-spin' : undefined}
                        />
                    </Button>
                    {connected ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={disconnecting}
                            onClick={() => {
                                setDisconnecting(true);
                                router.delete(googleCalendar.disconnect.url(), {
                                    preserveScroll: true,
                                    onFinish: () => {
                                        setDisconnecting(false);
                                        void loadStatus();
                                    },
                                });
                            }}
                        >
                            {disconnecting ? 'Desconectando…' : 'Desconectar'}
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            disabled={loading || unavailable}
                            onClick={() => {
                                window.location.assign(
                                    googleCalendar.connect.url(),
                                );
                            }}
                        >
                            Conectar Google Calendar
                        </Button>
                    )}
                </div>
            </div>
        </section>
    );
}

function AppointmentForm({
    appointment,
    customers: initialCustomers,
    defaultDurationMinutes,
    defaultProfessionalId = '',
    defaultStartsAt = '',
    existingAppointments = [],
    existingScheduleBlocks = [],
    onClose,
    onTriggerCancel,
    professionals: initialProfessionals,
    services: initialServices,
    unitTimezone,
}: {
    appointment: CalendarAppointment | null;
    customers: CalendarOption[];
    defaultDurationMinutes?: number;
    defaultProfessionalId?: string;
    defaultStartsAt?: string;
    existingAppointments?: CalendarAppointment[];
    existingScheduleBlocks?: ScheduleBlock[];
    onClose: () => void;
    onTriggerCancel?: () => void;
    professionals: CalendarOption[];
    services: CalendarOption[];
    unitTimezone: string;
}) {
    const [mutationKey] = useState(() =>
        createIdempotencyKey(
            appointment
                ? `appointment-update:${appointment.id}`
                : 'appointment-create',
        ),
    );

    const [customerList, setCustomerList] =
        useState<CalendarOption[]>(initialCustomers);
    const [serviceList, setServiceList] =
        useState<CalendarOption[]>(initialServices);
    const [professionalList, setProfessionalList] =
        useState<CalendarOption[]>(initialProfessionals);

    const [selectedCustomer, setSelectedCustomer] = useState(
        appointment?.customer_id ?? appointment?.customer?.id ?? '',
    );
    const [selectedService, setSelectedService] = useState(
        appointment?.service_id ??
            appointment?.service?.id ??
            appointment?.items?.[0]?.service_id ??
            '',
    );
    const [selectedProfessional, setSelectedProfessional] = useState(
        appointment?.professional_id ??
            appointment?.professional?.id ??
            defaultProfessionalId,
    );
    const [selectedStartsAt, setSelectedStartsAt] = useState(
        appointment
            ? dateTimeValue(appointment.starts_at, unitTimezone)
            : defaultStartsAt,
    );
    const [selectedDuration, setSelectedDuration] = useState<number>(
        appointment?.duration_minutes ??
            appointment?.items?.[0]?.duration_minutes ??
            defaultDurationMinutes ??
            30,
    );
    const [selectedStatus, setSelectedStatus] = useState<AppointmentStatus>(
        appointment?.status ?? 'scheduled',
    );
    const [notes, setNotes] = useState(appointment?.notes ?? '');
    const [reminderEnabled, setReminderEnabled] = useState(
        appointment?.reminder_enabled ?? true,
    );

    const [quickCustomerOpen, setQuickCustomerOpen] = useState(false);
    const [quickServiceOpen, setQuickServiceOpen] = useState(false);
    const [quickProfessionalOpen, setQuickProfessionalOpen] = useState(false);

    const isEditing = appointment !== null;
    const isCancelled = isEditing && appointment?.status === 'cancelled';
    const isCompleted = isEditing && appointment?.status === 'completed';
    const isReadOnlyStatus =
        isEditing &&
        ['completed', 'no_show', 'cancelled'].includes(
            appointment?.status ?? '',
        );

    const availableStatuses = useMemo(() => {
        if (!isEditing || !appointment) {
            return CREATABLE_STATUSES;
        }

        return STATUS_TRANSITIONS[appointment.status] ?? [appointment.status];
    }, [isEditing, appointment]);

    const estimatedEndTime = useMemo(() => {
        if (!selectedStartsAt || !selectedDuration) {
            return null;
        }

        const start = new Date(selectedStartsAt);

        if (Number.isNaN(start.getTime())) {
            return null;
        }

        const end = new Date(
            start.getTime() + Number(selectedDuration) * 60 * 1000,
        );
        const hours = String(end.getHours()).padStart(2, '0');
        const minutes = String(end.getMinutes()).padStart(2, '0');

        return `${hours}:${minutes}`;
    }, [selectedStartsAt, selectedDuration]);

    const handleStatusChange = (newStatus: AppointmentStatus) => {
        if (newStatus === 'cancelled') {
            if (onTriggerCancel) {
                onTriggerCancel();
            }

            return;
        }

        setSelectedStatus(newStatus);
    };

    const handleCustomerCreated = (created: CreatedEntity) => {
        const newOpt: CalendarOption = { id: created.id, name: created.name };
        setCustomerList((prev) => [
            ...prev.filter((c) => c.id !== created.id),
            newOpt,
        ]);
        setSelectedCustomer(created.id);
    };

    const handleServiceCreated = (created: CreatedEntity) => {
        const newOpt: CalendarOption = { id: created.id, name: created.name };
        setServiceList((prev) => [
            ...prev.filter((s) => s.id !== created.id),
            newOpt,
        ]);
        setSelectedService(created.id);

        if (created.duration_minutes) {
            setSelectedDuration(created.duration_minutes);
        }
    };

    const handleServiceChange = (serviceId: string) => {
        setSelectedService(serviceId);

        const selectedServiceOption = serviceList.find(
            (service) => service.id === serviceId,
        );

        if (selectedServiceOption?.duration_minutes) {
            setSelectedDuration(selectedServiceOption.duration_minutes);
        }
    };

    const handleProfessionalCreated = (created: CreatedEntity) => {
        const newOpt: CalendarOption = { id: created.id, name: created.name };
        setProfessionalList((prev) => [
            ...prev.filter((p) => p.id !== created.id),
            newOpt,
        ]);
        setSelectedProfessional(created.id);
    };

    const conflict = useMemo(() => {
        if (
            isCancelled ||
            !selectedProfessional ||
            !selectedStartsAt ||
            !selectedDuration
        ) {
            return null;
        }

        const start = new Date(selectedStartsAt);

        if (Number.isNaN(start.getTime())) {
            return null;
        }

        const durationMin = Number(selectedDuration) || 30;
        const end = new Date(start.getTime() + durationMin * 60 * 1000);

        // 1. Verificar conflitos com outros agendamentos do profissional
        if (existingAppointments && existingAppointments.length > 0) {
            const aptConflict = existingAppointments.find((apt) => {
                if (appointment && apt.id === appointment.id) {
                    return false;
                }

                if (apt.status === 'cancelled') {
                    return false;
                }

                const profId = apt.professional_id ?? apt.professional?.id;

                if (profId !== selectedProfessional) {
                    return false;
                }

                const aStart = new Date(apt.starts_at);
                const aEnd = new Date(apt.ends_at);

                return start < aEnd && end > aStart;
            });

            if (aptConflict) {
                const customerName =
                    aptConflict.customer?.name || 'Outro cliente';

                return {
                    type: 'appointment',
                    description: `Conflito com agendamento de ${customerName} (${formatTime(aptConflict.starts_at, unitTimezone)} - ${formatTime(aptConflict.ends_at, unitTimezone)}).`,
                };
            }
        }

        // 2. Verificar conflitos com bloqueios de agenda
        if (existingScheduleBlocks && existingScheduleBlocks.length > 0) {
            const blockConflict = existingScheduleBlocks.find((block) => {
                if (
                    block.professional_id &&
                    block.professional_id !== selectedProfessional
                ) {
                    return false;
                }

                const bStart = new Date(block.starts_at);
                const bEnd = new Date(block.ends_at);

                return start < bEnd && end > bStart;
            });

            if (blockConflict) {
                return {
                    type: 'block',
                    description: `Conflito com bloqueio: ${blockConflict.reason || 'Horário reservado'} (${formatTime(blockConflict.starts_at, unitTimezone)} - ${formatTime(blockConflict.ends_at, unitTimezone)}).`,
                };
            }
        }

        return null;
    }, [
        isCancelled,
        selectedProfessional,
        selectedStartsAt,
        selectedDuration,
        existingAppointments,
        existingScheduleBlocks,
        appointment,
        unitTimezone,
    ]);

    const route = isEditing
        ? updateAppointment.form(appointment.id)
        : storeAppointment.form();

    return (
        <>
            <Form
                {...route}
                headers={{ 'X-Idempotency-Key': mutationKey }}
                className="space-y-6"
                onSuccess={onClose}
            >
                {({ errors, processing, submit }) => {
                    const automationIssue = automationIssueFromErrors(errors);

                    return (
                        <>
                            <FormErrorSummary errors={errors} />

                            {automationIssue ? (
                                <AutomationFeedback
                                    kind={automationIssue}
                                    onAction={
                                        automationIssue === 'creation'
                                            ? submit
                                            : undefined
                                    }
                                    onReload={
                                        automationIssue === 'stale' ||
                                        automationIssue === 'conflict' ||
                                        automationIssue === 'sync'
                                            ? () =>
                                                  router.reload({
                                                      only: [
                                                          'appointments',
                                                          'scheduleBlocks',
                                                          'options',
                                                      ],
                                                  })
                                            : undefined
                                    }
                                />
                            ) : null}

                            {/* Bloqueio amigável para cancelado */}
                            {isCancelled && (
                                <div className="flex items-start gap-3 rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-destructive dark:border-destructive/40 dark:bg-destructive/15">
                                    <XCircle className="mt-0.5 size-5 shrink-0" />
                                    <div className="space-y-1 text-sm">
                                        <p className="font-semibold text-destructive">
                                            Este agendamento está cancelado e
                                            não pode ser editado.
                                        </p>
                                        {appointment?.cancel_reason && (
                                            <p className="text-xs text-muted-foreground">
                                                <strong className="font-medium text-foreground">
                                                    Motivo registrado:
                                                </strong>{' '}
                                                {appointment.cancel_reason}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Bloqueio amigável para concluído */}
                            {isCompleted && (
                                <div className="flex items-start gap-3 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                                    <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                    <div className="space-y-1 text-sm">
                                        <p className="font-semibold">
                                            Este agendamento já foi concluído e
                                            seu histórico está registrado.
                                        </p>
                                        <p className="text-xs text-emerald-800/80 dark:text-emerald-300/80">
                                            Os dados operacionais estão
                                            bloqueados; apenas o campo de
                                            observações pode ser atualizado.
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* Aviso amigável de conflito de horário */}
                            {!isCancelled && conflict && (
                                <div className="flex flex-col gap-1.5 rounded-lg border border-amber-300 bg-amber-50 p-3.5 text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <AlertTriangle className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                        <Badge
                                            variant="outline"
                                            className="border-amber-400 bg-amber-100 font-semibold text-amber-900 dark:border-amber-600 dark:bg-amber-900/60 dark:text-amber-200"
                                        >
                                            Atenção: Horário coincide com outro
                                            agendamento/bloqueio
                                        </Badge>
                                    </div>
                                    <p className="text-xs text-amber-800 dark:text-amber-300">
                                        {conflict.description}
                                    </p>
                                </div>
                            )}

                            {/* Hidden inputs para preservar integridade quando campos estiverem desabilitados */}
                            {(isCompleted || isCancelled) && (
                                <>
                                    <input
                                        type="hidden"
                                        name="customer_id"
                                        value={selectedCustomer}
                                    />
                                    <input
                                        type="hidden"
                                        name="service_id"
                                        value={selectedService}
                                    />
                                    <input
                                        type="hidden"
                                        name="professional_id"
                                        value={selectedProfessional}
                                    />
                                    <input
                                        type="hidden"
                                        name="starts_at"
                                        value={selectedStartsAt}
                                    />
                                    <input
                                        type="hidden"
                                        name="duration_minutes"
                                        value={selectedDuration}
                                    />
                                </>
                            )}
                            {(isCompleted ||
                                isCancelled ||
                                isReadOnlyStatus) && (
                                <input
                                    type="hidden"
                                    name="status"
                                    value={selectedStatus}
                                />
                            )}

                            <div className="grid min-w-0 gap-5 md:grid-cols-2">
                                <div className="min-w-0 md:col-span-2">
                                    <FormField
                                        label="Cliente"
                                        name="customer_id"
                                        error={errors.customer_id}
                                        action={
                                            !isCancelled && !isCompleted ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setQuickCustomerOpen(
                                                            true,
                                                        )
                                                    }
                                                    className="text-xs font-semibold text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                                                >
                                                    + Novo Cliente
                                                </button>
                                            ) : undefined
                                        }
                                    >
                                        <select
                                            id="customer_id"
                                            name="customer_id"
                                            value={selectedCustomer}
                                            onChange={(e) =>
                                                setSelectedCustomer(
                                                    e.target.value,
                                                )
                                            }
                                            disabled={
                                                isCancelled || isCompleted
                                            }
                                            required
                                            className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-70"
                                        >
                                            <option value="">
                                                Selecione um cliente
                                            </option>
                                            {customerList.map((customer) => (
                                                <option
                                                    key={customer.id}
                                                    value={customer.id}
                                                >
                                                    {customer.name}
                                                </option>
                                            ))}
                                        </select>
                                    </FormField>
                                </div>
                                <FormField
                                    label="Serviço"
                                    name="service_id"
                                    error={errors.service_id}
                                    action={
                                        !isCancelled && !isCompleted ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuickServiceOpen(true)
                                                }
                                                className="text-xs font-semibold text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                                            >
                                                + Novo Serviço
                                            </button>
                                        ) : undefined
                                    }
                                >
                                    <select
                                        id="service_id"
                                        name="service_id"
                                        value={selectedService}
                                        onChange={(e) =>
                                            handleServiceChange(e.target.value)
                                        }
                                        disabled={isCancelled || isCompleted}
                                        required
                                        className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-70"
                                    >
                                        <option value="">
                                            Selecione um serviço
                                        </option>
                                        {serviceList.map((service) => (
                                            <option
                                                key={service.id}
                                                value={service.id}
                                            >
                                                {service.name}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                <FormField
                                    label="Profissional"
                                    name="professional_id"
                                    error={errors.professional_id}
                                    action={
                                        !isCancelled && !isCompleted ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setQuickProfessionalOpen(
                                                        true,
                                                    )
                                                }
                                                className="text-xs font-semibold text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                                            >
                                                + Novo Profissional
                                            </button>
                                        ) : undefined
                                    }
                                >
                                    <select
                                        id="professional_id"
                                        name="professional_id"
                                        value={selectedProfessional}
                                        onChange={(e) =>
                                            setSelectedProfessional(
                                                e.target.value,
                                            )
                                        }
                                        disabled={isCancelled || isCompleted}
                                        required
                                        className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-70"
                                    >
                                        <option value="">
                                            Selecione um profissional
                                        </option>
                                        {professionalList.map(
                                            (professional) => (
                                                <option
                                                    key={professional.id}
                                                    value={professional.id}
                                                >
                                                    {professional.name}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </FormField>
                                <FormField
                                    label="Data e horário"
                                    name="starts_at"
                                    error={errors.starts_at}
                                >
                                    <Input
                                        id="starts_at"
                                        name="starts_at"
                                        type="datetime-local"
                                        value={selectedStartsAt}
                                        onChange={(e) =>
                                            setSelectedStartsAt(e.target.value)
                                        }
                                        disabled={isCancelled || isCompleted}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Duração"
                                    name="duration_minutes"
                                    error={errors.duration_minutes}
                                >
                                    <Input
                                        id="duration_minutes"
                                        name="duration_minutes"
                                        type="number"
                                        min={5}
                                        max={1440}
                                        step={5}
                                        value={selectedDuration}
                                        onChange={(e) =>
                                            setSelectedDuration(
                                                Number(e.target.value),
                                            )
                                        }
                                        disabled={isCancelled || isCompleted}
                                        required
                                    />
                                    {estimatedEndTime && (
                                        <p className="mt-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <Clock className="size-3.5 shrink-0 text-primary" />
                                            <span>
                                                Término previsto:{' '}
                                                <strong className="font-semibold text-foreground">
                                                    {estimatedEndTime}
                                                </strong>
                                            </span>
                                        </p>
                                    )}
                                </FormField>
                                <FormField
                                    label="Status"
                                    name="status"
                                    error={errors.status}
                                >
                                    <select
                                        id="status"
                                        name="status"
                                        value={selectedStatus}
                                        onChange={(e) =>
                                            handleStatusChange(
                                                e.target
                                                    .value as AppointmentStatus,
                                            )
                                        }
                                        disabled={
                                            isCancelled ||
                                            isCompleted ||
                                            isReadOnlyStatus
                                        }
                                        className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-70"
                                    >
                                        {availableStatuses.map((status) => (
                                            <option key={status} value={status}>
                                                {statusLabel(status)}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                                <div className="flex items-start gap-3 rounded-xl border border-border bg-muted/20 px-3.5 py-3 md:col-span-2">
                                    <input
                                        type="hidden"
                                        name="reminder_enabled"
                                        value={reminderEnabled ? '1' : '0'}
                                    />
                                    <input
                                        id="reminder_enabled"
                                        type="checkbox"
                                        checked={reminderEnabled}
                                        onChange={(e) =>
                                            setReminderEnabled(e.target.checked)
                                        }
                                        disabled={isCancelled || isCompleted}
                                        className="size-4 accent-primary disabled:opacity-70"
                                    />
                                    <label
                                        htmlFor="reminder_enabled"
                                        className="text-sm"
                                    >
                                        Enviar lembrete ao cliente
                                        <span className="block text-xs text-muted-foreground">
                                            O canal e o consentimento são
                                            validados pelo servidor.
                                        </span>
                                    </label>
                                </div>
                                <div className="min-w-0 md:col-span-2">
                                    <FormField
                                        label="Observações"
                                        name="notes"
                                        error={errors.notes}
                                    >
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows={3}
                                            value={notes}
                                            onChange={(e) =>
                                                setNotes(e.target.value)
                                            }
                                            disabled={isCancelled}
                                            className="min-h-24 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-70"
                                            placeholder="Informações úteis para o atendimento"
                                        />
                                    </FormField>
                                </div>
                            </div>
                            {isEditing ? (
                                <input
                                    type="hidden"
                                    name="lock_version"
                                    value={appointment.lock_version}
                                />
                            ) : null}

                            {isCancelled ? (
                                <div className="flex justify-end border-t border-border pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={onClose}
                                    >
                                        Fechar
                                    </Button>
                                </div>
                            ) : (
                                <FormActions
                                    processing={processing}
                                    onCancel={onClose}
                                    cancelLabel="Cancelar"
                                    label={
                                        isEditing
                                            ? isCompleted
                                                ? 'Salvar observações'
                                                : 'Salvar alterações'
                                            : 'Criar agendamento'
                                    }
                                />
                            )}
                        </>
                    );
                }}
            </Form>

            <QuickCreateCustomerModal
                open={quickCustomerOpen}
                onOpenChange={setQuickCustomerOpen}
                onSuccess={handleCustomerCreated}
            />

            <QuickCreateServiceModal
                open={quickServiceOpen}
                onOpenChange={setQuickServiceOpen}
                onSuccess={handleServiceCreated}
            />

            <QuickCreateProfessionalModal
                open={quickProfessionalOpen}
                onOpenChange={setQuickProfessionalOpen}
                onSuccess={handleProfessionalCreated}
            />
        </>
    );
}

function CancelAppointmentForm({
    appointment,
    onClose,
}: {
    appointment: CalendarAppointment;
    onClose: () => void;
}) {
    const [mutationKey] = useState(() =>
        createIdempotencyKey(`appointment-cancel:${appointment.id}`),
    );

    return (
        <Form
            {...cancelAppointment.form(appointment.id)}
            headers={{ 'X-Idempotency-Key': mutationKey }}
            className="space-y-3"
            onSubmit={(event) => {
                if (
                    !window.confirm(
                        'Tem certeza de que deseja cancelar este agendamento?',
                    )
                ) {
                    event.preventDefault();
                }
            }}
            onSuccess={onClose}
        >
            {({ errors, processing }) => (
                <>
                    {automationIssueFromErrors(errors) ? (
                        <AutomationFeedback
                            kind={
                                automationIssueFromErrors(errors) ?? 'creation'
                            }
                            onReload={
                                automationIssueFromErrors(errors) === 'stale'
                                    ? () => router.reload()
                                    : undefined
                            }
                        />
                    ) : null}
                    <input
                        type="hidden"
                        name="lock_version"
                        value={appointment.lock_version}
                    />
                    <FormField
                        label="Motivo do cancelamento"
                        name="cancel_reason"
                        error={errors.cancel_reason}
                    >
                        <Input
                            id="cancel_reason"
                            name="cancel_reason"
                            placeholder="Informe o motivo do cancelamento"
                            required
                            autoFocus
                        />
                    </FormField>
                    <Button
                        type="submit"
                        variant="destructive"
                        size="sm"
                        disabled={processing}
                        className="w-full"
                    >
                        {processing ? 'Cancelando…' : 'Confirmar cancelamento'}
                    </Button>
                </>
            )}
        </Form>
    );
}

function CheckInAppointmentButton({
    appointment,
    onSuccess,
}: {
    appointment: CalendarAppointment;
    onSuccess: () => void;
}) {
    const [mutationKey] = useState(() =>
        createIdempotencyKey(`appointment-checkin:${appointment.id}`),
    );

    return (
        <Form
            {...checkInAppointment.form(appointment.id)}
            headers={{ 'X-Idempotency-Key': mutationKey }}
            onSuccess={onSuccess}
        >
            {({ processing }) => (
                <>
                    <input
                        type="hidden"
                        name="lock_version"
                        value={appointment.lock_version}
                    />
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing}
                        className="bg-emerald-600 font-medium text-white shadow-xs hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                    >
                        <CheckCircle2
                            className="mr-1.5 size-4"
                            aria-hidden="true"
                        />
                        {processing ? 'Registrando…' : 'Marcar Presença'}
                    </Button>
                </>
            )}
        </Form>
    );
}

function AppointmentSummaryHeader({
    appointment,
    cancelSectionOpen,
    canManage,
    canManageSales,
    customers,
    onClose,
    onOpenSaleDialog,
    onToggleCancel,
    services,
    unitTimezone,
}: {
    appointment: CalendarAppointment;
    cancelSectionOpen: boolean;
    canManage: boolean;
    canManageSales: boolean;
    customers: CalendarOption[];
    onClose: () => void;
    onOpenSaleDialog: () => void;
    onToggleCancel: () => void;
    services: CalendarOption[];
    unitTimezone: string;
}) {
    const customerObj = useMemo(() => {
        const custId = appointment.customer_id ?? appointment.customer?.id;

        return customers.find((c) => c.id === custId) ?? appointment.customer;
    }, [customers, appointment]);

    const customerPhone = customerObj?.phone ?? appointment.customer?.phone;
    const waUrl = useMemo(() => getWhatsAppUrl(customerPhone), [customerPhone]);

    const serviceObj = useMemo(() => {
        const serviceId =
            appointment.service_id ??
            appointment.service?.id ??
            appointment.items?.[0]?.service_id;

        return (
            services.find((s) => s.id === serviceId) ??
            appointment.service ??
            appointment.items?.[0]?.service
        );
    }, [services, appointment]);

    const priceCents =
        serviceObj?.price_cents ?? appointment.items?.[0]?.price_cents;
    const formattedPrice = useMemo(
        () => formatPriceCents(priceCents),
        [priceCents],
    );

    const formattedDateTime = useMemo(
        () =>
            formatAppointmentHeaderDate(
                appointment.starts_at,
                appointment.ends_at,
                appointment.duration_minutes ??
                    appointment.items?.[0]?.duration_minutes,
                unitTimezone,
            ),
        [appointment, unitTimezone],
    );
    const appointmentSaleLink = getAppointmentSaleLink(appointment);
    const linkedSale = appointmentSaleLink?.sale;
    const isAutomaticSale = wasSaleCreatedAutomatically(appointmentSaleLink);

    return (
        <div className="space-y-3 rounded-xl border border-border/80 bg-muted/30 p-4 shadow-2xs">
            {/* Resumo do Cliente e Status */}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary">
                        {(customerObj?.name || 'C').charAt(0).toUpperCase()}
                    </div>
                    <div className="min-w-0">
                        <h3 className="truncate text-base leading-tight font-semibold text-foreground">
                            {customerObj?.name || 'Cliente não identificado'}
                        </h3>
                        {customerPhone ? (
                            <div className="mt-0.5 flex flex-wrap items-center gap-2 text-xs">
                                <span className="flex items-center gap-1 text-muted-foreground">
                                    <Phone className="size-3 text-muted-foreground/70" />
                                    {customerPhone}
                                </span>
                                {waUrl && (
                                    <a
                                        href={waUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 hover:bg-emerald-100 hover:underline dark:bg-emerald-950/50 dark:text-emerald-300 dark:hover:bg-emerald-900/50"
                                        title="Abrir WhatsApp com o cliente"
                                    >
                                        <MessageCircle className="size-3 text-emerald-600 dark:text-emerald-400" />
                                        <span>WhatsApp</span>
                                    </a>
                                )}
                            </div>
                        ) : (
                            <span className="text-xs text-muted-foreground">
                                Sem telefone cadastrado
                            </span>
                        )}
                    </div>
                </div>
                <div className="shrink-0">
                    <StatusChip status={appointment.status} />
                </div>
            </div>

            {isAutomaticSale ? (
                <>
                    <div className="flex items-start gap-2.5 rounded-lg border border-primary/25 bg-primary/5 px-3 py-2.5 text-xs text-muted-foreground">
                        <Sparkles
                            aria-hidden="true"
                            className="mt-0.5 size-4 shrink-0 text-primary"
                        />
                        <p>
                            <strong className="font-semibold text-foreground">
                                Comanda criada automaticamente pela Agenda.
                            </strong>{' '}
                            O serviço deste agendamento já foi adicionado e as
                            alterações serão sincronizadas enquanto a comanda
                            permanecer aberta.
                        </p>
                    </div>
                    {linkedSale && linkedSale.status !== 'open' ? (
                        <AutomationFeedback
                            kind={
                                ['cancelled', 'closed', 'finalized'].includes(
                                    linkedSale.status,
                                )
                                    ? 'sync'
                                    : 'blocked'
                            }
                            actionLabel="Abrir comanda para revisar"
                            onAction={() =>
                                router.visit(sales.show(linkedSale.id))
                            }
                        >
                            {linkedSale.status === 'cancelled'
                                ? 'A comanda foi cancelada e não receberá alterações deste agendamento.'
                                : 'A comanda não está aberta; alterações futuras do agendamento não serão aplicadas nela.'}
                        </AutomationFeedback>
                    ) : null}
                </>
            ) : null}

            {/* Serviço & Valor + Data & Horário */}
            <div className="grid grid-cols-1 gap-2 border-t border-border/60 pt-2.5 text-xs sm:grid-cols-2">
                <div className="flex min-w-0 items-center gap-2">
                    <span className="truncate font-semibold text-foreground">
                        {serviceObj?.name || 'Serviço'}
                    </span>
                    {formattedPrice && (
                        <Badge
                            variant="secondary"
                            className="shrink-0 border border-emerald-200 bg-emerald-50 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                        >
                            {formattedPrice}
                        </Badge>
                    )}
                </div>
                <div className="flex items-center gap-1.5 text-muted-foreground sm:justify-end">
                    <Calendar className="size-3.5 shrink-0 text-muted-foreground" />
                    <span className="font-medium text-foreground">
                        {formattedDateTime}
                    </span>
                </div>
            </div>

            {/* Barra de Ações Rápidas */}
            <div className="flex flex-wrap items-center gap-2 border-t border-border/60 pt-2.5">
                {/* Marcar Presença */}
                {canManage &&
                    ['scheduled', 'confirmed'].includes(appointment.status) && (
                        <CheckInAppointmentButton
                            appointment={appointment}
                            onSuccess={onClose}
                        />
                    )}

                {/* Comanda & Faturamento */}
                {linkedSale ? (
                    <div className="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-background/80 px-2.5 py-1 text-xs shadow-2xs">
                        {isAutomaticSale ? (
                            <Sparkles
                                aria-hidden="true"
                                className="size-3.5 shrink-0 text-primary"
                            />
                        ) : (
                            <Receipt className="size-3.5 shrink-0 text-primary" />
                        )}
                        <span className="max-w-[140px] truncate font-medium">
                            {linkedSale.reference_label || 'Comanda'}
                        </span>
                        {isAutomaticSale ? (
                            <Badge
                                variant="secondary"
                                className="border-primary/20 bg-primary/10 px-1.5 py-0 text-2xs font-semibold text-primary"
                            >
                                Automática
                            </Badge>
                        ) : null}
                        <Badge
                            variant="outline"
                            className="px-1.5 py-0 text-2xs capitalize"
                        >
                            {linkedSale.status}
                        </Badge>
                        <Button
                            asChild
                            size="sm"
                            variant="ghost"
                            className="h-6 px-1.5 text-xs font-medium text-primary hover:text-primary/80"
                        >
                            <Link href={sales.show(linkedSale.id)}>
                                Ver Comanda →
                            </Link>
                        </Button>
                    </div>
                ) : canManageSales && appointment.status !== 'cancelled' ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="gap-1.5"
                        onClick={onOpenSaleDialog}
                    >
                        <Receipt className="size-3.5 text-primary" />
                        Abrir Comanda
                    </Button>
                ) : null}

                {/* Cancelar Agendamento */}
                {canManage && appointment.status !== 'cancelled' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className={cn(
                            'gap-1.5 transition-colors sm:ml-auto',
                            cancelSectionOpen
                                ? 'border-destructive/40 bg-destructive/10 text-destructive'
                                : 'text-muted-foreground hover:border-destructive/40 hover:text-destructive',
                        )}
                        onClick={onToggleCancel}
                    >
                        <Ban className="size-3.5" />
                        {cancelSectionOpen
                            ? 'Fechar cancelamento'
                            : 'Cancelar agendamento'}
                    </Button>
                )}
            </div>

            {/* Seção retrátil de cancelamento */}
            {cancelSectionOpen && (
                <div className="mt-2 space-y-2.5 rounded-lg border border-destructive/30 bg-destructive/5 p-3.5">
                    <div className="flex items-center justify-between">
                        <p className="flex items-center gap-1.5 text-xs font-semibold text-destructive">
                            <AlertTriangle className="size-3.5" />
                            Confirmar cancelamento do agendamento
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-6 px-2 text-xs text-muted-foreground hover:text-foreground"
                            onClick={onToggleCancel}
                        >
                            Fechar
                        </Button>
                    </div>
                    <CancelAppointmentForm
                        appointment={appointment}
                        onClose={onClose}
                    />
                </div>
            )}
        </div>
    );
}

function OpenAppointmentSaleForm({
    appointment,
    categories,
    onClose,
}: {
    appointment: CalendarAppointment;
    categories: SaleCategoryOptionSummary[];
    onClose: () => void;
}) {
    const [mutationKey] = useState(() =>
        createIdempotencyKey(`appointment-sale-open:${appointment.id}`),
    );

    return (
        <Form
            {...sales.store.form()}
            headers={{ 'X-Idempotency-Key': mutationKey }}
            className="space-y-4"
            onSuccess={onClose}
        >
            {({ errors, processing, submit }) => (
                <>
                    <FormErrorSummary errors={errors} />
                    {automationIssueFromErrors(errors) ? (
                        <AutomationFeedback
                            kind={
                                automationIssueFromErrors(errors) ?? 'creation'
                            }
                            onAction={
                                automationIssueFromErrors(errors) === 'stale'
                                    ? undefined
                                    : submit
                            }
                            onReload={
                                automationIssueFromErrors(errors) === 'stale'
                                    ? () => router.reload()
                                    : undefined
                            }
                        />
                    ) : null}
                    <input
                        type="hidden"
                        name="appointment_id"
                        value={appointment.id}
                    />
                    <input
                        type="hidden"
                        name="customer_id"
                        value={
                            appointment.customer_id ??
                            appointment.customer?.id ??
                            ''
                        }
                    />
                    <FormField
                        label="Categoria da Comanda"
                        name="sale_category_id"
                        error={errors.sale_category_id}
                    >
                        <select
                            id="appointment_sale_category_id"
                            name="sale_category_id"
                            required
                            className="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/50"
                        >
                            <option value="">Selecione a categoria</option>
                            {categories.map((cat) => (
                                <option key={cat.id} value={cat.id}>
                                    {cat.name}
                                </option>
                            ))}
                        </select>
                    </FormField>

                    <FormField
                        label="Identificador / Mesa / Referência (opcional)"
                        name="reference_label"
                        error={errors.reference_label}
                    >
                        <Input
                            id="appointment_reference_label"
                            name="reference_label"
                            placeholder="Ex.: Mesa 01, Cadeira 02, etc."
                        />
                    </FormField>

                    <FormActions
                        processing={processing}
                        onCancel={onClose}
                        label="Abrir Comanda e Ir ao Detalhe"
                    />
                </>
            )}
        </Form>
    );
}

function ScheduleBlockForm({
    defaultDate,
    defaultEndsAt: prefilledEndsAt,
    defaultProfessionalId = '',
    defaultStartsAt: prefilledStartsAt,
    onClose,
    professionals: initialProfessionals,
    unitTimezone,
}: {
    defaultDate: string;
    defaultEndsAt?: string;
    defaultProfessionalId?: string;
    defaultStartsAt?: string;
    onClose: () => void;
    professionals: CalendarOption[];
    unitTimezone: string;
}) {
    const [mutationKey] = useState(() =>
        createIdempotencyKey('schedule-block-create'),
    );

    const [professionalList, setProfessionalList] =
        useState<CalendarOption[]>(initialProfessionals);
    const [selectedProfessional, setSelectedProfessional] = useState(
        defaultProfessionalId,
    );
    const [quickProfessionalOpen, setQuickProfessionalOpen] = useState(false);

    const handleProfessionalCreated = (created: CreatedEntity) => {
        const newOpt: CalendarOption = { id: created.id, name: created.name };
        setProfessionalList((prev) => [
            ...prev.filter((p) => p.id !== created.id),
            newOpt,
        ]);
        setSelectedProfessional(created.id);
    };

    const defaultStartsAt = prefilledStartsAt || `${defaultDate}T09:00`;
    const defaultEndsAt = prefilledEndsAt || `${defaultDate}T10:00`;

    return (
        <>
            <Form
                {...storeScheduleBlock.form()}
                headers={{ 'X-Idempotency-Key': mutationKey }}
                className="space-y-4"
                onSuccess={onClose}
            >
                {({ errors, processing }) => (
                    <>
                        <FormErrorSummary errors={errors} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <FormField
                                    label="Profissional"
                                    name="professional_id"
                                    error={errors.professional_id}
                                    action={
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setQuickProfessionalOpen(true)
                                            }
                                            className="rounded-xs text-xs font-semibold text-primary hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                        >
                                            + Novo Profissional
                                        </button>
                                    }
                                >
                                    <select
                                        id="professional_id"
                                        name="professional_id"
                                        value={selectedProfessional}
                                        onChange={(e) =>
                                            setSelectedProfessional(
                                                e.target.value,
                                            )
                                        }
                                        className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="">
                                            Toda a unidade (Bloqueio geral)
                                        </option>
                                        {professionalList.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>
                            </div>
                            <FormField
                                label="Início do bloqueio"
                                name="starts_at"
                                error={errors.starts_at}
                            >
                                <Input
                                    id="starts_at"
                                    name="starts_at"
                                    type="datetime-local"
                                    defaultValue={defaultStartsAt}
                                    required
                                />
                            </FormField>
                            <FormField
                                label="Fim do bloqueio"
                                name="ends_at"
                                error={errors.ends_at}
                            >
                                <Input
                                    id="ends_at"
                                    name="ends_at"
                                    type="datetime-local"
                                    defaultValue={defaultEndsAt}
                                    required
                                />
                            </FormField>
                            <div className="sm:col-span-2">
                                <FormField
                                    label="Motivo / Justificativa"
                                    name="reason"
                                    error={errors.reason}
                                >
                                    <Input
                                        id="reason"
                                        name="reason"
                                        placeholder="Ex.: Intervalo, Almoço, Consulta médica, Manutenção"
                                        required
                                    />
                                </FormField>
                            </div>
                            <input
                                type="hidden"
                                name="timezone"
                                value={unitTimezone}
                            />
                        </div>
                        <FormActions
                            processing={processing}
                            onCancel={onClose}
                            label="Criar bloqueio"
                        />
                    </>
                )}
            </Form>

            <QuickCreateProfessionalModal
                open={quickProfessionalOpen}
                onOpenChange={setQuickProfessionalOpen}
                onSuccess={handleProfessionalCreated}
            />
        </>
    );
}

function DeleteScheduleBlockForm({
    block,
    onClose,
}: {
    block: ScheduleBlock;
    onClose: () => void;
}) {
    const [mutationKey] = useState(() =>
        createIdempotencyKey(`schedule-block-delete:${block.id}`),
    );

    return (
        <Form
            {...destroyScheduleBlock.form(block.id)}
            headers={{ 'X-Idempotency-Key': mutationKey }}
            className="mt-4"
            onSubmit={(event) => {
                if (!window.confirm('Remover este bloqueio de horário?')) {
                    event.preventDefault();
                }
            }}
            onSuccess={onClose}
        >
            {({ errors, processing }) => (
                <>
                    <FormErrorSummary errors={errors} />
                    <input
                        type="hidden"
                        name="lock_version"
                        value={block.lock_version}
                    />
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={processing}
                        className="w-full"
                    >
                        {processing
                            ? 'Removendo…'
                            : 'Remover bloqueio de horário'}
                    </Button>
                </>
            )}
        </Form>
    );
}

export default function CalendarIndex(props: CalendarProps) {
    const page = usePage<SharedPageProps & CalendarProps>();
    const [createOpen, setCreateOpen] = useState(false);
    const [blockCreateOpen, setBlockCreateOpen] = useState(false);
    const [filterOpen, setFilterOpen] = useState(false);
    const [editing, setEditing] = useState<CalendarAppointment | null>(null);
    const [cancelSectionOpen, setCancelSectionOpen] = useState(false);
    const [openSaleDialogOpen, setOpenSaleDialogOpen] = useState(false);
    const [selectedBlock, setSelectedBlock] = useState<ScheduleBlock | null>(
        null,
    );
    const [prefilledSlot, setPrefilledSlot] = useState<{
        date: string;
        durationMinutes?: number;
        professionalId?: string;
        time: string;
    } | null>(null);

    const [prefilledBlockSlot, setPrefilledBlockSlot] = useState<{
        date: string;
        endsAt: string;
        professionalId?: string;
        startsAt: string;
    } | null>(null);

    const permissions = page.props.auth.permissions;
    const canManage = permissions.some((permission) =>
        ['appointment.manage', 'calendar.manage'].includes(permission),
    );
    const canManageSales = permissions.includes('sale.manage');
    const saleCategories = props.options?.sale_categories ?? [];
    const appointments =
        props.calendar?.appointments ?? props.appointments ?? [];

    const scheduleBlocks =
        props.scheduleBlocks ??
        props.calendar?.schedule_blocks ??
        props.schedule_blocks ??
        props.calendarSettings?.schedule_blocks ??
        [];

    const [isSyncing, setIsSyncing] = useState(false);

    const handleManualSync = () => {
        if (isSyncing) {
            return;
        }

        setIsSyncing(true);
        router.reload({
            only: ['appointments', 'scheduleBlocks'],
            onFinish: () => {
                setIsSyncing(false);
            },
        });
    };

    useEffect(() => {
        let intervalId: ReturnType<typeof setInterval> | null = null;

        const reloadData = () => {
            if (document.visibilityState === 'hidden') {
                return;
            }

            setIsSyncing(true);
            router.reload({
                only: ['appointments', 'scheduleBlocks'],
                onFinish: () => {
                    setIsSyncing(false);
                },
            });
        };

        const startPolling = () => {
            if (intervalId !== null) {
                clearInterval(intervalId);
            }

            intervalId = setInterval(reloadData, 30000);
        };

        const stopPolling = () => {
            if (intervalId !== null) {
                clearInterval(intervalId);
                intervalId = null;
            }
        };

        const handleVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                reloadData();
                startPolling();
            } else {
                stopPolling();
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);
        startPolling();

        return () => {
            stopPolling();
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        };
    }, []);
    const filters = props.calendar?.filters ?? props.filters ?? {};
    const view = filters.view ?? 'week';
    const unitTimezone =
        props.unitTimezone ?? props.calendar?.timezone ?? 'UTC';
    const selectedDate =
        filters.date ??
        props.calendar?.range?.start ??
        props.range?.start ??
        dateKey(new Date().toISOString(), unitTimezone);
    const range = props.calendar?.range ??
        props.range ?? {
            start: selectedDate,
            end: addDays(selectedDate, view === 'week' ? 6 : 0),
        };
    const customers = optionList(props.options?.customers, props.customers);
    const professionals = optionList(
        props.options?.professionals,
        props.professionals,
    );
    const services = optionList(props.options?.services, props.services);
    const loadError = typeof props.error === 'string' ? props.error : undefined;
    const flashAutomationError = page.props.flash?.error ?? undefined;
    const flashAutomationIssue = flashAutomationError
        ? automationIssueFromErrors({ flash: flashAutomationError })
        : null;
    const selectedProfessionalIds = filters.professional_ids ?? [];
    const selectedStatuses = filters.status ?? [];
    const visibleAppointments = appointments.filter((appointment) => {
        const matchesProfessional =
            selectedProfessionalIds.length === 0 ||
            selectedProfessionalIds.includes(
                appointment.professional_id ??
                    appointment.professional?.id ??
                    '',
            );
        const matchesStatus =
            selectedStatuses.length === 0 ||
            selectedStatuses.includes(appointment.status);

        return matchesProfessional && matchesStatus;
    });

    const visibleScheduleBlocks = scheduleBlocks.filter((block) => {
        if (block.status === 'cancelled') {
            return false;
        }

        if (selectedProfessionalIds.length === 0) {
            return true;
        }

        return (
            !block.professional_id ||
            selectedProfessionalIds.includes(block.professional_id)
        );
    });

    const handleOpenAppointment = (appointment: CalendarAppointment) => {
        setCreateOpen(false);
        setPrefilledSlot(null);
        setCancelSectionOpen(false);
        setEditing(appointment);
    };

    const handleSlotClick = ({
        date,
        time,
        professionalId,
    }: {
        date: string;
        time: string;
        professionalId?: string;
    }) => {
        if (!canManage) {
            return;
        }

        setEditing(null);
        setCancelSectionOpen(false);
        setPrefilledSlot({ date, professionalId, time });
        setCreateOpen(true);
    };

    const handleDragSelect = (
        action: 'appointment' | 'block',
        selection: {
            date: string;
            endMinutes: number;
            professionalId?: string;
            startMinutes: number;
        },
    ) => {
        if (!canManage) {
            return;
        }

        const minMins = Math.min(selection.startMinutes, selection.endMinutes);
        const maxMins =
            Math.max(selection.startMinutes, selection.endMinutes) + 15;
        const durationMinutes = maxMins - minMins;

        const startH = Math.floor(minMins / 60);
        const startM = minMins % 60;
        const startTimeStr = `${String(startH).padStart(2, '0')}:${String(startM).padStart(2, '0')}`;
        const startsAt = `${selection.date}T${startTimeStr}`;

        const endH = Math.floor(maxMins / 60);
        const endM = maxMins % 60;
        const endTimeStr = `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
        const endsAt = `${selection.date}T${endTimeStr}`;

        if (action === 'appointment') {
            setEditing(null);
            setPrefilledSlot({
                date: selection.date,
                durationMinutes,
                professionalId: selection.professionalId,
                time: startTimeStr,
            });
            setCreateOpen(true);
        } else {
            setPrefilledBlockSlot({
                date: selection.date,
                endsAt,
                professionalId: selection.professionalId,
                startsAt,
            });
            setBlockCreateOpen(true);
        }
    };

    const activeProfessionalsForHeader = useMemo(() => {
        if (filters.professional_ids && filters.professional_ids.length > 0) {
            return professionals.filter((p) =>
                filters.professional_ids?.includes(p.id),
            );
        }

        return professionals;
    }, [professionals, filters.professional_ids]);

    const weekDates = useMemo(() => {
        return Array.from({ length: 7 }, (_, index) =>
            addDays(range.start, index),
        );
    }, [range.start]);

    const activeMobileProfId =
        selectedProfessionalIds.length === 1
            ? selectedProfessionalIds[0]
            : undefined;

    return (
        <>
            <Head title="Agenda" />
            <PageCanvas>
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                            Operação diária
                        </p>
                        <h1 className="mt-2 font-display text-3xl leading-tight font-semibold tracking-[-0.035em] sm:text-4xl">
                            Agenda
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                            Organize horários, disponibilidade e próximos
                            atendimentos em uma única visão operacional.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <FilterSummary
                            professionals={professionals.filter(
                                (professional) =>
                                    selectedProfessionalIds.includes(
                                        professional.id,
                                    ),
                            )}
                            statuses={selectedStatuses}
                        />
                        <Button
                            variant="outline"
                            onClick={() => setFilterOpen(true)}
                            className="h-11 min-h-[44px] lg:hidden"
                        >
                            <SlidersHorizontal aria-hidden="true" />
                            Filtros
                        </Button>
                    </div>
                </header>

                <GoogleCalendarPanel />

                <CalendarToolbar
                    canManage={canManage}
                    date={selectedDate}
                    filters={filters}
                    onCreate={() => {
                        setPrefilledSlot(null);
                        setCreateOpen(true);
                    }}
                    onCreateBlock={() => setBlockCreateOpen(true)}
                    onFilter={() => setFilterOpen(true)}
                    onSync={handleManualSync}
                    isSyncing={isSyncing}
                    range={range}
                    timeZone={unitTimezone}
                    view={view}
                />

                {loadError ? <CalendarError message={loadError} /> : null}
                {flashAutomationIssue ? (
                    <AutomationFeedback
                        kind={flashAutomationIssue}
                        onReload={() => router.reload()}
                    >
                        {flashAutomationError}
                    </AutomationFeedback>
                ) : null}

                {/* Filtros rápidos de profissionais (responsivo para mobile e desktop) */}
                <section className="gap-4" aria-label="Filtros da agenda">
                    <div className="flex scrollbar-none items-center gap-2 overflow-x-auto rounded-xl border border-border bg-muted/20 px-3 py-2 sm:flex-wrap sm:px-4 sm:py-3">
                        <span className="shrink-0 text-xs font-semibold text-muted-foreground">
                            Profissionais:
                        </span>
                        <Button
                            asChild
                            size="sm"
                            variant={
                                selectedProfessionalIds.length === 0
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            className="h-11 min-h-[44px] shrink-0 rounded-full px-3 text-xs font-medium sm:h-8 sm:min-h-0"
                        >
                            <Link
                                href={calendarIndex({
                                    query: {
                                        ...filters,
                                        date: selectedDate,
                                        view,
                                        professional_ids: [],
                                    },
                                })}
                            >
                                Todos
                            </Link>
                        </Button>
                        {professionals.map((professional) => {
                            const isSelected = selectedProfessionalIds.includes(
                                professional.id,
                            );

                            return (
                                <Button
                                    key={professional.id}
                                    asChild
                                    size="sm"
                                    variant={isSelected ? 'secondary' : 'ghost'}
                                    className="h-11 min-h-[44px] shrink-0 rounded-full px-3 text-xs font-medium sm:h-8 sm:min-h-0"
                                >
                                    <Link
                                        href={calendarIndex({
                                            query: {
                                                ...filters,
                                                date: selectedDate,
                                                view,
                                                professional_ids: isSelected
                                                    ? selectedProfessionalIds.filter(
                                                          (id) =>
                                                              id !==
                                                              professional.id,
                                                      )
                                                    : [
                                                          ...selectedProfessionalIds,
                                                          professional.id,
                                                      ],
                                            },
                                        })}
                                    >
                                        {professional.name}
                                    </Link>
                                </Button>
                            );
                        })}
                        {professionals.length === 0 ? (
                            <span className="text-xs text-muted-foreground">
                                Nenhum profissional disponível.
                            </span>
                        ) : null}
                    </div>
                </section>

                {props.loading ? (
                    <CalendarLoading />
                ) : view === 'month' ? (
                    visibleAppointments.length === 0 &&
                    visibleScheduleBlocks.length === 0 ? (
                        <EmptyCalendar
                            action={
                                canManage ? (
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setBlockCreateOpen(true)
                                            }
                                            className="h-11 min-h-[44px] sm:h-9 sm:min-h-0"
                                        >
                                            <Lock aria-hidden="true" />
                                            Novo bloqueio
                                        </Button>
                                        <Button
                                            onClick={() => {
                                                setPrefilledSlot(null);
                                                setCreateOpen(true);
                                            }}
                                            className="h-11 min-h-[44px] sm:h-9 sm:min-h-0"
                                        >
                                            <Plus aria-hidden="true" />
                                            Novo agendamento
                                        </Button>
                                    </div>
                                ) : undefined
                            }
                            description="Nenhum agendamento ou bloqueio encontrado neste mês."
                        />
                    ) : (
                        <MonthAgenda
                            appointments={visibleAppointments}
                            onOpen={handleOpenAppointment}
                            onOpenBlock={setSelectedBlock}
                            range={range}
                            scheduleBlocks={visibleScheduleBlocks}
                            timeZone={unitTimezone}
                        />
                    )
                ) : view === 'day' ? (
                    <DayAgenda
                        appointments={visibleAppointments}
                        date={selectedDate}
                        onDragSelect={handleDragSelect}
                        onOpen={handleOpenAppointment}
                        onOpenBlock={setSelectedBlock}
                        onSlotClick={handleSlotClick}
                        professionals={professionals}
                        selectedProfessionalId={activeMobileProfId}
                        onSelectProfessional={(id) => {
                            router.get(
                                calendarIndex({
                                    query: {
                                        ...filters,
                                        date: selectedDate,
                                        view: 'day',
                                        professional_ids: id ? [id] : [],
                                    },
                                }),
                            );
                        }}
                        scheduleBlocks={visibleScheduleBlocks}
                        timeZone={unitTimezone}
                    />
                ) : (
                    <>
                        <div className="md:hidden">
                            <DayAgenda
                                appointments={visibleAppointments}
                                date={selectedDate}
                                onDragSelect={handleDragSelect}
                                onOpen={handleOpenAppointment}
                                onOpenBlock={setSelectedBlock}
                                onSlotClick={handleSlotClick}
                                professionals={professionals}
                                selectedProfessionalId={activeMobileProfId}
                                onSelectProfessional={(id) => {
                                    router.get(
                                        calendarIndex({
                                            query: {
                                                ...filters,
                                                date: selectedDate,
                                                view: 'week',
                                                professional_ids: id
                                                    ? [id]
                                                    : [],
                                            },
                                        }),
                                    );
                                }}
                                weekDates={weekDates}
                                onSelectDate={(d) => {
                                    router.get(
                                        calendarIndex({
                                            query: {
                                                ...filters,
                                                date: d,
                                                view: 'week',
                                            },
                                        }),
                                    );
                                }}
                                scheduleBlocks={visibleScheduleBlocks}
                                timeZone={unitTimezone}
                            />
                        </div>
                        <div className="hidden md:block">
                            <WeekCalendar
                                appointments={visibleAppointments}
                                onDragSelect={handleDragSelect}
                                onOpen={handleOpenAppointment}
                                onOpenBlock={setSelectedBlock}
                                onSlotClick={handleSlotClick}
                                professionals={activeProfessionalsForHeader}
                                range={range}
                                scheduleBlocks={visibleScheduleBlocks}
                                timeZone={unitTimezone}
                            />
                        </div>
                    </>
                )}

                <p className="text-xs leading-5 text-muted-foreground">
                    Horários exibidos no fuso da unidade ({unitTimezone}). A
                    duração é enviada ao servidor para calcular o horário final;
                    conflitos, disponibilidade e permissões são confirmados ao
                    salvar.
                </p>
            </PageCanvas>

            <Dialog
                open={createOpen || editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCreateOpen(false);
                        setEditing(null);
                        setPrefilledSlot(null);
                        setCancelSectionOpen(false);
                    }
                }}
            >
                <DialogContent className="max-h-[calc(100dvh-1rem)] w-[calc(100%-1rem)] overflow-y-auto p-4 sm:w-full sm:max-w-3xl sm:p-6">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? 'Editar agendamento'
                                : 'Novo agendamento'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Atualize os dados e confirme a versão mais recente antes de salvar.'
                                : 'Preencha os dados essenciais para reservar um horário na agenda.'}
                        </DialogDescription>
                    </DialogHeader>

                    {editing ? (
                        <AppointmentSummaryHeader
                            appointment={editing}
                            cancelSectionOpen={cancelSectionOpen}
                            canManage={canManage}
                            canManageSales={canManageSales}
                            customers={customers}
                            onClose={() => {
                                setCreateOpen(false);
                                setEditing(null);
                                setPrefilledSlot(null);
                                setCancelSectionOpen(false);
                            }}
                            onOpenSaleDialog={() => setOpenSaleDialogOpen(true)}
                            onToggleCancel={() =>
                                setCancelSectionOpen((prev) => !prev)
                            }
                            services={services}
                            unitTimezone={unitTimezone}
                        />
                    ) : null}

                    <AppointmentForm
                        key={
                            editing
                                ? `appointment-edit-${editing.id}-${editing.lock_version}`
                                : `appointment-create-${prefilledSlot?.date ?? ''}-${prefilledSlot?.time ?? ''}`
                        }
                        appointment={editing}
                        customers={customers}
                        defaultDurationMinutes={prefilledSlot?.durationMinutes}
                        defaultProfessionalId={prefilledSlot?.professionalId}
                        defaultStartsAt={
                            prefilledSlot
                                ? `${prefilledSlot.date}T${prefilledSlot.time}`
                                : ''
                        }
                        existingAppointments={appointments}
                        existingScheduleBlocks={scheduleBlocks}
                        onClose={() => {
                            setCreateOpen(false);
                            setEditing(null);
                            setPrefilledSlot(null);
                            setCancelSectionOpen(false);
                        }}
                        onTriggerCancel={() => setCancelSectionOpen(true)}
                        professionals={professionals}
                        services={services}
                        unitTimezone={unitTimezone}
                    />
                </DialogContent>
            </Dialog>

            <Dialog
                open={openSaleDialogOpen}
                onOpenChange={setOpenSaleDialogOpen}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Receipt className="size-5 text-primary" />
                            Abrir comanda para agendamento
                        </DialogTitle>
                        <DialogDescription>
                            Selecione a categoria de comanda para iniciar o
                            atendimento de{' '}
                            <strong>
                                {editing?.customer?.name ?? 'Cliente'}
                            </strong>
                            .
                        </DialogDescription>
                    </DialogHeader>
                    {editing ? (
                        <OpenAppointmentSaleForm
                            appointment={editing}
                            categories={saleCategories}
                            onClose={() => {
                                setOpenSaleDialogOpen(false);
                                setEditing(null);
                            }}
                        />
                    ) : null}
                </DialogContent>
            </Dialog>

            <Dialog
                open={blockCreateOpen}
                onOpenChange={(open) => {
                    setBlockCreateOpen(open);

                    if (!open) {
                        setPrefilledBlockSlot(null);
                    }
                }}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Lock
                                className="size-5 text-amber-600 dark:text-amber-400"
                                aria-hidden="true"
                            />
                            Novo bloqueio de horário
                        </DialogTitle>
                        <DialogDescription>
                            Bloqueie intervalos para pausas, almoço, consultas
                            ou manutenções na agenda.
                        </DialogDescription>
                    </DialogHeader>
                    <ScheduleBlockForm
                        defaultDate={prefilledBlockSlot?.date ?? selectedDate}
                        defaultEndsAt={prefilledBlockSlot?.endsAt}
                        defaultProfessionalId={
                            prefilledBlockSlot?.professionalId
                        }
                        defaultStartsAt={prefilledBlockSlot?.startsAt}
                        onClose={() => {
                            setBlockCreateOpen(false);
                            setPrefilledBlockSlot(null);
                        }}
                        professionals={professionals}
                        unitTimezone={unitTimezone}
                    />
                </DialogContent>
            </Dialog>

            <Dialog
                open={selectedBlock !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedBlock(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Lock
                                className="size-5 text-amber-600 dark:text-amber-400"
                                aria-hidden="true"
                            />
                            Detalhes do bloqueio
                        </DialogTitle>
                        <DialogDescription>
                            Informações sobre o intervalo bloqueado na agenda.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedBlock ? (
                        <div className="space-y-4">
                            <div className="rounded-lg border border-amber-300/60 bg-amber-50/70 p-3.5 text-sm dark:border-amber-700/60 dark:bg-amber-950/40">
                                <p className="font-semibold text-amber-950 dark:text-amber-100">
                                    {selectedBlock.reason ||
                                        'Horário bloqueado / Pausa operacional'}
                                </p>
                                <p className="mt-1 text-xs text-amber-900/80 dark:text-amber-300/80">
                                    {formatDay(
                                        selectedBlock.starts_at,
                                        unitTimezone,
                                    )}
                                    ,{' '}
                                    {formatTime(
                                        selectedBlock.starts_at,
                                        unitTimezone,
                                    )}{' '}
                                    às{' '}
                                    {formatTime(
                                        selectedBlock.ends_at,
                                        unitTimezone,
                                    )}
                                </p>
                                <p className="mt-1 text-xs text-amber-900/80 dark:text-amber-300/80">
                                    {selectedBlock.professional?.name
                                        ? `Profissional: ${selectedBlock.professional.name}`
                                        : 'Aplica-se a toda a unidade'}
                                </p>
                            </div>
                            {canManage ? (
                                <DeleteScheduleBlockForm
                                    block={selectedBlock}
                                    onClose={() => setSelectedBlock(null)}
                                />
                            ) : null}
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>

            <Sheet open={filterOpen} onOpenChange={setFilterOpen}>
                <SheetContent
                    side="right"
                    className="w-full overflow-y-auto sm:max-w-md"
                >
                    <SheetHeader>
                        <SheetTitle>Filtrar agenda</SheetTitle>
                        <SheetDescription>
                            Escolha os profissionais e situações que deseja
                            acompanhar.
                        </SheetDescription>
                    </SheetHeader>
                    <Form
                        {...calendarIndex.form()}
                        className="flex flex-1 flex-col"
                        onSuccess={() => setFilterOpen(false)}
                    >
                        <input type="hidden" name="date" value={selectedDate} />
                        <input type="hidden" name="view" value={view} />
                        <div className="flex-1 space-y-6 overflow-y-auto px-4 py-5">
                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">
                                    Profissionais
                                </legend>
                                {professionals.map((professional) => (
                                    <label
                                        key={professional.id}
                                        className="flex min-h-11 items-center gap-3 rounded-lg border border-border px-3 py-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            name="professional_ids[]"
                                            value={professional.id}
                                            defaultChecked={selectedProfessionalIds.includes(
                                                professional.id,
                                            )}
                                            className="size-4 accent-primary"
                                        />
                                        {professional.name}
                                    </label>
                                ))}
                                {professionals.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum profissional disponível.
                                    </p>
                                ) : null}
                            </fieldset>
                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">
                                    Situação
                                </legend>
                                {statusOptions.map((status) => (
                                    <label
                                        key={status}
                                        className="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-sm"
                                    >
                                        <span className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                name="status[]"
                                                value={status}
                                                defaultChecked={selectedStatuses.includes(
                                                    status,
                                                )}
                                                className="size-4 accent-primary"
                                            />
                                            {statusLabel(status)}
                                        </span>
                                        <StatusChip status={status} />
                                    </label>
                                ))}
                            </fieldset>
                        </div>
                        <SheetFooter className="border-t border-border">
                            <Button type="submit">
                                <FilterSummary
                                    professionals={[]}
                                    statuses={[]}
                                />
                                Aplicar filtros
                            </Button>
                            <Button asChild variant="ghost">
                                <Link
                                    href={calendarIndex({
                                        query: { date: selectedDate, view },
                                    })}
                                >
                                    Limpar filtros
                                </Link>
                            </Button>
                        </SheetFooter>
                    </Form>
                </SheetContent>
            </Sheet>
        </>
    );
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'Agenda', href: calendarIndex() }],
};
