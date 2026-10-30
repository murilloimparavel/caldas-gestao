import { Head, Link, useForm } from '@inertiajs/react';
import {
    Activity,
    ArrowUpRight,
    CircleCheck,
    Radar,
    ShieldAlert,
} from 'lucide-react';
import { EmptyState, PageCanvas, Pagination } from '@/components/operational';
import type { Paginated } from '@/components/operational';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { AdminPageHeader } from '@/features/admin/components/admin-page-header';
import { index, show } from '@/routes/admin/incidents';
import { date, Execution, selectClass, Severity } from './shared';
import type { Incident, Options } from './shared';

type Filters = {
    classification?: string;
    module?: string;
    severity?: string;
    workflow_status?: string;
    period?: string;
};
type Executor = {
    status: string;
    stale: boolean;
    last_seen_at: string | null;
    last_healthy_at: string | null;
    checks: { key: string; label: string; status: string }[];
};
type Props = {
    incidents: Paginated<Incident>;
    metrics: {
        total: number;
        open: number;
        critical: number;
        resolved: number;
    };
    filters: Filters;
    options: Options;
    executor?: Executor;
};
function ExecutorStatus({ executor }: { executor?: Executor }) {
    const statuses: Record<string, string> = {
        healthy: 'Monitorado e disponível',
        degraded: 'Verificação operacional necessária',
        paused: 'Execução pausada',
        stale: 'Sem sinal recente do monitor',
    };

    return (
        <Card
            className={
                executor?.status === 'healthy'
                    ? 'border-emerald-500/30'
                    : 'border-amber-500/40'
            }
        >
            <CardContent className="space-y-4 p-4 sm:p-5">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div>
                        <h2 className="flex items-center gap-2 text-sm font-semibold">
                            <Activity className="size-4" aria-hidden="true" />
                            Executor de diagnóstico
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {statuses[executor?.status ?? 'unknown'] ??
                                'Ainda não monitorado'}
                        </p>
                    </div>
                    <div className="space-y-1 text-xs text-muted-foreground">
                        <p>
                            Último sinal: {date(executor?.last_seen_at ?? null)}
                        </p>
                        <p>
                            Último estado saudável:{' '}
                            {date(executor?.last_healthy_at ?? null)}
                        </p>
                    </div>
                </div>
                {executor?.checks.length ? (
                    <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        {executor.checks.map((check) => (
                            <li
                                key={check.key}
                                className="flex min-w-0 items-center justify-between gap-2 rounded-lg bg-muted/50 px-3 py-2 text-xs"
                            >
                                <span>{check.label}</span>
                                <span
                                    className={
                                        check.status === 'ok' && !executor.stale
                                            ? 'text-emerald-700 dark:text-emerald-300'
                                            : 'text-muted-foreground'
                                    }
                                >
                                    {(
                                        {
                                            ok: executor.stale
                                                ? 'Desatualizado'
                                                : 'OK',
                                            missing: 'Ausente',
                                            error: 'Indisponível',
                                            paused: 'Pausado',
                                            stale: 'Desatualizado',
                                            unknown: 'Não verificado',
                                        } as Record<string, string>
                                    )[check.status] ?? 'Não verificado'}
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        O estado será apresentado após o primeiro relatório
                        operacional.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export default function IncidentIndex({
    incidents,
    metrics,
    filters,
    options,
    executor,
}: Props) {
    const form = useForm({
        classification: filters.classification ?? '',
        module: filters.module ?? '',
        severity: filters.severity ?? '',
        workflow_status: filters.workflow_status ?? '',
        period: filters.period ?? '30',
    });

    return (
        <>
            <Head title="Central de incidentes" />
            <PageCanvas className="gap-6">
                <AdminPageHeader
                    title="Central de incidentes"
                    description="Acompanhe sinais do sistema, priorize a análise e registre a resolução com revisão humana."
                />
                <ExecutorStatus executor={executor} />
                <section
                    aria-label="Resumo dos incidentes"
                    className="grid grid-cols-2 gap-3 xl:grid-cols-4"
                >
                    {[
                        {
                            label: 'No período',
                            value: metrics.total,
                            icon: Activity,
                        },
                        {
                            label: 'Em acompanhamento',
                            value: metrics.open,
                            icon: Radar,
                        },
                        {
                            label: 'Alta prioridade',
                            value: metrics.critical,
                            icon: ShieldAlert,
                        },
                        {
                            label: 'Resolvidos',
                            value: metrics.resolved,
                            icon: CircleCheck,
                        },
                    ].map(({ label, value, icon: Icon }) => (
                        <Card key={label}>
                            <CardContent className="p-4 sm:p-5">
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <Icon
                                        className="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span className="text-xs sm:text-sm">
                                        {label}
                                    </span>
                                </div>
                                <p className="mt-3 text-3xl font-semibold tracking-tight tabular-nums">
                                    {value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </section>
                <Card>
                    <CardContent className="p-4 sm:p-5">
                        <form
                            className="grid min-w-0 grid-cols-1 items-end gap-3 sm:grid-cols-2 xl:grid-cols-6"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.get(index.url(), {
                                    preserveState: true,
                                    preserveScroll: true,
                                });
                            }}
                        >
                            {(
                                [
                                    'classification',
                                    'module',
                                    'severity',
                                    'workflow_status',
                                ] as const
                            ).map((field) => (
                                <label
                                    key={field}
                                    className="min-w-0 space-y-1.5 text-xs font-medium"
                                >
                                    {
                                        {
                                            classification: 'Classificação',
                                            module: 'Módulo',
                                            severity: 'Gravidade',
                                            workflow_status: 'Acompanhamento',
                                        }[field]
                                    }
                                    <select
                                        className={selectClass}
                                        value={form.data[field]}
                                        onChange={(event) =>
                                            form.setData(
                                                field,
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Todos</option>
                                        {Object.entries(options[field]).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </label>
                            ))}
                            <label className="space-y-1.5 text-xs font-medium">
                                Período
                                <select
                                    className={selectClass}
                                    value={form.data.period}
                                    onChange={(event) =>
                                        form.setData(
                                            'period',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {[
                                        ['1', 'Últimas 24 horas'],
                                        ['7', 'Últimos 7 dias'],
                                        ['30', 'Últimos 30 dias'],
                                        ['90', 'Últimos 90 dias'],
                                    ].map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Button type="submit" disabled={form.processing}>
                                Filtrar incidentes
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                {incidents.data.length === 0 ? (
                    <EmptyState
                        icon={Radar}
                        title="Nenhum incidente neste recorte"
                        description="Novos sinais aparecerão aqui. Ajuste os filtros para consultar outros períodos."
                    />
                ) : (
                    <section
                        aria-label="Lista de incidentes"
                        className="space-y-3"
                    >
                        {incidents.data.map((incident) => (
                            <Card key={incident.id} className="overflow-hidden">
                                <CardContent className="p-4 sm:p-5">
                                    <div className="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div className="min-w-0 space-y-3">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Severity
                                                    value={incident.severity}
                                                    options={options}
                                                />
                                                <Badge variant="secondary">
                                                    {
                                                        options.workflow_status[
                                                            incident
                                                                .workflow_status
                                                        ]
                                                    }
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {options.module[
                                                        incident.module
                                                    ] ?? incident.module}
                                                </span>
                                            </div>
                                            <Link
                                                href={show.url(incident.id)}
                                                className="group flex items-start gap-2 text-lg font-semibold tracking-tight hover:text-primary"
                                            >
                                                <span className="break-words">
                                                    {incident.title ||
                                                        'Incidente técnico'}
                                                </span>
                                                <ArrowUpRight
                                                    className="mt-1 size-4 shrink-0"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                            <p className="max-w-3xl text-sm leading-6 text-muted-foreground">
                                                {incident.impact_summary}
                                            </p>
                                            <div className="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                                                <span>
                                                    {incident.occurrences}{' '}
                                                    ocorrências
                                                </span>
                                                <span>
                                                    Primeiro:{' '}
                                                    {date(
                                                        incident.first_seen_at,
                                                    )}
                                                </span>
                                                <span>
                                                    Último:{' '}
                                                    {date(
                                                        incident.last_seen_at,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="shrink-0 space-y-2 text-xs lg:max-w-64 lg:text-right">
                                            <p>
                                                {incident.owner?.name ??
                                                    'Sem responsável'}
                                            </p>
                                            <p className="text-muted-foreground">
                                                <Execution
                                                    incident={incident}
                                                />
                                            </p>
                                            <p className="font-mono text-muted-foreground">
                                                Versão{' '}
                                                {incident.release.slice(0, 12)}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                )}
                <Pagination paginated={incidents} />
            </PageCanvas>
        </>
    );
}
IncidentIndex.layout = {
    breadcrumbs: [{ title: 'Incidentes', href: index.url() }],
};
