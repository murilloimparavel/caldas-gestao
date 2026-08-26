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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
        <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="flex min-w-0 items-center gap-2">
                <Button
                    asChild
                    size="icon"
                    variant="outline"
                    aria-label="Período anterior"
                >
                    <Link
                        href={calendarIndex({
                            query: {
                                ...query,
                                date: previousDate,
                            },
                        })}
                    >
                        <ChevronLeft aria-hidden="true" />
                    </Link>
                </Button>
                <div className="min-w-0 px-1">
                    <p className="truncate text-lg font-semibold capitalize sm:text-xl">
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
                >
                    <Link
                        href={calendarIndex({
                            query: {
                                ...query,
                                date: nextDate,
                            },
                        })}
                    >
                        <ChevronRight aria-hidden="true" />
                    </Link>
                </Button>
                <Button
                    asChild
                    variant="ghost"
                    className="ml-1 hidden sm:inline-flex"
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

            <div className="flex flex-wrap items-center gap-2">
                <div
                    className="flex rounded-lg border border-border bg-muted/30 p-1"
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
                                className="h-8 px-2.5 text-xs"
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
                <Button variant="outline" onClick={onFilter}>
                    <Filter aria-hidden="true" />
                    Filtrar
                </Button>
                {canManage ? (
                    <div className="flex items-center gap-2">
                        {onCreateBlock ? (
                            <Button variant="outline" onClick={onCreateBlock}>
                                <Lock aria-hidden="true" />
                                Novo bloqueio
                            </Button>
                        ) : null}
                        <Button onClick={onCreate}>
                            <CalendarDays aria-hidden="true" />
                            Novo agendamento
                        </Button>
                    </div>
                ) : null}
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
            onClick={() => onOpen(appointment)}
            className={cn(
                'group w-full rounded-lg border p-2 text-left shadow-xs transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden',
                statusClasses[appointment.status] ??
                    'border-border bg-card text-card-foreground',
                compact ? 'min-h-20' : 'min-h-24',
            )}
            aria-label={`${formatTime(appointment.starts_at, timeZone)}, ${customerName}, ${serviceName}, ${statusLabel(appointment.status)}`}
        >
            <div className="flex items-start justify-between gap-2">
                <span className="text-[11px] font-semibold">
                    {formatTime(appointment.starts_at, timeZone)}
                    {appointment.ends_at
                        ? `–${formatTime(appointment.ends_at, timeZone)}`
                        : null}
                </span>
                <MoreHorizontal
                    className="size-3.5 opacity-60 transition group-hover:opacity-100"
                    aria-hidden="true"
                />
            </div>
            <p className="mt-1 truncate text-xs font-semibold">
                {customerName}
            </p>
            <p className="truncate text-[11px] opacity-80">{serviceName}</p>
            {!compact && appointment.professional?.name ? (
                <p className="mt-1 truncate text-[10px] opacity-70">
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
            onClick={() => onOpen?.(block)}
            className="group flex w-full items-start justify-between rounded-lg border border-dashed border-amber-500/60 bg-amber-50/75 p-3 text-left shadow-2xs transition hover:border-amber-600 hover:bg-amber-100/90 hover:shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200 dark:hover:bg-amber-900/60"
        >
            <div className="flex items-start gap-3">
                <div className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-amber-200/80 text-amber-900 dark:bg-amber-900 dark:text-amber-300">
                    <Lock className="size-3.5" aria-hidden="true" />
                </div>
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-amber-950 dark:text-amber-200">
                            {formatTime(block.starts_at, timeZone)} –{' '}
                            {formatTime(block.ends_at, timeZone)}
                        </span>
                        <Badge
                            variant="outline"
                            className="border-amber-400/60 bg-amber-100/80 text-[10px] text-amber-900 dark:border-amber-700 dark:bg-amber-900/60 dark:text-amber-300"
                        >
                            Bloqueio
                        </Badge>
                    </div>
                    <p className="mt-1 truncate text-sm font-medium text-amber-950 dark:text-amber-100">
                        {block.reason || 'Horário bloqueado / Pausa operacional'}
                    </p>
                    {block.professional?.name ? (
                        <p className="mt-0.5 truncate text-xs text-amber-900/80 dark:text-amber-300/80">
                            Profissional: {block.professional.name}
                        </p>
                    ) : (
                        <p className="mt-0.5 text-xs text-amber-900/80 dark:text-amber-300/80">
                            Aplica-se a toda a unidade
                        </p>
                    )}
                </div>
            </div>
            <MoreHorizontal
                className="size-4 shrink-0 text-amber-800 opacity-60 transition group-hover:opacity-100 dark:text-amber-300"
                aria-hidden="true"
            />
        </button>
    );
}

export function WeekCalendar({
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
    const dates = dayDates(range.start, 'week');
    const startHour = 7;
    const endHour = 21;
    const slotHeight = 44;
    const timelineHeight = (endHour - startHour) * 2 * slotHeight;
    const timeSlots = Array.from(
        { length: (endHour - startHour) * 2 },
        (_, index) => startHour * 60 + index * 30,
    );

    return (
        <div className="surface-panel overflow-hidden">
            <div className="overflow-x-auto">
                <div className="min-w-[920px]">
                    <div className="grid grid-cols-[4.5rem_repeat(7,minmax(8rem,1fr))] border-b border-border bg-muted/25">
                        <div
                            aria-hidden="true"
                            className="border-r border-border"
                        />
                        {dates.map((date) => (
                            <div
                                key={date}
                                className="border-r border-border px-3 py-3 last:border-r-0"
                            >
                                <p className="text-xs font-semibold text-muted-foreground capitalize">
                                    {formatDay(date, timeZone)}
                                </p>
                            </div>
                        ))}
                    </div>
                    <div className="grid grid-cols-[4.5rem_repeat(7,minmax(8rem,1fr))]">
                        <div
                            className="relative border-r border-border"
                            style={{ height: timelineHeight }}
                        >
                            {timeSlots.map((minutes) => (
                                <span
                                    key={minutes}
                                    className="absolute right-2 -translate-y-1/2 text-[10px] text-muted-foreground"
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
                        {dates.map((date) => {
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
                                    className="relative border-r border-border bg-[linear-gradient(to_bottom,transparent_43px,var(--border)_44px)] bg-size-[100%_44px] last:border-r-0"
                                    style={{ height: timelineHeight }}
                                >
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
                                            height: `${Math.max(28, (duration / 30) * slotHeight - 4)}px`,
                                            top: `${((startMinutes - startHour * 60) / 30) * slotHeight + 2}px`,
                                        };

                                        return (
                                            <button
                                                key={block.id}
                                                type="button"
                                                onClick={() => onOpenBlock?.(block)}
                                                className="group absolute inset-x-1 z-10 flex flex-col justify-between overflow-hidden rounded-md border border-dashed border-amber-500/60 bg-amber-100/85 p-1.5 text-left text-amber-950 shadow-2xs backdrop-blur-xs transition hover:border-amber-600 hover:bg-amber-200/90 hover:shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden dark:border-amber-600/70 dark:bg-amber-950/80 dark:text-amber-200 dark:hover:bg-amber-900/90"
                                                style={style}
                                                title={`Bloqueio: ${block.reason || 'Horário bloqueado'} (${formatTime(block.starts_at, timeZone)} - ${formatTime(block.ends_at, timeZone)})`}
                                            >
                                                <div className="flex items-center justify-between gap-1">
                                                    <span className="flex items-center gap-1 text-[10px] font-semibold text-amber-900 dark:text-amber-300">
                                                        <Lock
                                                            className="size-3 text-amber-700 dark:text-amber-400"
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
                                                    <span className="rounded bg-amber-200/70 px-1 py-0.2 text-[9px] font-medium text-amber-950 dark:bg-amber-900/90 dark:text-amber-200">
                                                        Bloqueio
                                                    </span>
                                                </div>
                                                <p className="truncate text-[11px] font-medium text-amber-950 dark:text-amber-100">
                                                    {block.reason ||
                                                        'Horário bloqueado'}
                                                </p>
                                                {block.professional?.name ? (
                                                    <p className="truncate text-[10px] text-amber-900/80 dark:text-amber-300/80">
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
                                                className="absolute inset-x-1 z-0"
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
        </div>
    );
}

export function DayAgenda({
    appointments,
    date,
    onOpen,
    onOpenBlock,
    scheduleBlocks = [],
    timeZone,
}: {
    appointments: CalendarAppointment[];
    date: string;
    onOpen: (appointment: CalendarAppointment) => void;
    onOpenBlock?: (block: ScheduleBlock) => void;
    scheduleBlocks?: ScheduleBlock[];
    timeZone?: string;
}) {
    const dayAppointments = appointments
        .filter(
            (appointment) => dateKey(appointment.starts_at, timeZone) === date,
        )
        .sort((left, right) => left.starts_at.localeCompare(right.starts_at));

    const dayBlocks = scheduleBlocks
        .filter(
            (block) =>
                block.status !== 'cancelled' &&
                dateKey(block.starts_at, timeZone) === date,
        )
        .sort((left, right) => left.starts_at.localeCompare(right.starts_at));

    return (
        <section
            className="surface-panel p-4 sm:p-5"
            aria-labelledby="day-agenda-title"
        >
            <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2
                        id="day-agenda-title"
                        className="text-base font-semibold"
                    >
                        {formatDay(date, timeZone)}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Atendimentos e bloqueios do dia
                    </p>
                </div>
                <Clock3
                    className="size-5 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>

            {dayBlocks.length > 0 ? (
                <div className="mb-4 space-y-2">
                    <p className="text-xs font-semibold tracking-wider text-amber-800 uppercase dark:text-amber-400">
                        Bloqueios / Pausas programadas
                    </p>
                    <div className="grid gap-2">
                        {dayBlocks.map((block) => (
                            <ScheduleBlockCard
                                key={block.id}
                                block={block}
                                onOpen={onOpenBlock}
                                timeZone={timeZone}
                            />
                        ))}
                    </div>
                </div>
            ) : null}

            {dayAppointments.length > 0 ? (
                <div className="space-y-2">
                    {dayBlocks.length > 0 ? (
                        <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Agendamentos
                        </p>
                    ) : null}
                    <div className="grid gap-2">
                        {dayAppointments.map((appointment) => (
                            <AppointmentCard
                                key={appointment.id}
                                appointment={appointment}
                                onOpen={onOpen}
                                timeZone={timeZone}
                            />
                        ))}
                    </div>
                </div>
            ) : dayBlocks.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border bg-muted/30 px-4 py-8 text-center">
                    <CalendarDays
                        className="mx-auto size-6 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p className="mt-2 text-sm font-medium">
                        Dia livre por enquanto
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Nenhum agendamento ou bloqueio combina com os filtros.
                    </p>
                </div>
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
