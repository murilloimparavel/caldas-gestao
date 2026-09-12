import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { AppointmentStatus } from '@/types/calendar';

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

export const statusClasses: Record<string, string> = {
    cancelled: 'border-destructive/30 bg-destructive/10 text-destructive',
    checked_in:
        'border-cyan-300 bg-cyan-50 text-cyan-800 dark:border-cyan-800 dark:bg-cyan-950/40 dark:text-cyan-200',
    completed: 'border-success/30 bg-success/10 text-success',
    confirmed: 'border-info/30 bg-info/10 text-info',
    draft: 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
    in_service:
        'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-200',
    no_show:
        'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-800 dark:bg-orange-950/40 dark:text-orange-200',
    scheduled: 'border-warning/30 bg-warning/10 text-warning',
};

export function statusLabel(status: AppointmentStatus): string {
    return statusLabels[status] ?? status.replaceAll('_', ' ');
}

export function StatusChip({ status }: { status: AppointmentStatus }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-full px-2 py-0.5 text-2xs font-semibold',
                statusClasses[status] ??
                    'border-border bg-muted text-muted-foreground',
            )}
        >
            {statusLabel(status)}
        </Badge>
    );
}
