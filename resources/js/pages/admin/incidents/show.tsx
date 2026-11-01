import { Head, useForm, usePage } from '@inertiajs/react';
import { ClipboardCheck, History, ScanLine } from 'lucide-react';
import { PageCanvas } from '@/components/operational';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { AdminPageHeader } from '@/features/admin/components/admin-page-header';
import { index, update } from '@/routes/admin/incidents';
import type { SharedPageProps } from '@/types';
import { date, Execution, selectClass, Severity } from './shared';
import type { Incident, Options, Owner } from './shared';

type Detail = Incident & {
    frames: { file: string; line: number; column?: number }[];
    history: {
        id: string;
        event_type: string;
        from_status: string | null;
        to_status: string | null;
        reason: string | null;
        actor: string;
        owner?: string | null;
        created_at: string;
    }[];
};
const defaultReasons: Record<string, string> = {
    new: 'reopened',
    triaged: 'triage_confirmed',
    in_analysis: 'investigation_started',
    proposal_ready: 'proposal_recorded',
    in_validation: 'validation_started',
    resolved: 'fix_verified',
    ignored: 'not_actionable',
};
function WorkflowForm({
    incident,
    owners,
    options,
}: {
    incident: Incident;
    owners: Owner[];
    options: Options;
}) {
    const form = useForm({
        workflow_status: incident.workflow_status,
        owner_user_id: incident.owner_user_id ?? '',
        reason: 'owner_assigned',
        lock_version: incident.lock_version,
    });

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    owner_user_id: data.owner_user_id || null,
                }));
                form.patch(update.url(incident.id), { preserveScroll: true });
            }}
        >
            <label className="block space-y-2 text-sm font-medium">
                Acompanhamento
                <select
                    className={selectClass}
                    value={form.data.workflow_status}
                    onChange={(event) => {
                        form.setData('workflow_status', event.target.value);
                        form.setData(
                            'reason',
                            event.target.value === incident.workflow_status
                                ? 'owner_assigned'
                                : event.target.value === 'triaged' &&
                                    ['resolved', 'ignored'].includes(
                                        incident.workflow_status,
                                    )
                                  ? 'reopened'
                                  : (defaultReasons[event.target.value] ??
                                    'triage_confirmed'),
                        );
                    }}
                >
                    {Object.entries(options.workflow_status)
                        .filter(
                            ([value]) =>
                                value === incident.workflow_status ||
                                options.transitions[
                                    incident.workflow_status
                                ]?.includes(value),
                        )
                        .map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                </select>
            </label>
            <label className="block space-y-2 text-sm font-medium">
                Responsável
                <select
                    className={selectClass}
                    value={form.data.owner_user_id}
                    onChange={(event) =>
                        form.setData('owner_user_id', event.target.value)
                    }
                >
                    <option value="">Sem responsável</option>
                    {owners.map((owner) => (
                        <option key={owner.id} value={owner.id}>
                            {owner.name}
                        </option>
                    ))}
                </select>
            </label>
            <label className="block space-y-2 text-sm font-medium">
                Motivo do registro
                <select
                    className={selectClass}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                >
                    {Object.entries(options.reason)
                        .filter(([value]) =>
                            form.data.workflow_status ===
                            incident.workflow_status
                                ? value === 'owner_assigned'
                                : form.data.workflow_status === 'ignored'
                                  ? ['duplicate', 'not_actionable'].includes(
                                        value,
                                    )
                                  : value ===
                                    (form.data.workflow_status === 'triaged' &&
                                    ['resolved', 'ignored'].includes(
                                        incident.workflow_status,
                                    )
                                        ? 'reopened'
                                        : defaultReasons[
                                              form.data.workflow_status
                                          ]),
                        )
                        .map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                </select>
            </label>
            {Object.entries(form.errors).map(([field, error]) => (
                <p
                    key={field}
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {error}
                </p>
            ))}
            <p className="text-xs leading-5 text-muted-foreground">
                Concluir o diagnóstico automático não resolve o incidente. A
                resolução é registrada após a verificação da correção.
            </p>
            <Button
                className="w-full"
                type="submit"
                disabled={
                    form.processing ||
                    (form.data.workflow_status === incident.workflow_status &&
                        form.data.owner_user_id ===
                            (incident.owner_user_id ?? ''))
                }
            >
                Salvar acompanhamento
            </Button>
        </form>
    );
}
export default function IncidentDetail({
    incident,
    owners,
    options,
}: {
    incident: Detail;
    owners: Owner[];
    options: Options;
}) {
    const { flash } = usePage<SharedPageProps>().props;

    return (
        <>
            <Head title={incident.title || 'Detalhes do incidente'} />
            <PageCanvas className="gap-6">
                <AdminPageHeader
                    title={incident.title || 'Detalhes do incidente'}
                    description={
                        incident.impact_summary ||
                        'Registro central de acompanhamento técnico.'
                    }
                    backHref={index.url()}
                />
                {flash.error ? (
                    <p
                        role="alert"
                        className="rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
                    >
                        {flash.error}
                    </p>
                ) : null}
                {flash.success ? (
                    <p
                        role="status"
                        className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm"
                    >
                        {flash.success}
                    </p>
                ) : null}
                <div className="flex flex-wrap items-center gap-2">
                    <Severity value={incident.severity} options={options} />
                    <Badge variant="secondary">
                        {options.workflow_status[incident.workflow_status]}
                    </Badge>
                    <span className="text-sm text-muted-foreground">
                        {options.classification[incident.classification] ??
                            incident.classification}{' '}
                        · {options.module[incident.module] ?? incident.module}
                    </span>
                </div>
                <div className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="min-w-0 space-y-5">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <ScanLine
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Detecção e diagnóstico
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid grid-cols-1 gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                                    {[
                                        [
                                            'Ocorrências',
                                            String(incident.occurrences),
                                        ],
                                        [
                                            'Confiança da classificação',
                                            `${incident.confidence}%`,
                                        ],
                                        [
                                            'Primeira detecção',
                                            date(incident.first_seen_at),
                                        ],
                                        [
                                            'Última detecção',
                                            date(incident.last_seen_at),
                                        ],
                                        [
                                            'Rota identificada',
                                            incident.route_name ??
                                                'Não identificada',
                                        ],
                                        ['Origem', incident.detection_source],
                                        [
                                            'Exceção ou código',
                                            incident.exception_class,
                                        ],
                                        [
                                            'Tentativas automáticas',
                                            String(incident.attempts),
                                        ],
                                    ].map(([label, value]) => (
                                        <div key={label} className="min-w-0">
                                            <dt className="text-xs text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="mt-1 font-medium break-words">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                    <div className="min-w-0 sm:col-span-2">
                                        <dt className="text-xs text-muted-foreground">
                                            Versão detectada
                                        </dt>
                                        <dd className="mt-1 font-mono text-xs break-all">
                                            {incident.release}
                                        </dd>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs text-muted-foreground">
                                            Execução automática
                                        </dt>
                                        <dd className="mt-1">
                                            <Execution incident={incident} />
                                        </dd>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {incident.auto_diagnosis_eligible
                                                ? `Elegível após ${incident.diagnosis_threshold} ocorrências.`
                                                : 'Análise manual indicada para este sinal.'}
                                        </p>
                                    </div>
                                </dl>
                                <div className="mt-5 space-y-2 border-t pt-4">
                                    <h2 className="text-sm font-medium">
                                        Referências técnicas sanitizadas
                                    </h2>
                                    {incident.frames.length ? (
                                        incident.frames.map(
                                            (frame, position) => (
                                                <p
                                                    key={position}
                                                    className="rounded-lg bg-muted p-3 font-mono text-xs break-all"
                                                >
                                                    {frame.file}:{frame.line}
                                                    {frame.column
                                                        ? `:${frame.column}`
                                                        : ''}
                                                </p>
                                            ),
                                        )
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Nenhuma referência de código
                                            disponível.
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <History
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Histórico do incidente
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {incident.history.length ? (
                                    <ol className="divide-y">
                                        {incident.history.map((event) => (
                                            <li
                                                key={event.id}
                                                className="py-4 first:pt-0"
                                            >
                                                <p className="text-sm font-medium">
                                                    {event.to_status &&
                                                    options.workflow_status[
                                                        event.to_status
                                                    ]
                                                        ? options
                                                              .workflow_status[
                                                              event.to_status
                                                          ]
                                                        : ((
                                                              {
                                                                  detected:
                                                                      'Sinal detectado',
                                                                  execution_claim:
                                                                      'Diagnóstico iniciado',
                                                                  execution_finish:
                                                                      'Diagnóstico encerrado',
                                                              } as Record<
                                                                  string,
                                                                  string
                                                              >
                                                          )[event.event_type] ??
                                                          'Registro atualizado')}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {event.actor} ·{' '}
                                                    {date(event.created_at)}
                                                </p>
                                                {event.reason ===
                                                'owner_assigned' ? (
                                                    <p className="mt-2 text-xs text-muted-foreground">
                                                        Responsável:{' '}
                                                        {event.owner ??
                                                            'Sem responsável'}
                                                    </p>
                                                ) : null}
                                                {event.reason &&
                                                options.reason[event.reason] ? (
                                                    <p className="mt-2 text-xs">
                                                        {
                                                            options.reason[
                                                                event.reason
                                                            ]
                                                        }
                                                    </p>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ol>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Sem alterações de acompanhamento
                                        registradas.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                    <aside className="min-w-0">
                        <Card className="xl:sticky xl:top-5">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <ClipboardCheck
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Acompanhamento humano
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <WorkflowForm
                                    key={incident.lock_version}
                                    incident={incident}
                                    owners={owners}
                                    options={options}
                                />
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </PageCanvas>
        </>
    );
}
IncidentDetail.layout = {
    breadcrumbs: [{ title: 'Incidentes', href: index.url() }],
};
