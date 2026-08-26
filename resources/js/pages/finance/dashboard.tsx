import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowLeftRight,
    ArrowRight,
    ArrowUpRight,
    Banknote,
    Calendar,
    CheckCircle2,
    Clock,
    DollarSign,
    PiggyBank,
    Plus,
    TrendingDown,
    TrendingUp,
    User,
    Users,
    WalletCards,
} from 'lucide-react';
import {
    EmptyState,
    formatMoney,
    PageCanvas,
    ResourceHeader,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    FinanceDashboardMetrics,
    FinancialObligation,
    SharedPageProps,
} from '@/types';

type Props = {
    metrics: FinanceDashboardMetrics;
    upcomingObligations: FinancialObligation[];
    recentSettled: FinancialObligation[];
};

export default function FinanceDashboard({
    metrics,
    upcomingObligations,
    recentSettled,
}: Props) {
    const { auth } = usePage<SharedPageProps>().props;
    const canManage = auth.permissions.includes('financial.manage');

    const todayStr = new Date().toISOString().split('T')[0];

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
                                    Atenção: Existem {metrics.overdue_count} conta(s) em atraso
                                </p>
                                <p className="text-sm opacity-90">
                                    Total pendente vencido: <strong>{formatMoney(metrics.overdue_amount_cents)}</strong>
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
                                {formatMoney(metrics.current_cash_balance_cents)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
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
                            <div className={`text-2xl font-bold ${
                                metrics.projected_balance_cents >= 0
                                    ? 'text-indigo-600 dark:text-indigo-400'
                                    : 'text-rose-600 dark:text-rose-400'
                            }`}>
                                {formatMoney(metrics.projected_balance_cents)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Caixa + Receitas Previstas - Despesas Previstas
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-emerald-500/20 bg-emerald-500/5">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                A Receber Hoje
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                {formatMoney(metrics.receivable_today_cents)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Previsto no mês: {formatMoney(metrics.receivable_month_pending_cents)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-rose-500/20 bg-rose-500/5">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                A Pagar Hoje
                            </CardTitle>
                            <TrendingDown className="h-4 w-4 text-rose-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-rose-600 dark:text-rose-400">
                                {formatMoney(metrics.payable_today_cents)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Previsto no mês: {formatMoney(metrics.payable_month_pending_cents)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Realized summary in current month */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base flex items-center gap-2">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                Realizado no Mês (Recebimentos)
                            </CardTitle>
                            <CardDescription>
                                Total de receitas efetivamente liquidadas no mês corrente.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between p-3 bg-emerald-500/10 rounded-lg">
                                <span className="text-sm font-medium text-emerald-800 dark:text-emerald-300">Receitas Liquidadas</span>
                                <span className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                                    {formatMoney(metrics.received_month_cents)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base flex items-center gap-2">
                                <CheckCircle2 className="h-4 w-4 text-rose-600" />
                                Realizado no Mês (Pagamentos)
                            </CardTitle>
                            <CardDescription>
                                Total de despesas e contas operacionais pagas no mês corrente.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between p-3 bg-rose-500/10 rounded-lg">
                                <span className="text-sm font-medium text-rose-800 dark:text-rose-300">Despesas Pagas</span>
                                <span className="text-xl font-bold text-rose-600 dark:text-rose-400">
                                    {formatMoney(metrics.paid_month_cents)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Upcoming Obligations & Recent Transactions */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Upcoming Obligations */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <div>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-amber-600" />
                                    Próximos Vencimentos
                                </CardTitle>
                                <CardDescription>
                                    Contas pendentes a vencer nos próximos dias.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild className="text-xs">
                                <Link href="/finance/transactions?status=pending">
                                    Ver todas <ArrowRight className="h-3 w-3 ml-1" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {upcomingObligations.length === 0 ? (
                                <p className="text-xs text-muted-foreground py-6 text-center">
                                    Nenhum vencimento pendente agendado.
                                </p>
                            ) : (
                                <div className="divide-y text-sm">
                                    {upcomingObligations.map((item) => (
                                        <div key={item.id} className="py-2.5 flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className={`p-1.5 rounded-md ${
                                                    item.type === 'payable'
                                                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400'
                                                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400'
                                                }`}>
                                                    {item.type === 'payable' ? (
                                                        <ArrowDownRight className="h-4 w-4" />
                                                    ) : (
                                                        <ArrowUpRight className="h-4 w-4" />
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="font-medium text-foreground text-xs leading-tight">
                                                        {item.description}
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                                        {item.supplier?.name || item.customer?.name || item.category?.name || 'Geral'} • Vence em {new Date(item.due_date + 'T00:00:00').toLocaleDateString('pt-BR')}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <span className={`font-semibold text-xs ${
                                                    item.type === 'payable' ? 'text-rose-600' : 'text-emerald-600'
                                                }`}>
                                                    {item.type === 'payable' ? '-' : '+'} {formatMoney(item.amount_cents)}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Settlements */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <div>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <CheckCircle2 className="h-4 w-4 text-sky-600" />
                                    Últimas Liquidações
                                </CardTitle>
                                <CardDescription>
                                    Histórico das contas liquidadas mais recentemente.
                                </CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild className="text-xs">
                                <Link href="/finance/transactions?status=paid">
                                    Ver histórico <ArrowRight className="h-3 w-3 ml-1" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {recentSettled.length === 0 ? (
                                <p className="text-xs text-muted-foreground py-6 text-center">
                                    Nenhuma liquidação registrada recentemente.
                                </p>
                            ) : (
                                <div className="divide-y text-sm">
                                    {recentSettled.map((item) => (
                                        <div key={item.id} className="py-2.5 flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className={`p-1.5 rounded-md ${
                                                    item.type === 'payable'
                                                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400'
                                                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400'
                                                }`}>
                                                    <CheckCircle2 className="h-4 w-4" />
                                                </div>
                                                <div>
                                                    <p className="font-medium text-foreground text-xs leading-tight">
                                                        {item.description}
                                                    </p>
                                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                                        Pago em {item.paid_date ? new Date(item.paid_date + 'T00:00:00').toLocaleDateString('pt-BR') : '—'} • {item.payment_method?.toUpperCase() || 'PIX'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <span className={`font-semibold text-xs ${
                                                    item.type === 'payable' ? 'text-rose-600' : 'text-emerald-600'
                                                }`}>
                                                    {item.type === 'payable' ? '-' : '+'} {formatMoney(item.amount_cents)}
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
