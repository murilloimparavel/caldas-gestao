import { Clock3 } from 'lucide-react';
import type { AppointmentSummary } from '../types';

const statusLabel: Record<AppointmentSummary['status'], string> = {
    confirmed: 'Confirmado',
    waiting: 'Aguardando',
    'in-service': 'Em atendimento',
};

const statusClass: Record<AppointmentSummary['status'], string> = {
    confirmed: 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
    waiting: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-200',
    'in-service':
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
};

export function NextAppointments({
    appointments,
}: {
    appointments: AppointmentSummary[];
}) {
    return (
        <section
            className="surface-panel min-w-0 overflow-hidden"
            aria-labelledby="appointments-title"
        >
            <header className="border-b border-border px-5 py-4 sm:px-6">
                <div>
                    <p className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                        Agenda
                    </p>
                    <h2
                        id="appointments-title"
                        className="mt-1 text-lg font-semibold tracking-tight text-foreground"
                    >
                        Próximos atendimentos
                    </h2>
                </div>
            </header>
            {appointments.length > 0 ? (
                <ol className="divide-y divide-border">
                    {appointments.map((appointment) => (
                        <li
                            key={appointment.id}
                            className="grid min-h-20 grid-cols-[56px_minmax(0,1fr)_auto] items-center gap-3 px-5 py-3 sm:grid-cols-[72px_minmax(0,1fr)_auto] sm:px-6"
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
                                className={`hidden rounded-full px-2.5 py-1 text-xs font-semibold sm:inline-flex ${statusClass[appointment.status]}`}
                            >
                                {statusLabel[appointment.status]}
                            </span>
                        </li>
                    ))}
                </ol>
            ) : (
                <p className="px-5 py-8 text-sm text-muted-foreground sm:px-6">
                    Nenhum atendimento disponível ainda. A agenda conectada
                    aparecerá nesta área.
                </p>
            )}
        </section>
    );
}
