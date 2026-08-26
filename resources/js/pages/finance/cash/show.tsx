import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import {
    EmptyState,
    formatMoney,
    PageCanvas,
    ResourceHeader,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import cashShifts from '@/routes/cash_shifts';
import type { CashMovementType, CashShift } from '@/types';

type Props = {
    shift: CashShift;
};

const movementTypeConfig: Record<
    CashMovementType,
    { label: string; bgClass: string; isCredit: boolean }
> = {
    supply: {
        label: 'Suprimento (Entrada)',
        bgClass:
            'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
        isCredit: true,
    },
    sale_inflow: {
        label: 'Recebimento de Venda',
        bgClass:
            'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
        isCredit: true,
    },
    bleed: {
        label: 'Sangria (Saída)',
        bgClass:
            'border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
        isCredit: false,
    },
    commission_outflow: {
        label: 'Pagamento de Comissão',
        bgClass:
            'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
        isCredit: false,
    },
    expense_outflow: {
        label: 'Despesa Operacional',
        bgClass:
            'border-purple-300 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-950/40 dark:text-purple-300',
        isCredit: false,
    },
};

function formatDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
}

export default function CashShow({ shift }: Props) {
    const isClosed = shift.status === 'closed';
    const diff = shift.difference_cents ?? 0;

    const handlePrint = () => {
        window.print();
    };

    return (
        <PageCanvas>
            <Head title={`Turno de Caixa #${shift.id.slice(0, 8)}`} />

            <ResourceHeader
                title={`Turno de Caixa #${shift.id.slice(0, 8)}`}
                description={`Aberto em ${formatDateTime(shift.opened_at)} por ${shift.opened_by?.name ?? 'Operador'}`}
                actions={
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={cashShifts.history()}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Voltar ao Histórico
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" onClick={handlePrint}>
                            <Printer className="mr-2 h-4 w-4" />
                            Imprimir Resumo
                        </Button>
                    </div>
                }
            />

            {/* Resumo do Turno */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <span className="text-xs font-semibold text-muted-foreground">Fundo Inicial</span>
                    <p className="mt-2 text-2xl font-bold tracking-tight">
                        {formatMoney(shift.initial_amount_cents)}
                    </p>
                    <span className="text-xs text-muted-foreground">
                        Aberto: {formatDateTime(shift.opened_at)}
                    </span>
                </div>

                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <span className="text-xs font-semibold text-muted-foreground">Saldo Esperado em Caixa</span>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-foreground">
                        {formatMoney(shift.expected_amount_cents)}
                    </p>
                    <span className="text-xs text-muted-foreground">
                        Calculado pelo sistema
                    </span>
                </div>

                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <span className="text-xs font-semibold text-muted-foreground">Saldo Final Apurado</span>
                    <p className="mt-2 text-2xl font-bold tracking-tight text-foreground">
                        {shift.final_amount_cents !== null && shift.final_amount_cents !== undefined
                            ? formatMoney(shift.final_amount_cents)
                            : 'Em aberto'}
                    </p>
                    <span className="text-xs text-muted-foreground">
                        Fechamento: {formatDateTime(shift.closed_at)}
                    </span>
                </div>

                <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <span className="text-xs font-semibold text-muted-foreground">Diferença / Status</span>
                    <div className="mt-2 flex items-center gap-2">
                        {!isClosed ? (
                            <Badge className="bg-emerald-600 text-white">Aberto</Badge>
                        ) : diff === 0 ? (
                            <Badge className="bg-emerald-600 text-white">Exato</Badge>
                        ) : diff > 0 ? (
                            <Badge className="bg-blue-600 text-white">
                                +{formatMoney(diff)} (Sobra)
                            </Badge>
                        ) : (
                            <Badge className="bg-rose-600 text-white">
                                {formatMoney(diff)} (Quebra)
                            </Badge>
                        )}
                    </div>
                    <span className="text-xs text-muted-foreground">
                        {shift.closed_by?.name ? `Fechado por ${shift.closed_by.name}` : 'Em operação'}
                    </span>
                </div>
            </div>

            {shift.notes && (
                <div className="rounded-xl border border-border bg-muted/30 p-4 text-sm">
                    <span className="font-semibold text-foreground">Observações do Turno: </span>
                    <span className="text-muted-foreground">{shift.notes}</span>
                </div>
            )}

            {/* Listagem de Movimentações */}
            <div className="rounded-xl border border-border bg-card shadow-sm">
                <div className="border-b border-border px-6 py-4">
                    <h3 className="text-base font-semibold">Movimentações Registradas</h3>
                    <p className="text-xs text-muted-foreground">
                        Todas as operações que afetaram o saldo deste turno
                    </p>
                </div>

                {!shift.movements || shift.movements.length === 0 ? (
                    <EmptyState
                        title="Nenhuma movimentação avulsa registrada"
                        description="Este turno não teve suprimentos ou sangrias manuais."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <tr>
                                    <th className="px-6 py-3">Tipo</th>
                                    <th className="px-6 py-3">Motivo / Descrição</th>
                                    <th className="px-6 py-3">Responsável</th>
                                    <th className="px-6 py-3">Horário</th>
                                    <th className="px-6 py-3 text-right">Valor</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {shift.movements.map((movement) => {
                                    const config =
                                        movementTypeConfig[movement.type] ??
                                        movementTypeConfig.supply;

                                    return (
                                        <tr key={movement.id} className="transition-colors hover:bg-muted/30">
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <Badge
                                                    variant="outline"
                                                    className={`font-semibold ${config.bgClass}`}
                                                >
                                                    {config.label}
                                                </Badge>
                                            </td>
                                            <td className="px-6 py-4 font-medium text-foreground">
                                                {movement.reason}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-muted-foreground">
                                                {movement.user?.name ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-xs text-muted-foreground">
                                                {formatDateTime(movement.created_at)}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right font-bold">
                                                <span
                                                    className={
                                                        config.isCredit
                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                            : 'text-rose-600 dark:text-rose-400'
                                                    }
                                                >
                                                    {config.isCredit ? '+' : '-'}
                                                    {formatMoney(movement.amount_cents)}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </PageCanvas>
    );
}
