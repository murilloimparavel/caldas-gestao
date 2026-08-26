import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Filter,
    Lock,
    MoreHorizontal,
    UserRound,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type {
    CSSProperties,
    KeyboardEvent as ReactKeyboardEvent,
    ReactNode,
} from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { index as calendarIndex } from '@/routes/calendar';
import type {
    AppointmentStatus,
    CalendarAppointment,
    CalendarFilters,
    CalendarOption,
    CalendarRange,
    CalendarView,
    ScheduleBlock,
} from '@/types/calendar';

export const statusLabels: Record<string, string> = {
    cancelled: 'Cancelado',
    checked_in: 'Chegou',
    completed: 'Concluído',
    confirmed: 'Confirmado',
    draft: 'Rascunho',
    in_service: 'Em atendimento',
    no_show: 'Não compareceu',
    scheduled: 'Agendado',
};

const statusClasses: Record<string, string> = {
    cancelled:
        'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200',
    checked_in:
        'border-cyan-300 bg-cyan-50 text-cyan-800 dark:border-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200',
    completed:
        'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    confirmed:
        'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-200',
    draft: 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
    in_service:
        'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
    no_show:
        'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-800 dark:bg-orange-950/40 dark:text-orange-200',
    scheduled:
        'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
};

type ZonedParts = {
    day: number;
    hour: number;
    minute: number;
    month: number;
    year: number;
};

function getInitials(name: string): string {
    const parts = name.trim().split(/\s+/);

    if (parts.length === 0 || !parts[0]) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

function asInstant(value: string): Date {
    const parsed = new Date(value.includes('T') ? value : `${value}T12:00:00Z`);

    return Number.isNaN(parsed.getTime()) ? new Date(0) : parsed;
}

function partsFor(value: string, timeZone = 'UTC'): ZonedParts {
    const parts = new Intl.DateTimeFormat('en-US', {
        day: '2-digit',
        hour: '2-digit',
        hour12: false,
        minute: '2-digit',
        month: '2-digit',
        timeZone,
        year: 'numeric',
    }).formatToParts(asInstant(value));
    const values = Object.fromEntries(
        parts.map(({ type, value: partValue }) => [type, partValue]),
    );

    return {
        day: Number(values.day),
        hour: Number(values.hour) % 24,
        minute: Number(values.minute),
        month: Number(values.month),
        year: Number(values.year),
    };
}

function dateOnlyParts(
    value: string,
): Pick<ZonedParts, 'day' | 'month' | 'year'> {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        const parts = partsFor(value);

        return { day: parts.day, month: parts.month, year: parts.year };
    }

    return {
        day: Number(match[3]),
        month: Number(match[2]),
        year: Number(match[1]),
    };
}

export function dateKey(value: string, timeZone = 'UTC'): string {
    const parts = value.includes('T')
        ? partsFor(value, timeZone)
        : dateOnlyParts(value);

    return `${parts.year}-${String(parts.month).padStart(2, '0')}-${String(parts.day).padStart(2, '0')}`;
}

export function dateTimeValue(value: string, timeZone = 'UTC'): string {
    const parts = partsFor(value, timeZone);

    return `${dateKey(value, timeZone)}T${String(parts.hour).padStart(2, '0')}:${String(parts.minute).padStart(2, '0')}`;
}

export function zonedTimeParts(
    value: string,
    timeZone = 'UTC',
): Pick<ZonedParts, 'hour' | 'minute'> {
    const { hour, minute } = partsFor(value, timeZone);

    return { hour, minute };
}

export function formatTime(value: string, timeZone = 'UTC'): string {
    return new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone,
    }).format(asInstant(value));
}

export function formatDay(value: string, timeZone = 'UTC'): string {
    const displayTimeZone = value.includes('T') ? timeZone : 'UTC';

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'short',
        timeZone: displayTimeZone,
        weekday: 'short',
    })
        .format(asInstant(value))
        .replace('.', '');
}

export function addDays(value: string, amount: number): string {
    const { day, month, year } = dateOnlyParts(value);
    const date = new Date(Date.UTC(year, month - 1, day + amount));

    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
}

export function addMonths(value: string, amount: number): string {
    const { day, month, year } = dateOnlyParts(value);
    const date = new Date(Date.UTC(year, month - 1 + amount, 1));
    const lastDay = new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0),
    ).getUTCDate();

    date.setUTCDate(Math.min(day, lastDay));

    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
}

export function statusLabel(status: AppointmentStatus): string {
    return statusLabels[status] ?? status.replaceAll('_', ' ');
}

export function StatusChip({ status }: { status: AppointmentStatus }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                statusClasses[status] ??
                    'border-border bg-muted text-muted-foreground',
            )}
        >
            {statusLabel(status)}
        </Badge>
    );
}

export function ProfessionalAvatarHeader({
    professional,
    subtitle,
}: {
    professional: CalendarOption;
    subtitle?: string;
}) {
    return (
        <div className="flex items-center gap-2 px-2 py-2">
            <Avatar className="size-8 shrink-0 border border-border">
                {professional.avatar_url ? (
                    <AvatarImage src={professional.avatar_url} alt={professional.name} />
                ) : null}
                <AvatarFallback className="bg-primary/10 text-primary font-semibold text-xs">
                    {getInitials(professional.name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-semibold text-foreground">
                    {professional.name}
                </p>
                {subtitle ? (
                    <p className="truncate text-[10px] text-muted-foreground">
                        {subtitle}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

export function CalendarToolbar({
    canManage,
    date,
    filters,
    onCreate,
    onCreateBlock,
    onFilter,
    range,
    timeZone,
    view,
}: {
    canManage: boolean;
    date: string;
    filters: CalendarFilters;
    onCreate: () => void;
    onCreateBlock?: () => void;
    onFilter: () => void;
    range?: CalendarRange;
    timeZone?: string;
    view: CalendarView;
}) {
    const step = view === 'month' ? 1 : view === 'week' ? 7 : 1;
    const move = view === 'month' ? addMonths : addDays;
    const previousDate = move(date, -step);
    const nextDate = move(date, step);

    const query = { ...filters, view };

    return (
        <div className="flex flex-col gap-3 sm:gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex min-w-0 items-center justify-between gap-2 sm:justify-start">
                <div className="flex items-center gap-1 sm:gap-2">
                    <Button
                        asChild
                        size="icon"
                        variant="outline"
                        aria-label="Período anterior"
                        className="h-11 w-11 shrink-0"
                    >
                        <Link
                            href={calendarIndex({
                                query: {
                                    ...query,
                                    date: previousDate,
                                },
                            })}
                        >
                            <ChevronLeft className="size-5" aria-hidden="true" />
                        </Link>
                    </Button>
                    <div className="min-w-0 px-1 text-center sm:text-left">
                        <p className="truncate text-base font-semibold capitalize sm:text-xl">
                            {range?.label ?? formatDay(date, timeZone)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {view === 'week'
                                ? 'Visão semanal'
                                : view === 'month'
                                  ? 'Visão mensal'
                                  : 'Visão diária'}
                        </p>
                    </div>
                    <Button
                        asChild
                        size="icon"
                        variant="outline"
                        aria-label="Próximo período"
                        className="h-11 w-11 shrink-0"
                    >
                        <Link
                            href={calendarIndex({
                                query: {
                                    ...query,
                                    date: nextDate,
                                },
                            })}
                        >
                            <ChevronRight className="size-5" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
                <Button
                    asChild
                    variant="ghost"
                    className="h-11 min-h-[44px] px-3 font-medium sm:inline-flex"
                >
                    <Link
                        href={calendarIndex({
                            query: {
                                ...filters,
                                date: dateKey(
                                    new Date().toISOString(),
                                    timeZone,
                                ),
                                view,
                            },
                        })}
                    >
                        Hoje
                    </Link>
                </Button>
            </div>

            <div className="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap sm:items-center">
                <div
                    className="flex w-full rounded-lg border border-border bg-muted/30 p-1 sm:w-auto"
                    role="group"
                    aria-label="Visualização da agenda"
                >
                    {(['day', 'week', 'month'] as CalendarView[]).map(
                        (item) => (
                            <Button
                                key={item}
                                asChild
                                size="sm"
                                variant={view === item ? 'secondary' : 'ghost'}
                                className="h-11 min-h-[44px] flex-1 px-3 text-xs font-medium sm:h-9 sm:min-h-0 sm:flex-none"
                            >
                                <Link
                                    href={calendarIndex({
                                        query: { ...filters, date, view: item },
                                    })}
                                >
                                    {item === 'day'
                                        ? 'Dia'
                                        : item === 'week'
                                          ? 'Semana'
                                          : 'Mês'}
                                </Link>
                            </Button>
                        ),
                    )}
                </div>
                <div className="flex w-full items-center gap-2 sm:w-auto">
                    <Button
                        variant="outline"
                        onClick={onFilter}
                        className="h-11 min-h-[44px] flex-1 sm:h-9 sm:min-h-0 sm:flex-none"
                    >
                        <Filter className="size-4" aria-hidden="true" />
                        Filtrar
                    </Button>
                    {canManage ? (
                        <>
                            {onCreateBlock ? (
                                <Button
                                    variant="outline"
                                    onClick={onCreateBlock}
                                    className="h-11 min-h-[44px] flex-1 sm:h-9 sm:min-h-0 sm:flex-none"
                                >
                                    <Lock className="size-4" aria-hidden="true" />
                                    <span className="hidden xs:inline sm:inline">Bloqueio</span>
                                    <span className="xs:hidden sm:hidden">Bloquear</span>
                                </Button>
                            ) : null}
                            <Button
                                onClick={onCreate}
                                className="h-11 min-h-[44px] flex-1 sm:h-9 sm:min-h-0 sm:flex-none"
                            >
                                <CalendarDays className="size-4" aria-hidden="true" />
                                <span>Agendar</span>
                            </Button>
                        </>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

export function AppointmentCard({
    appointment,
    compact = false,
    onOpen,
    timeZone,
}: {
    appointment: CalendarAppointment;
    compact?: boolean;
    onOpen: (appointment: CalendarAppointment) => void;
    timeZone?: string;
}) {
    const customerName = appointment.customer?.name ?? 'Cliente não informado';
    const serviceName =
        appointment.items?.[0]?.service?.name ??
        appointment.service?.name ??
        'Serviço não informado';

    return (
        <button
            type="button"
            onClick={(e) => {
                e.stopPropagation();
                onOpen(appointment);
            }}
            className={cn(
                'group w-full rounded-lg border p-2 text-left shadow-xs transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden',
                statusClasses[appointment.status] ??
                    'border-border bg-card text-card-foreground',
                compact ? 'h-full min-h-16' : 'min-h-24',
            )}
            aria-label={`${formatTime(appointment.starts_at, timeZone)}, ${customerName}, ${serviceName}, ${statusLabel(appointment.status)}`}
        >
            <div className="flex items-start justify-between gap-1">
                <span className="text-[11px] font-semibold leading-none">
                    {formatTime(appointment.starts_at, timeZone)}
                    {appointment.ends_at
                        ? `–${formatTime(appointment.ends_at, timeZone)}`
                        : null}
                </span>
                <MoreHorizontal
                    className="size-3.5 opacity-60 transition group-hover:opacity-100 shrink-0"
                    aria-hidden="true"
                />
            </div>
            <p className="mt-1 truncate text-xs font-bold leading-snug">
                {customerName}
            </p>
            <p className="truncate text-[11px] opacity-90">{serviceName}</p>
            {!compact && appointment.professional?.name ? (
                <p className="mt-1 truncate text-[10px] opacity-75">
                    {appointment.professional.name}
                </p>
            ) : null}
        </button>
    );
}

function dayDates(start: string, view: CalendarView): string[] {
    const count = view === 'week' ? 7 : 1;

    return Array.from({ length: count }, (_, index) => addDays(start, index));
}

export function ScheduleBlockCard({
    block,
    onOpen,
    timeZone,
}: {
    block: ScheduleBlock;
    onOpen?: (block: ScheduleBlock) => void;
    timeZone?: string;
}) {
    return (
        <button
            type="button"
            onClick={(e) => {
                e.stopPropagation();
                onOpen?.(block);
            }}
            className="group flex w-full items-start justify-between rounded-lg border border-slate-300 bg-slate-100/90 p-3 text-left text-slate-800 shadow-2xs transition hover:border-slate-400 hover:bg-slate-200/90 hover:shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-200 dark:hover:bg-slate-800"
        >
            <div className="flex items-start gap-3">
                <div className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    <Lock className="size-3.5" aria-hidden="true" />
                </div>
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-slate-900 dark:text-slate-100">
                            {formatTime(block.starts_at, timeZone)} –{' '}
                            {formatTime(block.ends_at, timeZone)}
                        </span>
                        <Badge
                            variant="outline"
                            className="border-slate-300 bg-slate-200/70 text-[10px] text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            Ocupado
                        </Badge>
                    </div>
                    <p className="mt-1 truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                        {block.reason || 'Ocupado / Horário bloqueado'}
                    </p>
                    {block.professional?.name ? (
                        <p className="mt-0.5 truncate text-xs text-slate-600 dark:text-slate-400">
                            Profissional: {block.professional.name}
                        </p>
                    ) : (
                        <p className="mt-0.5 text-xs text-slate-600 dark:text-slate-400">
                            Aplica-se a toda a unidade
                        </p>
                    )}
                </div>
            </div>
            <MoreHorizontal
                className="size-4 shrink-0 text-slate-500 opacity-60 transition group-hover:opacity-100 dark:text-slate-400"
                aria-hidden="true"
            />
        </button>
    );
}

export type DragSelection = {
    date: string;
    endMinutes: number;
    professionalId?: string;
    startMinutes: number;
};

function formatMinutes(totalMinutes: number): string {
    const hours = Math.floor(totalMinutes / 60);
    const mins = totalMinutes % 60;

    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
}

function formatDuration(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    if (h > 0 && m > 0) {
        return `${h}h ${m}m`;
    }

    if (h > 0) {
        return `${h}h`;
    }

    return `${m}m`;
}

export function SelectionPopover({
    onCancel,
    onSelectBlock,
    onSelectNew,
    selection,
}: {
    onCancel: () => void;
    onSelectBlock: (selection: DragSelection) => void;
    onSelectNew: (selection: DragSelection) => void;
    selection: DragSelection;
}) {
    const minMins = Math.min(selection.startMinutes, selection.endMinutes);
    const maxMins = Math.max(selection.startMinutes, selection.endMinutes) + 15;
    const duration = maxMins - minMins;
    const label = `${formatMinutes(minMins)} - ${formatMinutes(maxMins)} • ${formatDuration(duration)}`;

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onCancel();
                }
            }}
        >
            <DialogContent className="p-4 sm:max-w-xs">
                <DialogHeader className="space-y-1">
                    <DialogTitle className="text-center text-sm font-semibold">
                        Horário Selecionado
                    </DialogTitle>
                    <DialogDescription className="text-center text-xs font-medium text-primary">
                        {label}
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-2 pt-2">
                    <Button
                        size="sm"
                        className="w-full justify-start gap-2"
                        onClick={() => onSelectNew(selection)}
                    >
                        <span>🗓️</span>
                        <span>Novo Agendamento</span>
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        className="w-full justify-start gap-2"
                        onClick={() => onSelectBlock(selection)}
                    >
                        <span>🔒</span>
                        <span>Travar Horário / Bloqueio</span>
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

export function WeekCalendar({
    appointments,
    onDragSelect,
    onOpen,
    onOpenBlock,
    onSlotClick,
    professionals = [],
    range,
    scheduleBlocks = [],
    timeZone,
}: {
    appointments: CalendarAppointment[];
    onDragSelect?: (action: 'appointment' | 'block', selection: DragSelection) => void;
    onOpen: (appointment: CalendarAppointment) => void;
    onOpenBlock?: (block: ScheduleBlock) => void;
    onSlotClick?: (params: { date: string; time: string; professionalId?: string }) => void;
    professionals?: CalendarOption[];
    range: CalendarRange;
    scheduleBlocks?: ScheduleBlock[];
    timeZone?: string;
}) {
    const dates = dayDates(range.start, 'week');
    const startHour = 7;
    const endHour = 21;
    const slotHeight = 48; // Mínimo 48px para excelente usabilidade touch (>= 44px)
    const timelineHeight = (endHour - startHour) * 2 * slotHeight;
    const timeSlots = Array.from(
        { length: (endHour - startHour) * 2 },
        (_, index) => startHour * 60 + index * 30,
    );

    const [dragging, setDragging] = useState<DragSelection | null>(null);
    const [completedSelection, setCompletedSelection] = useState<DragSelection | null>(null);
    const isMouseDownRef = useRef(false);

    // Se houver profissionais selecionados/disponíveis e for visualização por profissionais (ou exibição na semana)
    const showProfessionalColumns = professionals.length > 0 && dates.length === 1;
    const columns = showProfessionalColumns
        ? professionals.map((p) => ({ id: p.id, title: p.name, professional: p, date: dates[0] }))
        : dates.map((d) => ({ id: d, title: formatDay(d, timeZone), professional: null, date: d }));

    const getMinutesFromY = (y: number) => {
        const clampedY = Math.max(0, Math.min(y, timelineHeight - 1));
        const slot15 = Math.floor(clampedY / (slotHeight / 2));
        const minutes = startHour * 60 + slot15 * 15;

        return Math.min(minutes, (endHour * 60) - 15);
    };

    const handlePointerDown = (
        colDate: string,
        profId: string | undefined,
        clientY: number,
        currentTarget: HTMLDivElement,
    ) => {
        const rect = currentTarget.getBoundingClientRect();
        const y = clientY - rect.top;
        const minutes = getMinutesFromY(y);

        isMouseDownRef.current = true;
        setDragging({
            date: colDate,
            endMinutes: minutes,
            professionalId: profId,
            startMinutes: minutes,
        });
    };

    const handlePointerMove = (clientY: number, currentTarget: HTMLDivElement) => {
        if (!isMouseDownRef.current || !dragging) {
            return;
        }

        const rect = currentTarget.getBoundingClientRect();
        const y = clientY - rect.top;
        const minutes = getMinutesFromY(y);

        if (minutes !== dragging.endMinutes) {
            setDragging((prev) => (prev ? { ...prev, endMinutes: minutes } : null));
        }
    };

    const handlePointerUp = () => {
        if (!isMouseDownRef.current || !dragging) {
            return;
        }

        isMouseDownRef.current = false;

        if (Math.abs(dragging.endMinutes - dragging.startMinutes) === 0) {
            const hours = Math.floor(dragging.startMinutes / 60);
            const mins = dragging.startMinutes % 60;
            const timeStr = `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;

            onSlotClick?.({
                date: dragging.date,
                professionalId: dragging.professionalId,
                time: timeStr,
            });
            setDragging(null);
        } else {
            setCompletedSelection(dragging);
            setDragging(null);
        }
    };

    return (
        <div className="surface-panel overflow-hidden select-none">
            <div className="overflow-x-auto">
                <div style={{ minWidth: `${Math.max(920, columns.length * 140 + 80)}px` }}>
                    <div
                        className="grid border-b border-border bg-muted/25"
                        style={{
                            gridTemplateColumns: `4.5rem repeat(${columns.length}, minmax(8rem, 1fr))`,
                        }}
                    >
                        <div
                            aria-hidden="true"
                            className="border-r border-border"
                        />
                        {columns.map((col) => (
                            <div
                                key={col.id}
                                className="border-r border-border px-2 py-2 last:border-r-0"
                            >
                                {col.professional ? (
                                    <ProfessionalAvatarHeader
                                        professional={col.professional}
                                        subtitle={formatDay(col.date, timeZone)}
                                    />
                                ) : (
                                    <div className="px-1 py-1">
                                        <p className="text-xs font-semibold text-muted-foreground capitalize">
                                            {col.title}
                                        </p>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                    <div
                        className="grid"
                        style={{
                            gridTemplateColumns: `4.5rem repeat(${columns.length}, minmax(8rem, 1fr))`,
                        }}
                    >
                        <div
                            className="relative border-r border-border"
                            style={{ height: timelineHeight }}
                        >
                            {timeSlots.map((minutes) => (
                                <span
                                    key={minutes}
                                    className="absolute right-2 -translate-y-1/2 text-[10px] font-medium text-muted-foreground"
                                    style={{
                                        top:
                                            ((minutes - startHour * 60) / 30) *
                                            slotHeight,
                                    }}
                                >
                                    {String(Math.floor(minutes / 60)).padStart(
                                        2,
                                        '0',
                                    )}
                                    :{String(minutes % 60).padStart(2, '0')}
                                </span>
                            ))}
                        </div>
                        {columns.map((col) => {
                            const dayAppointments = appointments.filter((appointment) => {
                                const matchDate = dateKey(appointment.starts_at, timeZone) === col.date;

                                if (!matchDate) {
                                    return false;
                                }

                                if (col.professional) {
                                    const profId = appointment.professional_id ?? appointment.professional?.id;

                                    return profId === col.professional.id;
                                }

                                return true;
                            });

                            const dayBlocks = scheduleBlocks.filter((block) => {
                                if (block.status === 'cancelled') {
                                    return false;
                                }

                                const matchDate = dateKey(block.starts_at, timeZone) === col.date;

                                if (!matchDate) {
                                    return false;
                                }

                                if (col.professional) {
                                    return !block.professional_id || block.professional_id === col.professional.id;
                                }

                                return true;
                            });

                            const isCurrentColDragging =
                                dragging &&
                                dragging.date === col.date &&
                                dragging.professionalId === col.professional?.id;

                            const dragMinMins = isCurrentColDragging
                                ? Math.min(dragging.startMinutes, dragging.endMinutes)
                                : 0;
                            const dragMaxMins = isCurrentColDragging
                                ? Math.max(dragging.startMinutes, dragging.endMinutes) + 15
                                : 0;
                            const dragDuration = dragMaxMins - dragMinMins;
                            const dragTop = isCurrentColDragging
                                ? ((dragMinMins - startHour * 60) / 30) * slotHeight
                                : 0;
                            const dragHeight = isCurrentColDragging
                                ? (dragDuration / 30) * slotHeight
                                : 0;

                            return (
                                <div
                                    key={col.id}
                                    className="relative cursor-pointer border-r border-border bg-[linear-gradient(to_bottom,transparent_47px,var(--border)_48px)] bg-size-[100%_48px] last:border-r-0 touch-none"
                                    style={{ height: timelineHeight }}
                                    onMouseDown={(e) => {
                                        if (e.button !== 0) {
return;
}

                                        handlePointerDown(col.date, col.professional?.id, e.clientY, e.currentTarget);
                                    }}
                                    onMouseMove={(e) => {
                                        handlePointerMove(e.clientY, e.currentTarget);
                                    }}
                                    onMouseUp={() => {
                                        handlePointerUp();
                                    }}
                                    onTouchStart={(e) => {
                                        if (e.touches[0]) {
                                            handlePointerDown(col.date, col.professional?.id, e.touches[0].clientY, e.currentTarget);
                                        }
                                    }}
                                    onTouchMove={(e) => {
                                        if (e.touches[0]) {
                                            handlePointerMove(e.touches[0].clientY, e.currentTarget);
                                        }
                                    }}
                                    onTouchEnd={() => {
                                        handlePointerUp();
                                    }}
                                >
                                    {/* Overlay Visual durante o arraste */}
                                    {isCurrentColDragging ? (
                                        <div
                                            className="absolute inset-x-1 z-20 flex flex-col justify-between rounded-md border-2 border-primary/50 bg-primary/20 p-1.5 shadow-sm pointer-events-none"
                                            style={{
                                                height: `${dragHeight}px`,
                                                top: `${dragTop}px`,
                                            }}
                                        >
                                            <span className="text-[11px] font-bold text-primary dark:text-primary-foreground drop-shadow-xs">
                                                {formatMinutes(dragMinMins)} - {formatMinutes(dragMaxMins)} • {formatDuration(dragDuration)}
                                            </span>
                                        </div>
                                    ) : null}

                                    {dayBlocks.map((block) => {
                                        const start = asInstant(block.starts_at);
                                        const end = asInstant(block.ends_at);
                                        const { hour, minute } = zonedTimeParts(
                                            block.starts_at,
                                            timeZone,
                                        );
                                        const startMinutes = hour * 60 + minute;
                                        const duration = Math.max(
                                            15,
                                            Math.round(
                                                (end.getTime() - start.getTime()) /
                                                    60000,
                                            ),
                                        );
                                        const style: CSSProperties = {
                                            height: `${Math.max(32, (duration / 30) * slotHeight - 4)}px`,
                                            top: `${((startMinutes - startHour * 60) / 30) * slotHeight + 2}px`,
                                        };

                                        return (
                                            <button
                                                key={block.id}
                                                type="button"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    onOpenBlock?.(block);
                                                }}
                                                className="group absolute inset-x-1 z-10 flex flex-col justify-between overflow-hidden rounded-md border border-slate-300 bg-slate-100/95 p-1.5 text-left text-slate-800 shadow-2xs backdrop-blur-xs transition hover:border-slate-400 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-900/95 dark:text-slate-200 dark:hover:bg-slate-800 min-h-[44px]"
                                                style={style}
                                                title={`Ocupado: ${block.reason || 'Horário bloqueado'} (${formatTime(block.starts_at, timeZone)} - ${formatTime(block.ends_at, timeZone)})`}
                                            >
                                                <div className="flex items-center justify-between gap-1">
                                                    <span className="flex items-center gap-1 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                                                        <Lock
                                                            className="size-3 text-slate-500 dark:text-slate-400 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                        {formatTime(
                                                            block.starts_at,
                                                            timeZone,
                                                        )}
                                                        –
                                                        {formatTime(
                                                            block.ends_at,
                                                            timeZone,
                                                        )}
                                                    </span>
                                                    <span className="rounded bg-slate-200 px-1 py-0.2 text-[9px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                        Ocupado
                                                    </span>
                                                </div>
                                                <p className="truncate text-[11px] font-medium text-slate-900 dark:text-slate-100">
                                                    {block.reason ||
                                                        'Ocupado'}
                                                </p>
                                                {block.professional?.name && !col.professional ? (
                                                    <p className="truncate text-[10px] text-slate-600 dark:text-slate-400">
                                                        {block.professional.name}
                                                    </p>
                                                ) : null}
                                            </button>
                                        );
                                    })}
                                    {dayAppointments.map((appointment) => {
                                        const start = asInstant(
                                            appointment.starts_at,
                                        );
                                        const end = asInstant(
                                            appointment.ends_at,
                                        );
                                        const { hour, minute } = zonedTimeParts(
                                            appointment.starts_at,
                                            timeZone,
                                        );
                                        const startMinutes = hour * 60 + minute;
                                        const duration = Math.max(
                                            30,
                                            Math.round(
                                                (end.getTime() -
                                                    start.getTime()) /
                                                    60000,
                                            ),
                                        );
                                        const style: CSSProperties = {
                                            height: `${Math.max(44, (duration / 30) * slotHeight - 6)}px`,
                                            top: `${((startMinutes - startHour * 60) / 30) * slotHeight + 3}px`,
                                        };

                                        return (
                                            <div
                                                key={appointment.id}
                                                className="absolute inset-x-1 z-0 min-h-[44px]"
                                                style={style}
                                            >
                                                <AppointmentCard
                                                    appointment={appointment}
                                                    compact
                                                    onOpen={onOpen}
                                                    timeZone={timeZone}
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {completedSelection ? (
                <SelectionPopover
                    selection={completedSelection}
                    onCancel={() => setCompletedSelection(null)}
                    onSelectNew={(sel) => {
                        onDragSelect?.('appointment', sel);
                        setCompletedSelection(null);
                    }}
                    onSelectBlock={(sel) => {
                        onDragSelect?.('block', sel);
                        setCompletedSelection(null);
                    }}
                />
            ) : null}
        </div>
    );
}

export function DayAgenda({
    appointments,
    date,
    onDragSelect,
    onOpen,
    onOpenBlock,
    onSlotClick,
    professionals = [],
    selectedProfessionalId,
    onSelectProfessional,
    scheduleBlocks = [],
    timeZone,
    weekDates = [],
    onSelectDate,
}: {
    appointments: CalendarAppointment[];
    date: string;
    onDragSelect?: (action: 'appointment' | 'block', selection: DragSelection) => void;
    onOpen: (appointment: CalendarAppointment) => void;
    onOpenBlock?: (block: ScheduleBlock) => void;
    onSlotClick?: (params: { date: string; time: string; professionalId?: string }) => void;
    professionals?: CalendarOption[];
    selectedProfessionalId?: string;
    onSelectProfessional?: (id: string | null) => void;
    scheduleBlocks?: ScheduleBlock[];
    timeZone?: string;
    weekDates?: string[];
    onSelectDate?: (date: string) => void;
}) {
    const startHour = 7;
    const endHour = 21;
    const slotHeight = 48;
    const timelineHeight = (endHour - startHour) * 2 * slotHeight;
    const timeSlots = Array.from(
        { length: (endHour - startHour) * 2 },
        (_, index) => startHour * 60 + index * 30,
    );

    const [dragging, setDragging] = useState<DragSelection | null>(null);
    const [completedSelection, setCompletedSelection] = useState<DragSelection | null>(null);
    const isMouseDownRef = useRef(false);

    const getMinutesFromY = (y: number) => {
        const clampedY = Math.max(0, Math.min(y, timelineHeight - 1));
        const slot15 = Math.floor(clampedY / (slotHeight / 2));
        const minutes = startHour * 60 + slot15 * 15;

        return Math.min(minutes, (endHour * 60) - 15);
    };

    const handlePointerDown = (clientY: number, currentTarget: HTMLDivElement) => {
        const rect = currentTarget.getBoundingClientRect();
        const y = clientY - rect.top;
        const minutes = getMinutesFromY(y);

        isMouseDownRef.current = true;
        setDragging({
            date,
            endMinutes: minutes,
            professionalId: selectedProfessionalId,
            startMinutes: minutes,
        });
    };

    const handlePointerMove = (clientY: number, currentTarget: HTMLDivElement) => {
        if (!isMouseDownRef.current || !dragging) {
            return;
        }

        const rect = currentTarget.getBoundingClientRect();
        const y = clientY - rect.top;
        const minutes = getMinutesFromY(y);

        if (minutes !== dragging.endMinutes) {
            setDragging((prev) => (prev ? { ...prev, endMinutes: minutes } : null));
        }
    };

    const handlePointerUp = () => {
        if (!isMouseDownRef.current || !dragging) {
            return;
        }

        isMouseDownRef.current = false;

        if (Math.abs(dragging.endMinutes - dragging.startMinutes) === 0) {
            const hours = Math.floor(dragging.startMinutes / 60);
            const mins = dragging.startMinutes % 60;
            const timeStr = `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;

            onSlotClick?.({
                date: dragging.date,
                professionalId: dragging.professionalId,
                time: timeStr,
            });
            setDragging(null);
        } else {
            setCompletedSelection(dragging);
            setDragging(null);
        }
    };

    const dayAppointments = appointments
        .filter(
            (appointment) => {
                const matchDate = dateKey(appointment.starts_at, timeZone) === date;

                if (!matchDate) {
                    return false;
                }

                if (selectedProfessionalId) {
                    const profId = appointment.professional_id ?? appointment.professional?.id;

                    return profId === selectedProfessionalId;
                }

                return true;
            },
        )
        .sort((left, right) => left.starts_at.localeCompare(right.starts_at));

    const dayBlocks = scheduleBlocks
        .filter(
            (block) => {
                if (block.status === 'cancelled') {
                    return false;
                }

                const matchDate = dateKey(block.starts_at, timeZone) === date;

                if (!matchDate) {
                    return false;
                }

                if (selectedProfessionalId) {
                    return !block.professional_id || block.professional_id === selectedProfessionalId;
                }

                return true;
            },
        )
        .sort((left, right) => left.starts_at.localeCompare(right.starts_at));

    const isDragging = dragging && dragging.date === date;
    const dragMinMins = isDragging ? Math.min(dragging.startMinutes, dragging.endMinutes) : 0;
    const dragMaxMins = isDragging ? Math.max(dragging.startMinutes, dragging.endMinutes) + 15 : 0;
    const dragDuration = dragMaxMins - dragMinMins;
    const dragTop = isDragging ? ((dragMinMins - startHour * 60) / 30) * slotHeight : 0;
    const dragHeight = isDragging ? (dragDuration / 30) * slotHeight : 0;

    return (
        <section
            className="surface-panel p-3.5 sm:p-5 select-none"
            aria-labelledby="day-agenda-title"
        >
            {/* Seletor de dias da semana para mobile */}
            {weekDates.length > 0 && onSelectDate ? (
                <div className="mb-4 overflow-x-auto pb-1 scrollbar-none md:hidden">
                    <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Dias da semana
                    </p>
                    <div className="flex items-center gap-1.5">
                        {weekDates.map((d) => {
                            const isSelected = d === date;

                            return (
                                <button
                                    key={d}
                                    type="button"
                                    onClick={() => onSelectDate(d)}
                                    className={cn(
                                        'flex min-h-[44px] min-w-[3.25rem] flex-1 flex-col items-center justify-center rounded-lg border px-2 py-1 text-center transition',
                                        isSelected
                                            ? 'border-primary bg-primary text-primary-foreground font-bold shadow-xs'
                                            : 'border-border bg-card text-foreground hover:bg-muted/50',
                                    )}
                                >
                                    <span className="text-[10px] uppercase opacity-80">
                                        {formatDay(d, timeZone).split(' ')[0]}
                                    </span>
                                    <span className="text-xs font-semibold">
                                        {dateOnlyParts(d).day}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            ) : null}

            {/* Seletor/tabs de profissionais para mobile */}
            {professionals.length > 1 && onSelectProfessional ? (
                <div className="mb-4 overflow-x-auto pb-1 scrollbar-none md:hidden">
                    <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Filtrar Profissional
                    </p>
                    <div className="flex items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() => onSelectProfessional(null)}
                            className={cn(
                                'flex h-11 min-h-[44px] shrink-0 items-center justify-center rounded-full px-4 text-xs font-semibold transition',
                                !selectedProfessionalId
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-border bg-card text-muted-foreground hover:bg-muted',
                            )}
                        >
                            Todos ({professionals.length})
                        </button>
                        {professionals.map((p) => {
                            const isSelected = selectedProfessionalId === p.id;

                            return (
                                <button
                                    key={p.id}
                                    type="button"
                                    onClick={() => onSelectProfessional(p.id)}
                                    className={cn(
                                        'flex h-11 min-h-[44px] shrink-0 items-center gap-2 rounded-full px-3 text-xs font-semibold transition',
                                        isSelected
                                            ? 'bg-primary text-primary-foreground'
                                            : 'border border-border bg-card text-foreground hover:bg-muted',
                                    )}
                                >
                                    <Avatar className="size-6 border border-border">
                                        {p.avatar_url ? (
                                            <AvatarImage src={p.avatar_url} alt={p.name} />
                                        ) : null}
                                        <AvatarFallback className="bg-primary/20 text-[10px]">
                                            {getInitials(p.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="truncate">{p.name}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            ) : null}

            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2
                        id="day-agenda-title"
                        className="text-base font-semibold capitalize"
                    >
                        {formatDay(date, timeZone)}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Clique ou arraste nos horários da grade temporal
                    </p>
                </div>
                <Clock3
                    className="size-5 text-muted-foreground shrink-0"
                    aria-hidden="true"
                />
            </div>

            {/* Grade Temporal Diária Interativa (Drag to select e clique) */}
            <div className="overflow-x-auto rounded-lg border border-border bg-muted/10 p-2 mb-4">
                <div
                    className="grid"
                    style={{
                        gridTemplateColumns: '4.5rem 1fr',
                    }}
                >
                    <div
                        className="relative border-r border-border"
                        style={{ height: timelineHeight }}
                    >
                        {timeSlots.map((minutes) => (
                            <span
                                key={minutes}
                                className="absolute right-2 -translate-y-1/2 text-[10px] font-medium text-muted-foreground"
                                style={{
                                    top:
                                        ((minutes - startHour * 60) / 30) *
                                        slotHeight,
                                }}
                            >
                                {String(Math.floor(minutes / 60)).padStart(
                                    2,
                                    '0',
                                )}
                                :{String(minutes % 60).padStart(2, '0')}
                            </span>
                        ))}
                    </div>
                    <div
                        className="relative cursor-pointer bg-[linear-gradient(to_bottom,transparent_47px,var(--border)_48px)] bg-size-[100%_48px] touch-none"
                        style={{ height: timelineHeight }}
                        onMouseDown={(e) => {
                            if (e.button !== 0) {
return;
}

                            handlePointerDown(e.clientY, e.currentTarget);
                        }}
                        onMouseMove={(e) => {
                            handlePointerMove(e.clientY, e.currentTarget);
                        }}
                        onMouseUp={() => {
                            handlePointerUp();
                        }}
                        onTouchStart={(e) => {
                            if (e.touches[0]) {
                                handlePointerDown(e.touches[0].clientY, e.currentTarget);
                            }
                        }}
                        onTouchMove={(e) => {
                            if (e.touches[0]) {
                                handlePointerMove(e.touches[0].clientY, e.currentTarget);
                            }
                        }}
                        onTouchEnd={() => {
                            handlePointerUp();
                        }}
                    >
                        {/* Overlay Visual durante o arraste */}
                        {isDragging ? (
                            <div
                                className="absolute inset-x-1 z-20 flex flex-col justify-between rounded-md border-2 border-primary/50 bg-primary/20 p-1.5 shadow-sm pointer-events-none"
                                style={{
                                    height: `${dragHeight}px`,
                                    top: `${dragTop}px`,
                                }}
                            >
                                <span className="text-[11px] font-bold text-primary dark:text-primary-foreground drop-shadow-xs">
                                    {formatMinutes(dragMinMins)} - {formatMinutes(dragMaxMins)} • {formatDuration(dragDuration)}
                                </span>
                            </div>
                        ) : null}

                        {dayBlocks.map((block) => {
                            const start = asInstant(block.starts_at);
                            const end = asInstant(block.ends_at);
                            const { hour, minute } = zonedTimeParts(
                                block.starts_at,
                                timeZone,
                            );
                            const startMinutes = hour * 60 + minute;
                            const duration = Math.max(
                                15,
                                Math.round(
                                    (end.getTime() - start.getTime()) /
                                        60000,
                                ),
                            );
                            const style: CSSProperties = {
                                height: `${Math.max(32, (duration / 30) * slotHeight - 4)}px`,
                                top: `${((startMinutes - startHour * 60) / 30) * slotHeight + 2}px`,
                            };

                            return (
                                <button
                                    key={block.id}
                                    type="button"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onOpenBlock?.(block);
                                    }}
                                    className="group absolute inset-x-1 z-10 flex flex-col justify-between overflow-hidden rounded-md border border-slate-300 bg-slate-100/95 p-1.5 text-left text-slate-800 shadow-2xs backdrop-blur-xs transition hover:border-slate-400 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-900/95 dark:text-slate-200 dark:hover:bg-slate-800 min-h-[44px]"
                                    style={style}
                                    title={`Ocupado: ${block.reason || 'Horário bloqueado'} (${formatTime(block.starts_at, timeZone)} - ${formatTime(block.ends_at, timeZone)})`}
                                >
                                    <div className="flex items-center justify-between gap-1">
                                        <span className="flex items-center gap-1 text-[10px] font-semibold text-slate-700 dark:text-slate-300">
                                            <Lock
                                                className="size-3 text-slate-500 dark:text-slate-400 shrink-0"
                                                aria-hidden="true"
                                            />
                                            {formatTime(
                                                block.starts_at,
                                                timeZone,
                                            )}
                                            –
                                            {formatTime(
                                                block.ends_at,
                                                timeZone,
                                            )}
                                        </span>
                                        <span className="rounded bg-slate-200 px-1 py-0.2 text-[9px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            Ocupado
                                        </span>
                                    </div>
                                    <p className="truncate text-[11px] font-medium text-slate-900 dark:text-slate-100">
                                        {block.reason ||
                                            'Ocupado'}
                                    </p>
                                    {block.professional?.name ? (
                                        <p className="truncate text-[10px] text-slate-600 dark:text-slate-400">
                                            {block.professional.name}
                                        </p>
                                    ) : null}
                                </button>
                            );
                        })}

                        {dayAppointments.map((appointment) => {
                            const start = asInstant(
                                appointment.starts_at,
                            );
                            const end = asInstant(
                                appointment.ends_at,
                            );
                            const { hour, minute } = zonedTimeParts(
                                appointment.starts_at,
                                timeZone,
                            );
                            const startMinutes = hour * 60 + minute;
                            const duration = Math.max(
                                30,
                                Math.round(
                                    (end.getTime() -
                                        start.getTime()) /
                                        60000,
                                ),
                            );
                            const style: CSSProperties = {
                                height: `${Math.max(44, (duration / 30) * slotHeight - 6)}px`,
                                top: `${((startMinutes - startHour * 60) / 30) * slotHeight + 3}px`,
                            };

                            return (
                                <div
                                    key={appointment.id}
                                    className="absolute inset-x-1 z-0 min-h-[44px]"
                                    style={style}
                                >
                                    <AppointmentCard
                                        appointment={appointment}
                                        compact
                                        onOpen={onOpen}
                                        timeZone={timeZone}
                                    />
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {completedSelection ? (
                <SelectionPopover
                    selection={completedSelection}
                    onCancel={() => setCompletedSelection(null)}
                    onSelectNew={(sel) => {
                        onDragSelect?.('appointment', sel);
                        setCompletedSelection(null);
                    }}
                    onSelectBlock={(sel) => {
                        onDragSelect?.('block', sel);
                        setCompletedSelection(null);
                    }}
                />
            ) : null}
        </section>
    );
}

export function MonthAgenda({
    appointments,
    onOpen,
    onOpenBlock,
    range,
    scheduleBlocks = [],
    timeZone,
}: {
    appointments: CalendarAppointment[];
    onOpen: (appointment: CalendarAppointment) => void;
    onOpenBlock?: (block: ScheduleBlock) => void;
    range: CalendarRange;
    scheduleBlocks?: ScheduleBlock[];
    timeZone?: string;
}) {
    const [activeCellIndex, setActiveCellIndex] = useState(0);
    const cellRefs = useRef<Record<string, HTMLDivElement | null>>({});
    const { month, year } = dateOnlyParts(range.start);
    const monthStart = `${year}-${String(month).padStart(2, '0')}-01`;
    const monthStartWeekday = new Date(
        Date.UTC(year, month - 1, 1),
    ).getUTCDay();
    const monthEnd = new Date(Date.UTC(year, month, 0)).getUTCDate();
    const dayCount = Math.ceil((monthStartWeekday + monthEnd) / 7) * 7;
    const dates = Array.from({ length: dayCount }, (_, index) =>
        addDays(monthStart, index - monthStartWeekday),
    );
    const rows = Array.from({ length: dates.length / 7 }, (_, rowIndex) =>
        dates.slice(rowIndex * 7, rowIndex * 7 + 7),
    );

    const focusCell = (index: number) => {
        const nextIndex = Math.max(0, Math.min(index, dates.length - 1));

        setActiveCellIndex(nextIndex);
        cellRefs.current[dates[nextIndex]]?.focus();
    };

    const handleCellKeyDown = (
        event: ReactKeyboardEvent<HTMLDivElement>,
        index: number,
        dayAppointments: CalendarAppointment[],
        dayBlocks: ScheduleBlock[],
    ) => {
        if (event.target !== event.currentTarget) {
            return;
        }

        const rowStart = Math.floor(index / 7) * 7;
        const rowEnd = Math.min(rowStart + 6, dates.length - 1);
        let nextIndex: number | null = null;

        switch (event.key) {
            case 'ArrowLeft':
                nextIndex = index - 1;
                break;
            case 'ArrowRight':
                nextIndex = index + 1;
                break;
            case 'ArrowUp':
                nextIndex = index - 7;
                break;
            case 'ArrowDown':
                nextIndex = index + 7;
                break;
            case 'Home':
                nextIndex = rowStart;
                break;
            case 'End':
                nextIndex = rowEnd;
                break;
            case 'Enter':
            case ' ':
                event.preventDefault();

                if (dayAppointments[0]) {
                    onOpen(dayAppointments[0]);
                } else if (dayBlocks[0]) {
                    onOpenBlock?.(dayBlocks[0]);
                }

                return;
            default:
                return;
        }

        if (nextIndex === null || nextIndex < 0 || nextIndex >= dates.length) {
            return;
        }

        event.preventDefault();
        focusCell(nextIndex);
    };

    return (
        <section
            className="surface-panel overflow-hidden"
            aria-label="Calendário mensal"
            role="grid"
        >
            <div
                role="row"
                className="grid grid-cols-7 border-b border-border bg-muted/25 text-center text-xs font-semibold text-muted-foreground"
            >
                {['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'].map(
                    (day) => (
                        <div
                            key={day}
                            role="columnheader"
                            className="border-r border-border px-2 py-3 last:border-r-0"
                        >
                            {day}
                        </div>
                    ),
                )}
            </div>
            <div role="rowgroup">
                {rows.map((rowDates, rowIndex) => (
                    <div
                        key={`week-${rowIndex}`}
                        role="row"
                        className="grid grid-cols-7"
                    >
                        {rowDates.map((date, dateIndex) => {
                            const index = rowIndex * 7 + dateIndex;
                            const dayAppointments = appointments.filter(
                                (appointment) =>
                                    dateKey(appointment.starts_at, timeZone) ===
                                    date,
                            );
                            const dayBlocks = scheduleBlocks.filter(
                                (block) =>
                                    block.status !== 'cancelled' &&
                                    dateKey(block.starts_at, timeZone) === date,
                            );

                            return (
                                <div
                                    key={date}
                                    role="gridcell"
                                    ref={(element) => {
                                        cellRefs.current[date] = element;
                                    }}
                                    tabIndex={
                                        activeCellIndex === index ? 0 : -1
                                    }
                                    aria-label={`${formatDay(date, timeZone)}: ${dayAppointments.length} agendamento${dayAppointments.length === 1 ? '' : 's'} e ${dayBlocks.length} bloqueio${dayBlocks.length === 1 ? '' : 's'}`}
                                    onFocus={() => setActiveCellIndex(index)}
                                    onKeyDown={(event) =>
                                        handleCellKeyDown(
                                            event,
                                            index,
                                            dayAppointments,
                                            dayBlocks,
                                        )
                                    }
                                    className="min-h-28 border-r border-b border-border p-2 outline-none last:border-r-0 focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset sm:min-h-32"
                                >
                                    <p className="text-xs font-semibold text-muted-foreground">
                                        {dateOnlyParts(date).day}
                                    </p>
                                    <div className="mt-2 grid gap-1">
                                        {dayBlocks.slice(0, 2).map((block) => (
                                            <button
                                                key={block.id}
                                                type="button"
                                                onClick={() =>
                                                    onOpenBlock?.(block)
                                                }
                                                className="flex w-full items-center gap-1 rounded border border-dashed border-amber-500/50 bg-amber-50/80 px-1.5 py-0.5 text-left text-[10px] font-medium text-amber-900 transition hover:bg-amber-100 dark:border-amber-700/60 dark:bg-amber-950/60 dark:text-amber-200"
                                            >
                                                <Lock
                                                    className="size-2.5 shrink-0 text-amber-700 dark:text-amber-400"
                                                    aria-hidden="true"
                                                />
                                                <span className="truncate">
                                                    {block.reason || 'Bloqueio'}
                                                </span>
                                            </button>
                                        ))}
                                        {dayAppointments
                                            .slice(0, 3)
                                            .map((appointment) => (
                                                <AppointmentCard
                                                    key={appointment.id}
                                                    appointment={appointment}
                                                    compact
                                                    onOpen={onOpen}
                                                    timeZone={timeZone}
                                                />
                                            ))}
                                        {dayAppointments.length > 3 ? (
                                            <p className="text-[10px] text-muted-foreground">
                                                +{dayAppointments.length - 3}{' '}
                                                outros
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ))}
            </div>
        </section>
    );
}

export function EmptyCalendar({
    action,
    description = 'A agenda aparecerá aqui quando houver atendimentos para este período.',
    title = 'Nenhum agendamento encontrado',
}: {
    action?: ReactNode;
    description?: string;
    title?: string;
}) {
    return (
        <div className="surface-panel flex min-h-64 flex-col items-center justify-center px-5 py-10 text-center">
            <div className="flex size-12 items-center justify-center rounded-2xl bg-secondary text-secondary-foreground">
                <CalendarDays className="size-6" aria-hidden="true" />
            </div>
            <h2 className="mt-4 text-base font-semibold">{title}</h2>
            <p className="mt-1 max-w-md text-sm leading-6 text-muted-foreground">
                {description}
            </p>
            {action ? <div className="mt-5">{action}</div> : null}
        </div>
    );
}

export function CalendarError({ message }: { message: string }) {
    return (
        <div
            className="surface-panel border-destructive/30 bg-destructive/5 px-4 py-5"
            role="alert"
        >
            <p className="font-semibold text-destructive">
                Não foi possível carregar a agenda
            </p>
            <p className="mt-1 text-sm text-muted-foreground">{message}</p>
        </div>
    );
}

export function CalendarLoading() {
    return (
        <div
            className="surface-panel grid min-h-64 place-items-center px-5 py-10"
            role="status"
            aria-busy="true"
        >
            <div className="w-full max-w-xl space-y-3">
                <span className="sr-only">Carregando agenda…</span>
                <div className="h-4 w-32 animate-pulse rounded bg-muted" />
                <div className="h-20 animate-pulse rounded-xl bg-muted/70" />
                <div className="h-20 animate-pulse rounded-xl bg-muted/50" />
            </div>
        </div>
    );
}

export function FilterSummary({
    professionals,
    statuses,
}: {
    professionals: CalendarOption[];
    statuses: string[];
}) {
    const count = professionals.length + statuses.length;

    return count > 0 ? (
        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <UserRound className="size-3.5" aria-hidden="true" />
            {count} filtro{count === 1 ? '' : 's'} aplicado
            {count === 1 ? '' : 's'}
        </span>
    ) : null;
}
