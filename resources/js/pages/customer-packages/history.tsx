import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    CircleAlert,
    History,
    Receipt,
    RotateCcw,
    Scissors,
    UserRound,
    XCircle,
} from 'lucide-react';
import {
    EmptyState,
    PageCanvas,
    Pagination,
    ResourceHeader,
} from '@/components/operational';
import type { Paginated, RelationOption } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import customerPackageActions from '@/actions/App/Http/Controllers/CustomerPackageController';
import customers from '@/routes/customers';
import sales from '@/routes/sales';
import closingSessions from '@/routes/closing-sessions';
import type { SharedPageProps } from '@/types';

type PackageSummary = {
    created_at: string | null;
    customer: {
        id: string;
        name: string;
        phone: string | null;
    };
    expires_at: string | null;
    id: string;
    name: string;
    remaining_sessions: number;
    status: string;
    total_sessions: number;
    balance_integrity: {
        message: string;
        next_step: string;
        services: {
            allocated: number;
            consumed: number;
            delta: number | null;
            expected_remaining: number;
            name: string;
            recorded_remaining: number | null;
        }[];
        status: 'consistent' | 'review' | 'inconclusive';
    };
};

type Usage = {
    closings: {
        closed_by: { id: string; name: string } | null;
        id: string;
        recorded_at: string | null;
        status: string;
    }[];
    consumed_at: string | null;
    id: string;
    reversal_reason: string | null;
    reversed_at: string | null;
    reversed_by_user: { id: string; name: string } | null;
    sale: {
        created_at: string | null;
        id: string;
        reference_label: string | null;
        status: string;
    } | null;
    sale_item: {
        covered_quantity: number;
        id: string;
        name_snapshot: string;
        quantity: number;
    } | null;
    service: RelationOption | null;
    sessions_consumed: number;
    status: 'consumed' | 'reversed';
    user: { id: string; name: string } | null;
    origin:
        | {
              appointment: {
                  ends_at: string | null;
                  id: string;
                  professional: { id: string; name: string } | null;
                  starts_at: string | null;
                  status: string;
              };
              label: string;
              type: 'appointment';
          }
        | { label: string; type: 'sale' | 'none' };
};

type Props = {
    canViewClosingSessions: boolean;
    canViewSales: boolean;
    customerPackage: PackageSummary;
    filters: {
        from: string;
        service_id: string;
        status: 'all' | 'consumed' | 'reversed';
        to: string;
    };
    services: RelationOption[];
    serviceTotals: {
        consumed_sessions: number;
        id: string | null;
        name: string;
        reversed_sessions: number;
        sessions: number;
    }[];
    totals: {
        consumed_sessions: number;
        records: number;
        reversed_sessions: number;
        sessions: number;
    };
    usages: Paginated<Usage>;
};

function formatDateTime(value: string | null, timeZone: string): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone,
    });
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value + 'T00:00:00').toLocaleDateString('pt-BR');
}

function statusLabel(status: Usage['status']): string {
    return status === 'reversed' ? 'Revertido' : 'Consumido';
}

export default function CustomerPackageHistory({
    canViewClosingSessions,
    canViewSales,
    customerPackage,
    filters,
    services,
    serviceTotals,
    totals,
    usages,
}: Props) {
    const { props } = usePage<SharedPageProps>();
    const timeZone =
        props.workspace?.activeUnit?.timezone ??
        props.workspace?.tenant.timezone ??
        'UTC';
    const historyUrl = customerPackageActions.history.url(customerPackage.id);
    const balanceNeedsReview =
        customerPackage.balance_integrity.status !== 'consistent';
    const servicesToReview = customerPackage.balance_integrity.services.filter(
        (service) => service.delta !== 0,
    );

    return (
        <>
            <Head title={'Histórico · ' + customerPackage.name} />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Auditoria de pacote"
                    title="Histórico de consumo"
                    description={
                        'Consumos registrados para ' +
                        customerPackage.name +
                        '.'
                    }
                    backHref={customers.show(customerPackage.customer.id).url}
                    backLabel={'Voltar para ' + customerPackage.customer.name}
                    status={
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {customerPackage.remaining_sessions} de{' '}
                                {customerPackage.total_sessions} sessões
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                                Adquirido em{' '}
                                {formatDate(
                                    customerPackage.created_at?.slice(0, 10) ??
                                        null,
                                )}
                                {customerPackage.expires_at
                                    ? ' · válido até ' +
                                      formatDate(customerPackage.expires_at)
                                    : ' · sem validade'}
                            </span>
                        </div>
                    }
                    action={
                        <Button asChild variant="outline">
                            <Link
                                href={customers.show(
                                    customerPackage.customer.id,
                                )}
                            >
                                <ArrowLeft aria-hidden="true" />
                                Ver cliente
                            </Link>
                        </Button>
                    }
                />

                <section
                    aria-label="Leitura do saldo"
                    className="surface-panel flex items-start gap-3 p-4"
                >
                    {balanceNeedsReview ? (
                        <CircleAlert
                            aria-hidden="true"
                            className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-300"
                        />
                    ) : (
                        <CheckCircle2
                            aria-hidden="true"
                            className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-300"
                        />
                    )}
                    <div className="min-w-0 space-y-2 text-sm">
                        <p className="font-medium text-foreground">
                            Conferência dos saldos por serviço
                        </p>
                        <p
                            className={
                                balanceNeedsReview
                                    ? 'font-medium text-amber-800 dark:text-amber-200'
                                    : 'text-muted-foreground'
                            }
                        >
                            {customerPackage.balance_integrity.message}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {customerPackage.balance_integrity.next_step}
                        </p>
                        {balanceNeedsReview && servicesToReview.length > 0 && (
                            <ul className="mt-3 divide-y divide-amber-200 rounded-lg border border-amber-200 bg-background/60 dark:divide-amber-900 dark:border-amber-900">
                                {servicesToReview.map((service, index) => (
                                    <li
                                        key={`${service.name}-${index}`}
                                        className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-3 py-2 text-xs"
                                    >
                                        <span className="font-medium text-foreground">
                                            {service.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            Esperado pelo histórico:{' '}
                                            <strong className="text-foreground">
                                                {service.expected_remaining}
                                            </strong>{' '}
                                            · Saldo registrado:{' '}
                                            <strong className="text-foreground">
                                                {service.recorded_remaining ??
                                                    'ausente'}
                                            </strong>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>

                <section
                    aria-label="Resumo do histórico"
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <article className="surface-panel flex items-center gap-3 p-4">
                        <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <History aria-hidden="true" className="size-5" />
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Registros filtrados
                            </p>
                            <p className="text-xl font-semibold text-foreground">
                                {totals.records}
                            </p>
                        </div>
                    </article>
                    <article className="surface-panel flex items-center gap-3 p-4">
                        <div className="flex size-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-300">
                            <Scissors aria-hidden="true" className="size-5" />
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Sessões no filtro
                            </p>
                            <p className="text-xl font-semibold text-foreground">
                                {totals.sessions}
                            </p>
                        </div>
                    </article>
                    <article className="surface-panel flex items-center gap-3 p-4">
                        <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                            <CheckCircle2
                                aria-hidden="true"
                                className="size-5"
                            />
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Consumo efetivo
                            </p>
                            <p className="text-xl font-semibold text-foreground">
                                {totals.consumed_sessions}
                            </p>
                        </div>
                    </article>
                    <article className="surface-panel flex items-center gap-3 p-4">
                        <div className="flex size-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300">
                            <RotateCcw aria-hidden="true" className="size-5" />
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Sessões revertidas
                            </p>
                            <p className="text-xl font-semibold text-foreground">
                                {totals.reversed_sessions}
                            </p>
                        </div>
                    </article>
                </section>

                {serviceTotals.length > 0 && (
                    <section
                        aria-label="Totais por serviço"
                        className="surface-panel overflow-hidden"
                    >
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="font-semibold text-foreground">
                                Totais por serviço
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Soma de todos os registros que atendem aos
                                filtros atuais, incluindo páginas que não estão
                                visíveis.
                            </p>
                        </div>
                        <div className="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                            {serviceTotals.map((service) => (
                                <article
                                    key={service.id ?? 'unknown'}
                                    className="rounded-xl border border-border bg-muted/20 p-4"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <h3 className="flex items-center gap-1.5 font-medium text-foreground">
                                            <Scissors
                                                aria-hidden="true"
                                                className="size-4 text-muted-foreground"
                                            />
                                            {service.name}
                                        </h3>
                                        <Badge variant="secondary">
                                            {service.sessions}{' '}
                                            {service.sessions === 1
                                                ? 'sessão'
                                                : 'sessões'}
                                        </Badge>
                                    </div>
                                    <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Efetivas
                                            </dt>
                                            <dd className="font-semibold text-emerald-700 dark:text-emerald-300">
                                                {service.consumed_sessions}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Revertidas
                                            </dt>
                                            <dd className="font-semibold text-amber-700 dark:text-amber-300">
                                                {service.reversed_sessions}
                                            </dd>
                                        </div>
                                    </dl>
                                </article>
                            ))}
                        </div>
                    </section>
                )}

                <form
                    action={historyUrl}
                    method="get"
                    className="surface-panel grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1.3fr_1fr_auto]"
                >
                    <label className="grid gap-1.5 text-sm font-medium text-foreground">
                        A partir de
                        <Input
                            type="date"
                            name="from"
                            defaultValue={filters.from}
                        />
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium text-foreground">
                        Até
                        <Input
                            type="date"
                            name="to"
                            defaultValue={filters.to}
                        />
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium text-foreground">
                        Serviço
                        <select
                            name="service_id"
                            defaultValue={filters.service_id}
                            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="">Todos os serviços</option>
                            {services.map((service) => (
                                <option key={service.id} value={service.id}>
                                    {service.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium text-foreground">
                        Situação
                        <select
                            name="status"
                            defaultValue={filters.status}
                            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="all">Todos</option>
                            <option value="consumed">Consumidos</option>
                            <option value="reversed">Revertidos</option>
                        </select>
                    </label>
                    <div className="flex items-end gap-2">
                        <Button type="submit">Aplicar</Button>
                        <Button asChild type="button" variant="ghost">
                            <Link href={historyUrl}>Limpar</Link>
                        </Button>
                    </div>
                </form>

                {usages.data.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title="Nenhum consumo encontrado"
                        description="Ajuste os filtros ou aguarde o primeiro consumo deste pacote."
                    />
                ) : (
                    <section
                        aria-label="Registros de consumo"
                        className="surface-panel overflow-hidden"
                    >
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="font-semibold text-foreground">
                                Registros de consumo
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Ordenados do mais recente para o mais antigo.
                                Datas no fuso {timeZone}.
                            </p>
                        </div>
                        <div className="divide-y divide-border">
                            {usages.data.map((usage) => (
                                <article
                                    key={usage.id}
                                    className="grid gap-5 px-5 py-5 lg:grid-cols-[1.05fr_1.1fr_1.35fr_1.45fr_1.2fr]"
                                >
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            {usage.status === 'reversed' ? (
                                                <XCircle
                                                    aria-hidden="true"
                                                    className="size-4 text-amber-600"
                                                />
                                            ) : (
                                                <CheckCircle2
                                                    aria-hidden="true"
                                                    className="size-4 text-emerald-600"
                                                />
                                            )}
                                            <span className="font-medium text-foreground">
                                                {statusLabel(usage.status)}
                                            </span>
                                            <Badge
                                                variant={
                                                    usage.status === 'reversed'
                                                        ? 'outline'
                                                        : 'secondary'
                                                }
                                            >
                                                {usage.sessions_consumed}{' '}
                                                {usage.sessions_consumed === 1
                                                    ? 'sessão'
                                                    : 'sessões'}
                                            </Badge>
                                        </div>
                                        <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                            <CalendarDays
                                                aria-hidden="true"
                                                className="size-3.5"
                                            />
                                            {formatDateTime(
                                                usage.consumed_at,
                                                timeZone,
                                            )}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Data do registro do consumo
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                                            Serviço e item
                                        </p>
                                        <p className="mt-1 flex items-center gap-1.5 text-sm text-foreground">
                                            <Scissors
                                                aria-hidden="true"
                                                className="size-3.5 text-muted-foreground"
                                            />
                                            {usage.service?.name ??
                                                'Não informado'}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            Item da comanda:{' '}
                                            <span className="text-foreground">
                                                {usage.sale_item
                                                    ?.name_snapshot ??
                                                    'Não localizado'}
                                            </span>
                                        </p>
                                        {usage.sale_item && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {
                                                    usage.sale_item
                                                        .covered_quantity
                                                }{' '}
                                                de {usage.sale_item.quantity}{' '}
                                                coberto(s) pelo pacote
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                                            Origem comprovada
                                        </p>
                                        {usage.origin.type === 'appointment' ? (
                                            <div className="mt-1 space-y-1.5 text-sm text-foreground">
                                                <p className="flex items-center gap-1.5 font-medium">
                                                    <CalendarClock
                                                        aria-hidden="true"
                                                        className="size-3.5 text-primary"
                                                    />
                                                    {usage.origin.label}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Atendimento:{' '}
                                                    {formatDateTime(
                                                        usage.origin.appointment
                                                            .starts_at,
                                                        timeZone,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Profissional:{' '}
                                                    {usage.origin.appointment
                                                        .professional?.name ??
                                                        'Não informado'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Situação:{' '}
                                                    {
                                                        usage.origin.appointment
                                                            .status
                                                    }
                                                </p>
                                            </div>
                                        ) : (
                                            <div className="mt-1 space-y-1.5 text-sm">
                                                <p
                                                    className={
                                                        usage.origin.type ===
                                                        'none'
                                                            ? 'flex items-center gap-1.5 text-amber-700 dark:text-amber-300'
                                                            : 'text-foreground'
                                                    }
                                                >
                                                    {usage.origin.type ===
                                                        'none' && (
                                                        <CircleAlert
                                                            aria-hidden="true"
                                                            className="size-3.5"
                                                        />
                                                    )}
                                                    {usage.origin.label}
                                                </p>
                                                {usage.origin.type ===
                                                    'none' && (
                                                    <p className="text-xs text-muted-foreground">
                                                        O sistema não encontrou
                                                        agendamento ou comanda
                                                        vinculados a este
                                                        consumo.
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                                            Comanda e fechamento
                                        </p>
                                        {canViewSales && usage.sale ? (
                                            <Link
                                                href={sales.show(usage.sale.id)}
                                                className="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                                            >
                                                <Receipt
                                                    aria-hidden="true"
                                                    className="size-3.5"
                                                />
                                                {usage.sale.reference_label ??
                                                    'Abrir comanda'}
                                            </Link>
                                        ) : (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Sem comanda vinculada
                                            </p>
                                        )}
                                        {usage.sale && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Situação da comanda:{' '}
                                                <span className="text-foreground">
                                                    {usage.sale.status}
                                                </span>
                                            </p>
                                        )}
                                        {usage.sale &&
                                        usage.closings.length > 0 ? (
                                            <div className="mt-2 space-y-3">
                                                {usage.closings.map(
                                                    (closing) => (
                                                        <div
                                                            key={closing.id}
                                                            className="space-y-1"
                                                        >
                                                            {canViewClosingSessions ? (
                                                                <Link
                                                                    href={closingSessions.show(
                                                                        closing.id,
                                                                    )}
                                                                    className="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                                                                >
                                                                    Fechamento
                                                                    concluído
                                                                </Link>
                                                            ) : (
                                                                <p className="text-xs font-medium text-foreground">
                                                                    Fechamento
                                                                    concluído
                                                                </p>
                                                            )}
                                                            <p className="text-xs text-muted-foreground">
                                                                Registrado em:{' '}
                                                                {formatDateTime(
                                                                    closing.recorded_at,
                                                                    timeZone,
                                                                )}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                Fechado por:{' '}
                                                                {closing
                                                                    .closed_by
                                                                    ?.name ??
                                                                    'Usuário não informado'}
                                                            </p>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        ) : usage.sale ? (
                                            <p className="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                                Sem fechamento concluído
                                                localizado
                                            </p>
                                        ) : null}
                                    </div>
                                    <div>
                                        <p className="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                                            Responsável e reversão
                                        </p>
                                        <p className="mt-1 flex items-center gap-1.5 text-sm text-foreground">
                                            <UserRound
                                                aria-hidden="true"
                                                className="size-3.5 text-muted-foreground"
                                            />
                                            {usage.user?.name ??
                                                'Usuário não informado'}
                                        </p>
                                        {usage.status === 'reversed' ? (
                                            <div className="mt-1 space-y-1 text-sm text-foreground">
                                                <p>
                                                    {formatDateTime(
                                                        usage.reversed_at,
                                                        timeZone,
                                                    )}{' '}
                                                    ·{' '}
                                                    {usage.reversed_by_user
                                                        ?.name ??
                                                        'Usuário não informado'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {usage.reversal_reason ??
                                                        'Sem motivo informado'}
                                                </p>
                                            </div>
                                        ) : (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Não revertido
                                            </p>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>
                )}

                <Pagination links={usages.links} />
            </PageCanvas>
        </>
    );
}

CustomerPackageHistory.layout = {
    breadcrumbs: [
        { title: 'Clientes', href: customers.index() },
        { title: 'Histórico de consumo' },
    ],
};
