import { MoreHorizontal } from 'lucide-react';
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
            aria-label={`${formatTime(appointment.starts_at, timeZone)}, ${customerName}, ${serviceName}, ${statusLabel(appointment.status)}`}
        >
            <div className="flex items-start justify-between gap-1">
                <span className="text-3xs leading-none font-semibold">
                    {formatTime(appointment.starts_at, timeZone)}
                    {appointment.ends_at
                        ? `–${formatTime(appointment.ends_at, timeZone)}`
                        : null}
                </span>
                <MoreHorizontal
                    className="size-3.5 shrink-0 opacity-60 transition group-hover:opacity-100"
                    aria-hidden="true"
                />
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
