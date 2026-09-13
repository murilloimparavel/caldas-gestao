import { AlertCircle, FileText, Globe, MoreHorizontal } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { CalendarAppointment } from '@/types/calendar';
import { formatTime } from './date-utils';
import { statusClasses, statusLabel } from './status-chip';

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

    const notesContent =
        appointment.notes?.trim() ||
        appointment.customer?.notes?.trim() ||
        appointment.description?.trim() ||
        null;
    const hasNotes = Boolean(notesContent);
    const isAlertNote = /alerg|cuidado|medic|atenç|restriç|alerta/i.test(
        notesContent ?? '',
    );
    const NoteIcon = isAlertNote ? AlertCircle : FileText;

    const isOnline =
        appointment.source === 'online' ||
        Boolean(appointment.online_booking) ||
        Boolean(appointment.online_booking_campaign_link_id);

    const fullAriaLabel = [
        formatTime(appointment.starts_at, timeZone),
        appointment.ends_at
            ? `até ${formatTime(appointment.ends_at, timeZone)}`
            : null,
        customerName,
        serviceName,
        statusLabel(appointment.status),
        isOnline ? 'Agendamento online' : null,
        hasNotes ? `Observação: ${notesContent}` : null,
    ]
        .filter(Boolean)
        .join(', ');

    return (
        <button
            type="button"
            onClick={(e) => {
                e.stopPropagation();
                onOpen(appointment);
            }}
            onMouseDown={(e) => e.stopPropagation()}
            onPointerDown={(e) => e.stopPropagation()}
            onTouchStart={(e) => e.stopPropagation()}
            className={cn(
                'group w-full rounded-lg border p-2 text-left shadow-xs transition hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden',
                statusClasses[appointment.status] ??
                    'border-border bg-card text-card-foreground',
                compact ? 'h-full min-h-16' : 'min-h-24',
            )}
            aria-label={fullAriaLabel}
        >
            <div className="flex items-start justify-between gap-1">
                <span className="text-3xs leading-none font-semibold">
                    {formatTime(appointment.starts_at, timeZone)}
                    {appointment.ends_at
                        ? `–${formatTime(appointment.ends_at, timeZone)}`
                        : null}
                </span>
                <div className="flex items-center gap-1">
                    {isOnline ? (
                        <span
                            className={cn(
                                'inline-flex items-center gap-0.5 rounded bg-sky-500/15 px-1 py-0.5 font-semibold text-sky-700 dark:bg-sky-500/25 dark:text-sky-300',
                                compact ? 'text-[9px]' : 'text-3xs',
                            )}
                            title="Agendamento online"
                        >
                            <Globe
                                className="size-2.5 shrink-0"
                                aria-hidden="true"
                            />
                            {!compact ? <span>Online</span> : null}
                            <span className="sr-only">Agendamento online</span>
                        </span>
                    ) : null}
                    {hasNotes ? (
                        <span
                            className={cn(
                                'inline-flex items-center',
                                isAlertNote
                                    ? 'text-destructive dark:text-red-400'
                                    : 'text-amber-600 dark:text-amber-400',
                            )}
                            title={`Observação: ${notesContent}`}
                        >
                            <NoteIcon
                                className="size-3 shrink-0"
                                aria-hidden="true"
                            />
                            <span className="sr-only">
                                Observação: {notesContent}
                            </span>
                        </span>
                    ) : null}
                    <MoreHorizontal
                        className="size-3.5 shrink-0 opacity-60 transition group-hover:opacity-100"
                        aria-hidden="true"
                    />
                </div>
            </div>
            <p className="mt-1 truncate text-xs leading-snug font-bold">
                {customerName}
            </p>
            <p className="truncate text-3xs opacity-90">{serviceName}</p>
            {!compact && appointment.professional?.name ? (
                <p className="mt-1 truncate text-2xs opacity-75">
                    {appointment.professional.name}
                </p>
            ) : null}
        </button>
    );
}
