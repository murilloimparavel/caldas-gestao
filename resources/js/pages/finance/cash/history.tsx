import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Eye, X } from 'lucide-react';
import { useState } from 'react';
import {
    EmptyState,
    formatMoney,
    PageCanvas,
    Pagination,
    ResourceHeader,
} from '@/components/operational';
import type { Paginated, ResourceFilters } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import cashShifts from '@/routes/cash_shifts';
import type { CashShift } from '@/types';

type Props = {
    shifts: Paginated<CashShift>;
    filters: ResourceFilters & {
        status?: string;
        date?: string;
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

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
}

export default function CashHistory({ shifts, filters }: Props) {
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [dateFilter, setDateFilter] = useState(filters.date ?? '');

    const applyFilters = (newStatus: string, newDate: string) => {
        router.get(
            cashShifts.history.url(),
            {
                status: newStatus || undefined,
                date: newDate || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <PageCanvas>
            <Head title="Histórico de Turnos de Caixa" />

            <ResourceHeader
                title="Histórico de Turnos de Caixa"
                description="Consulte todos os turnos de caixa passados, saldos apurados, quebras e sobras de caixa."
                actions={
                    <Button variant="outline" size="sm" asChild>
                        <Link href={cashShifts.index()}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Voltar ao Caixa Ativo
                        </Link>
                    </Button>
                }
            />

            {/* Filtros */}
            <div className="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold text-muted-foreground">Status:</span>
                    <select
                        className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        value={statusFilter}
                        onChange={(e) => {
                            setStatusFilter(e.target.value);
                            applyFilters(e.target.value, dateFilter);
                        }}
                    >
                        <option value="">Todos os status</option>
                        <option value="open">Abertos</option>
                        <option value="closed">Fechados</option>
                    </select>
                </div>

                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold text-muted-foreground">Data:</span>
                    <Input
                        type="date"
                        className="h-9 w-auto text-sm"
                        value={dateFilter}
                        onChange={(e) => {
                            setDateFilter(e.target.value);
                            applyFilters(statusFilter, e.target.value);
                        }}
                    />
                </div>

                {(statusFilter || dateFilter) && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setStatusFilter('');
                            setDateFilter('');
                            applyFilters('', '');
                        }}
                        className="h-9 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <X className="mr-1 h-3.5 w-3.5" />
                        Limpar filtros
                    </Button>
                )}
            </div>

            {/* Listagem de Turnos */}
            <div className="rounded-xl border border-border bg-card shadow-sm">
                {shifts.data.length === 0 ? (
                    <EmptyState
                        title="Nenhum turno de caixa encontrado"
                        description="Nenhum registro corresponde aos filtros selecionados."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <tr>
                                    <th className="px-6 py-3">Abertura</th>
                                    <th className="px-6 py-3">Fechamento</th>
                                    <th className="px-6 py-3">Operadores</th>
                                    <th className="px-6 py-3 text-right">Inicial</th>
                                    <th className="px-6 py-3 text-right">Esperado</th>
                                    <th className="px-6 py-3 text-right">Final Apurado</th>
                                    <th className="px-6 py-3 text-center">Diferença</th>
                                    <th className="px-6 py-3 text-center">Status</th>
                                    <th className="px-6 py-3 text-right">Ação</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {shifts.data.map((shift) => {
                                    const isClosed = shift.status === 'closed';
                                    const diff = shift.difference_cents ?? 0;

                                    return (
                                        <tr
                                            key={shift.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <div className="font-semibold text-foreground">
                                                    {formatDateTime(shift.opened_at)}
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-muted-foreground">
                                                {formatDateTime(shift.closed_at)}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="text-xs">
                                                    <span className="font-medium text-foreground">
                                                        {shift.opened_by?.name ?? '—'}
                                                    </span>
                                                    {shift.closed_by && shift.closed_by.id !== shift.opened_by?.id && (
                                                        <span className="block text-muted-foreground">
                                                            Fechado por: {shift.closed_by.name}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right font-medium text-muted-foreground">
                                                {formatMoney(shift.initial_amount_cents)}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right font-semibold text-foreground">
                                                {formatMoney(shift.expected_amount_cents)}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right font-bold text-foreground">
                                                {shift.final_amount_cents !== null && shift.final_amount_cents !== undefined
                                                    ? formatMoney(shift.final_amount_cents)
                                                    : '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-center">
                                                {!isClosed ? (
                                                    <span className="text-xs text-muted-foreground">Em andamento</span>
                                                ) : diff === 0 ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-emerald-300 bg-emerald-50 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                                                    >
                                                        Exato
                                                    </Badge>
                                                ) : diff > 0 ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-blue-300 bg-blue-50 text-xs font-semibold text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300"
                                                    >
                                                        +{formatMoney(diff)} (Sobra)
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-rose-300 bg-rose-50 text-xs font-semibold text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300"
                                                    >
                                                        {formatMoney(diff)} (Falta)
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-center">
                                                {shift.status === 'open' ? (
                                                    <Badge className="bg-emerald-600 text-white">
                                                        Aberto
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        Encerrado
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right">
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={cashShifts.show({ cashShift: shift.id })}>
                                                        <Eye className="mr-1 h-3.5 w-3.5" />
                                                        Detalhes
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="border-t border-border p-4">
                    <Pagination paginated={shifts} />
                </div>
            </div>
        </PageCanvas>
    );
}
