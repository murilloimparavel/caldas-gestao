import { Clock3 } from 'lucide-react';
import type { AppointmentSummary } from '../types';
import { DashboardPanel } from './dashboard-panel';
import { EmptyState } from './empty-state';

const statusLabel: Record<AppointmentSummary['status'], string> = {
    scheduled: 'Agendado',
    confirmed: 'Confirmado',
    checked_in: 'Aguardando',
    in_service: 'Em atendimento',
};

const statusClass: Record<AppointmentSummary['status'], string> = {
    scheduled: 'bg-muted text-muted-foreground',
    confirmed: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    checked_in:
        'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-200',
    in_service:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
};

export function NextAppointments({
    appointments,
}: {
    appointments: AppointmentSummary[];
}) {
    return (
        <DashboardPanel
            title="Próximos atendimentos"
            eyebrow="Agenda"
            description="Acompanhe os próximos horários e o status de cada atendimento."
        >
            {appointments.length > 0 ? (
                <ol className="divide-y divide-border">
                    {appointments.map((appointment) => (
                        <li
                            key={appointment.id}
                            className="grid min-h-20 grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3 gap-y-1 px-4 py-3 sm:grid-cols-[72px_minmax(0,1fr)_auto] sm:px-6"
                        >
                            <span className="flex items-center gap-1.5 font-mono text-sm font-semibold text-foreground">
                                <Clock3
                                    className="size-3.5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                {appointment.startsAt}
                            </span>
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-semibold text-foreground">
                                    {appointment.client}
                                </span>
                                <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                                    {appointment.service} ·{' '}
                                    {appointment.professional}
                                </span>
                            </span>
                            <span
                                className={`col-start-2 w-fit rounded-full px-2 py-1 text-[10px] font-semibold sm:col-start-auto sm:px-2.5 sm:text-xs ${statusClass[appointment.status] ?? statusClass.scheduled}`}
                            >
                                {statusLabel[appointment.status] ??
                                    statusLabel.scheduled}
                            </span>
                        </li>
                    ))}
                </ol>
            ) : (
                <EmptyState message="Nenhum próximo atendimento no período." />
            )}
        </DashboardPanel>
    );
}
