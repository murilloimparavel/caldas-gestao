import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowLeftRight,
    ArrowRight,
    ArrowUpRight,
    Banknote,
    CheckCircle2,
    Clock,
    PiggyBank,
    TrendingDown,
    TrendingUp,
    WalletCards,
} from 'lucide-react';
import {
    formatMoney,
    PageCanvas,
    ResourceHeader,
} from '@/components/operational';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    FinanceDashboardFilters,
    FinanceDashboardMetrics,
    FinanceDashboardMovement,
    FinancialObligation,
} from '@/types';

type Props = {
    metrics: FinanceDashboardMetrics;
    upcomingObligations: FinancialObligation[];
    filters: FinanceDashboardFilters;
    datePresets: Record<
        'today' | 'month' | 'previous',
        { from: string; to: string }
    >;
    filterOptions: {
        sources: Record<string, string>;
        types: Record<string, string>;
        statuses: Record<string, string>;
        paymentMethods: Array<{ value: string; label: string }>;
    };
    movements: FinanceDashboardMovement[];
};

export default function FinanceDashboard({
    metrics,
    upcomingObligations,
    filters,
    datePresets,
    filterOptions,
    movements,
}: Props) {
    const [filterState, setFilterState] = useState(filters);

    function applyFilters(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            '/finance/dashboard',
            { ...filterState },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function setPreset(preset: 'today' | 'month' | 'previous') {
        setFilterState((current) => ({ ...current, ...datePresets[preset] }));
    }

    return (
        <>
            <Head title="Painel Financeiro - Visão Geral" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Financeiro"
                    title="Painel Financeiro"
                    description="Indicadores consolidados de saúde financeira, fluxo de caixa, pagamentos e recebimentos."
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" asChild className="gap-2">
                                <Link href="/finance/cash">
                                    <Banknote className="h-4 w-4 text-emerald-600" />
                                    Caixa Operacional
                                </Link>
                            </Button>
                            <Button variant="outline" asChild className="gap-2">
                                <Link href="/finance/commissions">
                                    <WalletCards className="h-4 w-4 text-indigo-600" />
                                    Comissões
                                </Link>
                            </Button>
                            <Button asChild className="gap-2">
                                <Link href="/finance/transactions">
                                    <ArrowLeftRight className="h-4 w-4" />
                                    Lançamentos
                                </Link>
                            </Button>
                        </div>
                    }
                />

                {/* Overdue Alert Banner if any overdue obligations exist */}
                {metrics.overdue_count > 0 && (
                    <div className="flex items-center justify-between rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-rose-900 dark:text-rose-200">
                        <div className="flex items-center gap-3">
                            <div className="rounded-full bg-rose-500/20 p-2 text-rose-600 dark:text-rose-400">
                                <AlertCircle className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="font-semibold">
                                    Atenção: Existem {metrics.overdue_count}{' '}
                                    conta(s) em atraso
                                </p>
                                <p className="text-sm opacity-90">
                                    Total pendente vencido:{' '}
                                    <strong>
                                        {formatMoney(
                                            metrics.overdue_amount_cents,
                                        )}
                                    </strong>
                                </p>
                            </div>
                        </div>
                        <Button variant="destructive" size="sm" asChild>
                            <Link href="/finance/transactions?status=overdue">
                                Ver Contas Vencidas
                            </Link>
                        </Button>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Filtrar movimentações</CardTitle>
                        <CardDescription>
                            Os filtros atualizam o resumo realizado e a lista.
                            Caixa atual e previsões de hoje permanecem
                            operacionais.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={applyFilters} className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPreset('today')}
                                >
                                    Hoje
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPreset('month')}
                                >
                                    Este mês
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPreset('previous')}
                                >
                                    Mês anterior
                                </Button>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                <label className="grid gap-1 text-sm">
                                    De
                                    <input
                                        aria-label="Data inicial"
                                        className="h-10 rounded-md border bg-background px-3"
                                        type="date"
                                        value={filterState.from}
                                        onChange={(event) =>
                                            setFilterState({
                                                ...filterState,
                                                from: event.target.value,
                                            })
                                        }
                                    />
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Até
                                    <input
                                        aria-label="Data final"
                                        className="h-10 rounded-md border bg-background px-3"
                                        type="date"
                                        value={filterState.to}
                                        onChange={(event) =>
                                            setFilterState({
                                                ...filterState,
                                                to: event.target.value,
                                            })
                                        }
                                    />
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Origem
                                    <select
                                        aria-label="Origem"
                                        className="h-10 rounded-md border bg-background px-3"
                                        value={filterState.source}
                                        onChange={(event) =>
                                            setFilterState({
                                                ...filterState,
                                                source: event.target
                                                    .value as FinanceDashboardFilters['source'],
                                            })
                                        }
                                    >
                                        {Object.entries(
                                            filterOptions.sources,
                                        ).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Natureza
                                    <select
                                        aria-label="Natureza"
                                        className="h-10 rounded-md border bg-background px-3"
                                        value={filterState.type}
                                        onChange={(event) =>
                                            setFilterState({
                                                ...filterState,
                                                type: event.target
                                                    .value as FinanceDashboardFilters['type'],
                                            })
                                        }
                                    >
                                        {Object.entries(
                                            filterOptions.types,
                                        ).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Situação
                                    <select
                                        aria-label="Situação"
                                        className="h-10 rounded-md border bg-background px-3"
                                        value={filterState.status}
                                        onChange={(event) =>
                                            setFilterState({
                                                ...filterState,
                                                status: event.target
                                                    .value as FinanceDashboardFilters['status'],
                                            })
                                        }
                                    >
                                        {Object.entries(
                                            filterOptions.statuses,
                                        ).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Forma de pagamento
                                    <select
                                        aria-label="Forma de pagamento"
                                        className="h-10 rounded-md border bg-background px-3"
                                        value={filterState.payment_method}
                                        onChange={(event) =>
                                            setFilterState({
                                                ...filterState,
                                                payment_method:
                                                    event.target.value,
                                            })
                                        }
                                    >
                                        <option value="all">
                                            Todas as formas
                                        </option>
                                        {filterOptions.paymentMethods.map(
                                            (method) => (
                                                <option
                                                    key={method.value}
                                                    value={method.value}
                                                >
                                                    {method.label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </label>
                            </div>
                            <Button type="submit">Aplicar filtros</Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Main Metrics KPI Grid */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-sky-500/20 bg-sky-500/5">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Saldo em Caixa Físico
                            </CardTitle>
                            <Banknote className="h-4 w-4 text-sky-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-sky-600 dark:text-sky-400">
                                {formatMoney(
                                    metrics.current_cash_balance_cents,
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Turnos de caixa abertos atualmente
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-indigo-500/20 bg-indigo-500/5">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Saldo Projetado do Mês
                            </CardTitle>
                            <PiggyBank className="h-4 w-4 text-indigo-600" />
                        </CardHeader>
                        <CardContent>
                            <div
                                className={`text-2xl font-bold ${
                                    metrics.projected_balance_cents >= 0
                                        ? 'text-indigo-600 dark:text-indigo-400'
                                        : 'text-rose-600 dark:text-rose-400'
                                }`}
                            >
                                {formatMoney(metrics.projected_balance_cents)}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Caixa + Receitas Previstas - Despesas Previstas
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-emerald-500/20 bg-emerald-500/5">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                A Receber no período
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                {formatMoney(
                                    metrics.filtered_receivable_pending_cents,
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Obrigações pendentes no intervalo selecionado
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-rose-500/20 bg-rose-500/5">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                A Pagar no período
                            </CardTitle>
                            <TrendingDown className="h-4 w-4 text-rose-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-rose-600 dark:text-rose-400">
                                {formatMoney(
                                    metrics.filtered_payable_pending_cents,
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Despesas pendentes no intervalo selecionado
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Realized summary in current month */}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                Recebimentos no período
                            </CardTitle>
                            <CardDescription>
                                Comandas e recebíveis liquidados no intervalo
                                filtrado.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between rounded-lg bg-emerald-500/10 p-3">
                                <span className="text-sm font-medium text-emerald-800 dark:text-emerald-300">
                                    Total recebido
                                </span>
                                <span className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                                    {formatMoney(
                                        metrics.filtered_received_cents,
                                    )}
                                </span>
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Comandas:{' '}
                                {formatMoney(
                                    metrics.filtered_received_closings_cents,
                                )}{' '}
                                · Lançamentos:{' '}
                                {formatMoney(
                                    metrics.filtered_received_obligations_cents,
                                )}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <CheckCircle2 className="h-4 w-4 text-rose-600" />
                                Despesas pagas no período
                            </CardTitle>
                            <CardDescription>
                                Despesas financeiras liquidadas no intervalo
                                filtrado.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between rounded-lg bg-rose-500/10 p-3">
                                <span className="text-sm font-medium text-rose-800 dark:text-rose-300">
                                    Despesas Pagas
                                </span>
                                <span className="text-xl font-bold text-rose-600 dark:text-rose-400">
                                    {formatMoney(metrics.filtered_paid_cents)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Movimentações no período</CardTitle>
                        <CardDescription>
                            Comandas fechadas aparecem como recebimentos;
                            estornos reduzem o total recebido.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {movements.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Nenhuma movimentação encontrada para os filtros
                                selecionados.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[700px] text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="p-2 font-medium">
                                                Data
                                            </th>
                                            <th className="p-2 font-medium">
                                                Descrição
                                            </th>
                                            <th className="p-2 font-medium">
                                                Origem
                                            </th>
                                            <th className="p-2 font-medium">
                                                Forma
                                            </th>
                                            <th className="p-2 text-right font-medium">
                                                Valor
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {movements.map((movement) => (
                                            <tr key={movement.id}>
                                                <td className="p-2">
                                                    {movement.date
                                                        ? new Date(
                                                              `${movement.date}T00:00:00`,
                                                          ).toLocaleDateString(
                                                              'pt-BR',
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="p-2">
                                                    <Link
                                                        className="font-medium hover:underline"
                                                        href={
                                                            movement.reference_url
                                                        }
                                                    >
                                                        {movement.description}
                                                    </Link>
                                                    <div className="text-xs text-muted-foreground">
                                                        {movement.counterparty}
                                                    </div>
                                                </td>
                                                <td className="p-2">
                                                    {movement.source_label}
                                                    {movement.status ===
                                                    'reversed'
                                                        ? ' · Estornado'
                                                        : ''}
                                                </td>
                                                <td className="p-2">
                                                    {movement.payment_method_label ||
                                                        '—'}
                                                </td>
                                                <td
                                                    className={`p-2 text-right font-semibold ${movement.signed_amount_cents < 0 ? 'text-rose-600' : movement.type === 'receivable' ? 'text-emerald-600' : 'text-rose-600'}`}
                                                >
                                                    {movement.signed_amount_cents <
                                                    0
                                                        ? '−'
                                                        : '+'}{' '}
                                                    {formatMoney(
                                                        Math.abs(
                                                            movement.signed_amount_cents,
                                                        ),
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Upcoming obligations */}
                <div>
                    {/* Upcoming Obligations */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <div>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Clock className="h-4 w-4 text-amber-600" />
                                    Próximos Vencimentos
                                </CardTitle>
                                <CardDescription>
                                    Contas pendentes a vencer nos próximos dias.
                                </CardDescription>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                asChild
                                className="text-xs"
                            >
                                <Link href="/finance/transactions?status=pending">
                                    Ver todas{' '}
                                    <ArrowRight className="ml-1 h-3 w-3" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {upcomingObligations.length === 0 ? (
                                <p className="py-6 text-center text-xs text-muted-foreground">
                                    Nenhum vencimento pendente agendado.
                                </p>
                            ) : (
                                <div className="divide-y text-sm">
                                    {upcomingObligations.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center justify-between py-2.5"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div
                                                    className={`rounded-md p-1.5 ${
                                                        item.type === 'payable'
                                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400'
                                                            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400'
                                                    }`}
                                                >
                                                    {item.type === 'payable' ? (
                                                        <ArrowDownRight className="h-4 w-4" />
                                                    ) : (
                                                        <ArrowUpRight className="h-4 w-4" />
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="text-xs leading-tight font-medium text-foreground">
                                                        {item.description}
                                                    </p>
                                                    <p className="mt-0.5 text-3xs text-muted-foreground">
                                                        {item.supplier?.name ||
                                                            item.customer
                                                                ?.name ||
                                                            item.category
                                                                ?.name ||
                                                            'Geral'}{' '}
                                                        • Vence em{' '}
                                                        {new Date(
                                                            item.due_date +
                                                                'T00:00:00',
                                                        ).toLocaleDateString(
                                                            'pt-BR',
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <span
                                                    className={`text-xs font-semibold ${
                                                        item.type === 'payable'
                                                            ? 'text-rose-600'
                                                            : 'text-emerald-600'
                                                    }`}
                                                >
                                                    {item.type === 'payable'
                                                        ? '-'
                                                        : '+'}{' '}
                                                    {formatMoney(
                                                        item.amount_cents,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </PageCanvas>
        </>
    );
}
