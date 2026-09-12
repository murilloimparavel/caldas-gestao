import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    FileText,
    MapPin,
    Printer,
} from 'lucide-react';
import { formatMoney, PageCanvas } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import sales from '@/routes/sales';
import type { ClosingSession } from '@/types';

type Props = {
    session: ClosingSession;
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

export default function ClosingSessionShow({ session }: Props) {
    const payload = session.receipt_payload;

    const handlePrint = () => {
        window.print();
    };

    const customerName =
        payload?.customer?.name ||
        session.sales?.find((s) => s.customer?.name)?.customer?.name ||
        'Cliente Avulso / Não identificado';

    const tenantName =
        payload?.tenant?.name || session.tenant?.name || 'Caldas Gestão';
    const unitName =
        payload?.unit?.name || session.unit?.name || 'Unidade Principal';
    const operatorName =
        payload?.closed_by?.name || session.closed_by?.name || 'Operador';
    const receiptNumber =
        session.receipt_number ||
        payload?.receipt_number ||
        session.id.slice(0, 8).toUpperCase();
    const issuedAt = payload?.issued_at || session.created_at;

    const totalGrossCents =
        payload?.totals?.total_gross_cents ?? session.expected_total_cents;
    const totalDiscountCents =
        payload?.totals?.total_discount_cents ??
        totalGrossCents - session.final_total_cents;
    const finalTotalCents =
        payload?.totals?.final_total_cents ?? session.final_total_cents;
    const salesList = payload?.sales ?? session.sales ?? [];

    return (
        <>
            <Head title={`Recibo Interno #${receiptNumber}`} />

            {/* Screen View */}
            <PageCanvas className="print:hidden">
                <div className="space-y-6">
                    {/* Top Navigation & Actions */}
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2 gap-1 text-muted-foreground hover:text-foreground"
                        >
                            <Link href={sales.index()}>
                                <ArrowLeft className="size-4" />
                                Voltar para Comandas
                            </Link>
                        </Button>

                        <div className="flex items-center gap-2">
                            <Button
                                onClick={handlePrint}
                                variant="outline"
                                className="gap-2 shadow-sm"
                            >
                                <Printer className="size-4" />
                                Imprimir Recibo
                            </Button>
                        </div>
                    </div>

                    {/* Status Alert Banner */}
                    <div className="flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-900 dark:text-emerald-200">
                        <div className="flex items-center gap-3">
                            <CheckCircle2 className="size-6 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Fechamento Consolidado Concluído
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Todas as comandas vinculadas foram
                                    finalizadas com sucesso e o recibo
                                    operacional foi gerado.
                                </p>
                            </div>
                        </div>
                        <Badge
                            variant="outline"
                            className="border-emerald-500/30 bg-emerald-500/20 font-semibold text-emerald-700 dark:text-emerald-300"
                        >
                            {receiptNumber}
                        </Badge>
                    </div>

                    {/* Printable Styled Card Container */}
                    <div className="surface-panel mx-auto max-w-3xl overflow-hidden rounded-2xl border border-border shadow-md">
                        {/* Receipt Header */}
                        <div className="border-b border-border bg-muted/40 p-6 sm:p-8">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <span className="inline-flex items-center gap-1.5 text-xs font-bold tracking-wider text-primary uppercase">
                                        <FileText className="size-3.5" /> Recibo
                                        Interno Operacional
                                    </span>
                                    <h1 className="mt-1 font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                        {tenantName}
                                    </h1>
                                    <p className="flex items-center gap-1 text-xs text-muted-foreground sm:text-sm">
                                        <MapPin className="size-3.5" />{' '}
                                        {unitName}
                                    </p>
                                </div>
                                <div className="text-left sm:text-right">
                                    <span className="font-mono text-sm font-bold text-foreground">
                                        {receiptNumber}
                                    </span>
                                    <p className="text-xs text-muted-foreground">
                                        Emitido em: {formatDateTime(issuedAt)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Operador:{' '}
                                        <span className="font-medium text-foreground">
                                            {operatorName}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            {/* Client & Reference Summary */}
                            <div className="mt-6 grid gap-3 rounded-xl border border-border/80 bg-background/80 p-4 sm:grid-cols-2">
                                <div>
                                    <span className="text-2xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Cliente / Identificador
                                    </span>
                                    <p className="text-sm font-semibold text-foreground">
                                        {customerName}
                                    </p>
                                    {payload?.customer?.phone ? (
                                        <p className="text-xs text-muted-foreground">
                                            Tel: {payload.customer.phone}
                                        </p>
                                    ) : null}
                                </div>
                                <div>
                                    <span className="text-2xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Assunto de Fechamento
                                    </span>
                                    <p className="font-mono text-xs text-muted-foreground">
                                        {session.closing_subject}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {salesList.length} comanda(s)
                                        consolidada(s)
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Breakdown per Sale */}
                        <div className="space-y-6 p-6 sm:p-8">
                            <h3 className="font-display text-base font-semibold text-foreground">
                                Detalhamento por Comanda
                            </h3>

                            {salesList.map((s, saleIndex) => {
                                const categoryName =
                                    'category_name' in s
                                        ? s.category_name
                                        : s.category_name_snapshot ||
                                          s.category?.name ||
                                          'Geral';
                                const refLabel =
                                    'reference_label' in s
                                        ? s.reference_label
                                        : s.reference_label;
                                const saleItems =
                                    'items' in s ? (s.items ?? []) : [];
                                const saleDiscount =
                                    'discount_amount_cents' in s
                                        ? s.discount_amount_cents
                                        : 0;
                                const saleTotal =
                                    'final_amount_cents' in s
                                        ? s.final_amount_cents
                                        : 0;

                                return (
                                    <div
                                        key={s.id || saleIndex}
                                        className="rounded-xl border border-border bg-card/50 p-4"
                                    >
                                        {/* Sale Header */}
                                        <div className="flex items-center justify-between border-b border-border/60 pb-3">
                                            <div>
                                                <span className="text-sm font-semibold text-foreground">
                                                    Comanda #{s.id.slice(0, 8)}
                                                </span>
                                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Badge
                                                        variant="secondary"
                                                        className="py-0 text-2xs"
                                                    >
                                                        {categoryName}
                                                    </Badge>
                                                    {refLabel ? (
                                                        <span>
                                                            Ref: {refLabel}
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </div>
                                            <span className="text-sm font-semibold text-foreground">
                                                {formatMoney(saleTotal)}
                                            </span>
                                        </div>

                                        {/* Items Table */}
                                        <div className="mt-3 divide-y divide-border/40">
                                            {saleItems.length === 0 ? (
                                                <p className="py-2 text-xs text-muted-foreground italic">
                                                    Nenhum item avulso
                                                    adicionado.
                                                </p>
                                            ) : (
                                                saleItems.map(
                                                    (item, itemIdx) => {
                                                        const itemName =
                                                            'name' in item
                                                                ? item.name
                                                                : item.name_snapshot;
                                                        const itemProf =
                                                            'professional_name' in
                                                            item
                                                                ? item.professional_name
                                                                : 'professional' in
                                                                    item
                                                                  ? item
                                                                        .professional
                                                                        ?.name
                                                                  : null;

                                                        return (
                                                            <div
                                                                key={
                                                                    item.id ||
                                                                    itemIdx
                                                                }
                                                                className="flex items-center justify-between py-2 text-xs"
                                                            >
                                                                <div className="min-w-0 pr-4">
                                                                    <p className="font-medium text-foreground">
                                                                        {
                                                                            item.quantity
                                                                        }
                                                                        x{' '}
                                                                        {
                                                                            itemName
                                                                        }
                                                                    </p>
                                                                    {itemProf ? (
                                                                        <p className="text-3xs text-muted-foreground">
                                                                            Profissional:{' '}
                                                                            {
                                                                                itemProf
                                                                            }
                                                                        </p>
                                                                    ) : null}
                                                                </div>
                                                                <div className="shrink-0 text-right">
                                                                    <p className="font-medium text-foreground">
                                                                        {formatMoney(
                                                                            item.total_cents,
                                                                        )}
                                                                    </p>
                                                                    {item.discount_cents >
                                                                    0 ? (
                                                                        <p className="text-2xs text-emerald-600 dark:text-emerald-400">
                                                                            desc.{' '}
                                                                            {formatMoney(
                                                                                item.discount_cents,
                                                                            )}
                                                                        </p>
                                                                    ) : null}
                                                                </div>
                                                            </div>
                                                        );
                                                    },
                                                )
                                            )}
                                        </div>

                                        {saleDiscount > 0 ? (
                                            <div className="flex justify-between border-t border-border/60 pt-2 text-xs text-muted-foreground">
                                                <span>
                                                    Desconto geral na comanda
                                                </span>
                                                <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                                    -{formatMoney(saleDiscount)}
                                                </span>
                                            </div>
                                        ) : null}
                                    </div>
                                );
                            })}

                            {/* Consolidated Totals Box */}
                            <div className="space-y-2.5 rounded-xl border border-border bg-muted/30 p-5">
                                <div className="flex justify-between text-xs text-muted-foreground">
                                    <span>Total Bruto Consolidado</span>
                                    <span>{formatMoney(totalGrossCents)}</span>
                                </div>
                                {totalDiscountCents > 0 ? (
                                    <div className="flex justify-between text-xs text-emerald-600 dark:text-emerald-400">
                                        <span>Descontos Totais</span>
                                        <span>
                                            -{formatMoney(totalDiscountCents)}
                                        </span>
                                    </div>
                                ) : null}
                                <div className="flex items-center justify-between border-t border-border pt-3">
                                    <div>
                                        <span className="text-base font-bold text-foreground sm:text-lg">
                                            Total Geral Fechado
                                        </span>
                                        <p className="text-3xs text-muted-foreground">
                                            Valor consolidado de encerramento da
                                            visita
                                        </p>
                                    </div>
                                    <span className="font-display text-2xl font-extrabold text-foreground sm:text-3xl">
                                        {formatMoney(finalTotalCents)}
                                    </span>
                                </div>
                            </div>

                            {/* Internal Disclaimer */}
                            <p className="text-center text-3xs text-muted-foreground">
                                Documento interno de conferência e encerramento
                                operacional. Caldas Gestão SaaS.
                            </p>
                        </div>
                    </div>
                </div>
            </PageCanvas>

            {/* Print-Only Layout (Optimized for thermal printer & standard A4/ticket print) */}
            <div className="mx-auto hidden max-w-sm p-4 font-mono text-xs leading-relaxed text-black print:block">
                <div className="mb-3 border-b border-dashed border-black pb-3 text-center">
                    <h1 className="text-sm font-bold uppercase">
                        {tenantName}
                    </h1>
                    <p className="text-3xs">{unitName}</p>
                    <p className="mt-1 text-2xs">
                        *** RECIBO INTERNO OPERACIONAL ***
                    </p>
                    <p className="text-2xs">Nº {receiptNumber}</p>
                    <p className="text-2xs">{formatDateTime(issuedAt)}</p>
                </div>

                <div className="mb-3 border-b border-dashed border-black pb-2">
                    <p>
                        <span className="font-bold">Cliente:</span>{' '}
                        {customerName}
                    </p>
                    <p>
                        <span className="font-bold">Operador:</span>{' '}
                        {operatorName}
                    </p>
                    <p>
                        <span className="font-bold">Comandas:</span>{' '}
                        {salesList.length}
                    </p>
                </div>

                <div className="mb-3 space-y-3 border-b border-dashed border-black pb-2">
                    {salesList.map((s, idx) => {
                        const categoryName =
                            'category_name' in s
                                ? s.category_name
                                : s.category_name_snapshot ||
                                  s.category?.name ||
                                  'Geral';
                        const saleItems = 'items' in s ? (s.items ?? []) : [];
                        const saleTotal =
                            'final_amount_cents' in s
                                ? s.final_amount_cents
                                : 0;

                        return (
                            <div key={idx} className="space-y-1">
                                <div className="flex justify-between text-3xs font-bold">
                                    <span>
                                        [{categoryName}] #{s.id.slice(0, 6)}
                                    </span>
                                    <span>{formatMoney(saleTotal)}</span>
                                </div>
                                {saleItems.map((item, itemIdx) => {
                                    const itemName =
                                        'name' in item
                                            ? item.name
                                            : item.name_snapshot;

                                    return (
                                        <div
                                            key={itemIdx}
                                            className="flex justify-between pl-2 text-2xs"
                                        >
                                            <span>
                                                {item.quantity}x {itemName}
                                            </span>
                                            <span>
                                                {formatMoney(item.total_cents)}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>

                <div className="mb-4 space-y-1 pt-1 text-right">
                    <div className="flex justify-between">
                        <span>Subtotal Bruto:</span>
                        <span>{formatMoney(totalGrossCents)}</span>
                    </div>
                    {totalDiscountCents > 0 ? (
                        <div className="flex justify-between">
                            <span>Descontos:</span>
                            <span>-{formatMoney(totalDiscountCents)}</span>
                        </div>
                    ) : null}
                    <div className="flex justify-between border-t border-black pt-1 text-sm font-bold">
                        <span>TOTAL:</span>
                        <span>{formatMoney(finalTotalCents)}</span>
                    </div>
                </div>

                <div className="border-t border-dashed border-black pt-3 text-center text-[9px]">
                    <p>Obrigado pela preferência!</p>
                    <p>Caldas Gestão • www.caldasgestao.com.br</p>
                </div>
            </div>
        </>
    );
}

ClosingSessionShow.layout = {
    breadcrumbs: [
        { title: 'Comandas', href: sales.index() },
        { title: 'Recibo Interno', href: '#' },
    ],
};
