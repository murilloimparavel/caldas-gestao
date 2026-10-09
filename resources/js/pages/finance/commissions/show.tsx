import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    DollarSign,
    Filter,
    History,
    Receipt,
    UserCheck,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import {
    useIdempotencyKey,
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    Pagination,
    ResourceHeader,
} from '@/components/operational';
import type { Paginated } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type {
    CommissionAccrual,
    CommissionSettlement,
    PackageCommissionSummary,
    SharedPageProps,
} from '@/types';

type Professional = {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    status: string;
};

type Props = {
    professional: Professional;
    accruals: Paginated<CommissionAccrual>;
    package_summaries: PackageCommissionSummary[];
    settlements: CommissionSettlement[];
    filters: {
        status: string;
        date_start: string;
        date_end: string;
    };
    metrics: {
        pending_amount_cents: number;
        total_settled_cents: number;
        total_accruals_count: number;
    };
};

function formatDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export default function ProfessionalCommissionShow({
    professional,
    accruals,
    package_summaries,
    settlements,
    filters,
    metrics,
}: Props) {
    const { auth } = usePage<SharedPageProps>().props;
    const canSettle = auth.permissions.includes('commission.settle');
    const [isSettleModalOpen, setIsSettleModalOpen] = useState(false);
    const [settleKey, rotateSettleKey] = useIdempotencyKey(
        'settle-commissions',
        professional.id,
    );

    return (
        <PageCanvas
            breadcrumbs={[
                { title: 'Painel', href: '/dashboard' },
                { title: 'Financeiro', href: '/finance/cash' },
                { title: 'Comissões', href: '/finance/commissions' },
                {
                    title: professional.name,
                    href: `/finance/commissions/professionals/${professional.id}`,
                },
            ]}
        >
            <Head title={`Extrato de Comissões - ${professional.name}`} />

            <div className="space-y-6">
                <ResourceHeader
                    title={`Extrato de Comissões: ${professional.name}`}
                    description={`Visualização detalhada dos atendimentos, apuração e liquidação de comissões.`}
                    actions={
                        <div className="flex items-center gap-2">
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="gap-1.5"
                            >
                                <Link href="/finance/commissions">
                                    <ArrowLeft className="h-4 w-4" />
                                    Voltar
                                </Link>
                            </Button>
                            {canSettle && metrics.pending_amount_cents > 0 && (
                                <Button
                                    onClick={() => {
                                        rotateSettleKey();
                                        setIsSettleModalOpen(true);
                                    }}
                                    className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                                >
                                    <Wallet className="h-4 w-4" />
                                    Liquidar Comissões (
                                    {formatMoney(metrics.pending_amount_cents)})
                                </Button>
                            )}
                        </div>
                    }
                />

                {/* Cards de Métricas */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl border bg-card p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-muted-foreground">
                                Saldo Pendente (A Pagar)
                            </span>
                            <div className="rounded-lg bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                <Wallet className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold tracking-tight text-amber-600 dark:text-amber-400">
                            {formatMoney(metrics.pending_amount_cents)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Disponível para liquidação imediata
                        </p>
                    </div>

                    <div className="rounded-xl border bg-card p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-muted-foreground">
                                Total Já Liquidado
                            </span>
                            <div className="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                                <Receipt className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold tracking-tight text-foreground">
                            {formatMoney(metrics.total_settled_cents)}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Histórico consolidado de repasses pagos
                        </p>
                    </div>

                    <div className="rounded-xl border bg-card p-5 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-muted-foreground">
                                Total de Atendimentos
                            </span>
                            <div className="rounded-lg bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                                <UserCheck className="h-5 w-5" />
                            </div>
                        </div>
                        <div className="mt-3 text-2xl font-bold tracking-tight text-foreground">
                            {metrics.total_accruals_count}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Itens e serviços comissionados
                        </p>
                    </div>
                </div>

                {package_summaries.length > 0 && (
                    <section className="space-y-4">
                        <div>
                            <h2 className="text-lg font-semibold text-foreground">
                                Resumo por pacote
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Base comissionável e comissão dos serviços de
                                cada pacote.
                            </p>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            {package_summaries.map((summary) => (
                                <div
                                    key={summary.id}
                                    className="rounded-xl border bg-card p-5 shadow-sm"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 className="font-semibold text-foreground">
                                                {summary.name}
                                            </h3>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {summary.service_count}{' '}
                                                {summary.service_count === 1
                                                    ? 'serviço com comissão'
                                                    : 'serviços com comissão'}
                                            </p>
                                        </div>
                                        <Badge variant="secondary">
                                            Pacote
                                        </Badge>
                                    </div>
                                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-lg bg-muted/40 p-3">
                                            <p className="text-xs text-muted-foreground">
                                                Base comissionável
                                            </p>
                                            <p className="mt-1 font-semibold text-foreground">
                                                {formatMoney(
                                                    summary.gross_amount_cents,
                                                )}
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-emerald-500/10 p-3">
                                            <p className="text-xs text-muted-foreground">
                                                Comissão do pacote
                                            </p>
                                            <p className="mt-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                                {formatMoney(
                                                    summary.commission_amount_cents,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* Filtros */}
                <div className="rounded-xl border bg-card p-4 shadow-sm">
                    <form
                        method="get"
                        className="flex flex-wrap items-end gap-3"
                    >
                        <div className="space-y-1">
                            <label
                                htmlFor="filter-status"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Status
                            </label>
                            <select
                                id="filter-status"
                                name="status"
                                defaultValue={filters.status}
                                className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="">Todos os status</option>
                                <option value="accrued">
                                    Pendente (Apurado)
                                </option>
                                <option value="settled">
                                    Liquidado (Pago)
                                </option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="filter-date-start"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                A partir de
                            </label>
                            <Input
                                id="filter-date-start"
                                type="date"
                                name="date_start"
                                defaultValue={filters.date_start}
                                className="h-9 w-40 text-sm"
                            />
                        </div>

                        <div className="space-y-1">
                            <label
                                htmlFor="filter-date-end"
                                className="text-xs font-medium text-muted-foreground"
                            >
                                Até
                            </label>
                            <Input
                                id="filter-date-end"
                                type="date"
                                name="date_end"
                                defaultValue={filters.date_end}
                                className="h-9 w-40 text-sm"
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                variant="secondary"
                                className="h-9 gap-1.5"
                            >
                                <Filter className="h-3.5 w-3.5" />
                                Filtrar
                            </Button>
                            {(filters.status ||
                                filters.date_start ||
                                filters.date_end) && (
                                <Button
                                    asChild
                                    size="sm"
                                    variant="ghost"
                                    className="h-9"
                                >
                                    <Link
                                        href={`/finance/commissions/professionals/${professional.id}`}
                                    >
                                        Limpar
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </form>
                </div>

                {/* Tabela de Lançamentos / Apurações */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-foreground">
                            Lançamentos de Atendimentos
                        </h2>
                    </div>

                    {accruals.data.length === 0 ? (
                        <EmptyState
                            icon={DollarSign}
                            title="Nenhum lançamento encontrado"
                            description="Não há registros de comissão correspondentes aos filtros selecionados."
                        />
                    ) : (
                        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-6 py-3.5">
                                                Data / Hora
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Item / Serviço
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Valor Bruto
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Regra Aplicada
                                            </th>
                                            <th className="px-6 py-3.5 text-right">
                                                Comissão
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Status
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Liquidado Em
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {accruals.data.map((accrual) => (
                                            <tr
                                                key={accrual.id}
                                                className="transition-colors hover:bg-muted/30"
                                            >
                                                <td className="px-6 py-4 text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        accrual.created_at,
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 font-medium text-foreground">
                                                    <div>
                                                        {
                                                            accrual.item_name_snapshot
                                                        }
                                                    </div>
                                                    {accrual.source_type ===
                                                        'package_service' && (
                                                        <div className="text-xs font-normal text-muted-foreground">
                                                            Serviço do pacote
                                                            {accrual.package_name_snapshot
                                                                ? ` “${accrual.package_name_snapshot}”`
                                                                : ''}
                                                        </div>
                                                    )}
                                                    {(accrual.covered_quantity ??
                                                        0) > 0 && (
                                                        <div className="text-xs font-normal text-muted-foreground">
                                                            {
                                                                accrual.covered_quantity
                                                            }{' '}
                                                            de{' '}
                                                            {accrual.quantity ??
                                                                '—'}{' '}
                                                            sessões cobertas ·
                                                            comissão sobre{' '}
                                                            {accrual.commissionable_quantity ??
                                                                '—'}{' '}
                                                            sessão(ões) paga(s)
                                                        </div>
                                                    )}
                                                    {accrual.sale
                                                        ?.reference_label && (
                                                        <div className="text-xs text-muted-foreground">
                                                            Ref:{' '}
                                                            {
                                                                accrual.sale
                                                                    .reference_label
                                                            }
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-muted-foreground">
                                                    {formatMoney(
                                                        accrual.gross_amount_cents,
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-xs text-muted-foreground">
                                                    {accrual.rate_type ===
                                                    'percentage'
                                                        ? `${accrual.rate_value}%`
                                                        : formatMoney(
                                                              accrual.rate_value,
                                                          )}
                                                </td>
                                                <td className="px-6 py-4 text-right font-semibold">
                                                    <span
                                                        className={
                                                            accrual.status ===
                                                            'accrued'
                                                                ? 'text-amber-600 dark:text-amber-400'
                                                                : 'text-foreground'
                                                        }
                                                    >
                                                        {formatMoney(
                                                            accrual.commission_amount_cents,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4">
                                                    {accrual.status ===
                                                        'accrued' && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400"
                                                        >
                                                            Pendente
                                                        </Badge>
                                                    )}
                                                    {accrual.status ===
                                                        'settled' && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400"
                                                        >
                                                            Liquidado
                                                        </Badge>
                                                    )}
                                                    {accrual.status ===
                                                        'cancelled' && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-muted-foreground"
                                                        >
                                                            Cancelado
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-xs text-muted-foreground">
                                                    {accrual.settled_at
                                                        ? formatDateTime(
                                                              accrual.settled_at,
                                                          )
                                                        : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="border-t p-4">
                                <Pagination links={accruals.links} />
                            </div>
                        </div>
                    )}
                </div>

                {/* Histórico de Liquidações Recentes */}
                {settlements.length > 0 && (
                    <div className="space-y-4 pt-4">
                        <div className="flex items-center gap-2">
                            <History className="h-5 w-5 text-muted-foreground" />
                            <h2 className="text-lg font-semibold text-foreground">
                                Histórico de Liquidações
                            </h2>
                        </div>

                        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-muted/40 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-6 py-3.5">
                                                Data de Pagamento
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Valor Liquidado
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Período
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Operador
                                            </th>
                                            <th className="px-6 py-3.5">
                                                Observações
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {settlements.map((settlement) => (
                                            <tr
                                                key={settlement.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-6 py-4 font-medium text-foreground">
                                                    {formatDateTime(
                                                        settlement.paid_at,
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 font-semibold text-emerald-600 dark:text-emerald-400">
                                                    {formatMoney(
                                                        settlement.total_amount_cents,
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-xs text-muted-foreground">
                                                    {formatDate(
                                                        settlement.period_start,
                                                    )}{' '}
                                                    até{' '}
                                                    {formatDate(
                                                        settlement.period_end,
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-xs text-muted-foreground">
                                                    {settlement.user?.name ??
                                                        '—'}
                                                </td>
                                                <td className="px-6 py-4 text-xs text-muted-foreground">
                                                    {settlement.notes || '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Modal de Liquidação */}
            <Dialog
                open={isSettleModalOpen}
                onOpenChange={(open) => {
                    if (open) {
                        rotateSettleKey();
                    }

                    setIsSettleModalOpen(open);
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Liquidar Comissões Pendentes</DialogTitle>
                        <DialogDescription>
                            Confirmar pagamento e baixa das comissões acumuladas
                            de {professional.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        action="/finance/commissions/settle"
                        method="post"
                        headers={{
                            'X-Idempotency-Key': settleKey,
                        }}
                        onSuccess={() => {
                            rotateSettleKey();
                            setIsSettleModalOpen(false);
                        }}
                        className="space-y-4 pt-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <FormErrorSummary errors={errors} />

                                <input
                                    type="hidden"
                                    name="professional_id"
                                    value={professional.id}
                                />

                                <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-4 text-center">
                                    <div className="text-xs font-semibold tracking-wider text-emerald-700 uppercase dark:text-emerald-300">
                                        Total a Liquidar
                                    </div>
                                    <div className="mt-1 text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
                                        {formatMoney(
                                            metrics.pending_amount_cents,
                                        )}
                                    </div>
                                </div>

                                <FormField
                                    label="Data do Pagamento"
                                    error={errors.paid_at}
                                >
                                    <Input
                                        type="date"
                                        name="paid_at"
                                        defaultValue={
                                            new Date()
                                                .toISOString()
                                                .split('T')[0]
                                        }
                                        required
                                    />
                                </FormField>

                                <FormField
                                    label="Observações / Comprovante"
                                    error={errors.notes}
                                >
                                    <Input
                                        name="notes"
                                        placeholder="Ex: Pago via PIX, ref. semana 34"
                                    />
                                </FormField>

                                <FormActions
                                    onCancel={() => setIsSettleModalOpen(false)}
                                    submitLabel="Confirmar Liquidação"
                                    isSubmitting={processing}
                                />
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </PageCanvas>
    );
}
