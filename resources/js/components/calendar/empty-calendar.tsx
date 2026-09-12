import { CalendarDays, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';
import type { CalendarOption } from '@/types/calendar';

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
