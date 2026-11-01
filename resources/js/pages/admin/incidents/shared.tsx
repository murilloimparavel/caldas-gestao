import { Badge } from '@/components/ui/badge';

export type Options = Record<
    'classification' | 'module' | 'severity' | 'workflow_status' | 'reason',
    Record<string, string>
> & { transitions: Record<string, string[]> };
export type Owner = { id: string; name: string };
export type Incident = {
    id: string;
    title: string;
    classification: string;
    module: string;
    severity: string;
    confidence: number;
    impact_summary: string;
    workflow_status: string;
    owner_user_id: string | null;
    owner: Owner | null;
    lock_version: number;
    detection_source: string;
    release: string;
    occurrences: number;
    status: string;
    result: string | null;
    attempts: number;
    first_seen_at: string;
    last_seen_at: string;
    resolved_at: string | null;
    category: string;
    exception_class: string;
    route_name: string | null;
    auto_diagnosis_eligible: boolean;
    diagnosis_threshold: number;
    http_status: number | null;
};
export const selectClass =
    'h-10 w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-2 focus-visible:outline-primary';
export function date(value: string | null): string {
    if (!value) {
        return 'Não informado';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}
export function Severity({
    value,
    options,
}: {
    value: string;
    options: Options;
}) {
    return (
        <Badge
            variant="outline"
            className={
                value === 'critical'
                    ? 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300'
                    : value === 'high'
                      ? 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200'
                      : 'text-muted-foreground'
            }
        >
            {options.severity[value] ?? value}
        </Badge>
    );
}
export function Execution({ incident }: { incident: Incident }) {
    const labels: Record<string, string> = {
        pending: 'Aguardando execução',
        running: 'Em execução',
        completed: 'Preparação concluída',
        failed: 'Execução encerrada',
    };

    return (
        <span>
            {labels[incident.status] ?? incident.status}
            {incident.result === 'blocked' ? ' · Sem proposta automática' : ''}
        </span>
    );
}
