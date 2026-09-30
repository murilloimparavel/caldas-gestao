import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    AlertCircle,
    CheckCircle2,
    FileText,
    MapPin,
    Printer,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import closingSessionPayments from '@/routes/closing-sessions/payments';
import sales from '@/routes/sales';
import type {
    ClosingSession,
    ClosingSessionPayment,
    PaymentMethod,
    SharedPageProps,
} from '@/types';

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

function formatPaymentMethod(method: PaymentMethod | null | undefined): string {
    const labels: Record<PaymentMethod, string> = {
        pix: 'PIX',
        debit_card: 'Cartão de débito',
        credit_card: 'Cartão de crédito',
        cash: 'Dinheiro',
        permuta: 'Permuta',
    };

    return method ? labels[method] : 'Não informado';
}

function paymentWasReversed(payment: ClosingSessionPayment): boolean {
    return Boolean(
        payment.is_reversal || payment.reversal || payment.reversal_of_id,
    );
}
export default function ClosingSessionShow({ session }: Props) {
    const payload = session.receipt_payload;
    const { props } = usePage<SharedPageProps>();
    const permissions = new Set(props.auth.permissions);
    const canReversePayment =
        permissions.has('sale.close') || permissions.has('sale.manage');
    const [reversePayment, setReversePayment] =
        useState<ClosingSessionPayment | null>(null);
    const [reverseKey, setReverseKey] = useState(() =>
        createIdempotencyKey(`closing-session-payment-reversal:${session.id}`),
    );

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
    const paymentMethod = payload?.payment_method ?? session.payment_method;
    const cashReceivedCents =
        payload?.cash_received_cents ?? session.cash_received_cents;
    const cashChangeCents =
        payload?.cash_change_cents ?? session.cash_change_cents;
    const payments = session.payments ?? [];
    const originalPayments = payments.filter((payment) => !payment.is_reversal);
    const paymentRows =
        originalPayments.length > 0 ? originalPayments : payments;

    return (
        <>
            <Head title={`Recibo Interno #${receiptNumber}`} />

            {/* Screen View */}
            <PageCanvas className="no-print print:hidden">
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
                                {paymentMethod === 'cash' &&
                                cashReceivedCents != null ? (
                                    <div>
                                        <span className="text-2xs font-bold tracking-wider text-muted-foreground uppercase">
                                            Dinheiro recebido / Troco
                                        </span>
                                        <p className="text-sm font-semibold text-foreground">
                                            {formatMoney(cashReceivedCents)}{' '}
                                            <span className="font-normal text-muted-foreground">
                                                /{' '}
                                                {formatMoney(
                                                    cashChangeCents ??
                                                        Math.max(
                                                            0,
                                                            cashReceivedCents -
                                                                finalTotalCents,
                                                        ),
                                                )}
                                            </span>
                                        </p>
                                    </div>
                                ) : null}
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
                                <div>
                                    <span className="text-2xs font-bold tracking-wider text-muted-foreground uppercase">
                                        Método de pagamento
                                    </span>
                                    <p className="text-sm font-semibold text-foreground">
                                        {formatPaymentMethod(paymentMethod)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Breakdown per Sale */}
                        <div className="space-y-6 p-6 sm:p-8">
                            <section className="space-y-3 rounded-xl border border-border bg-card/60 p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 className="font-display text-base font-semibold text-foreground">
                                            Histórico de pagamentos
                                        </h3>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Valores efetivamente aplicados no
                                            fechamento, com troco e auditoria de
                                            estornos.
                                        </p>
                                    </div>
                                    <Badge variant="secondary">
                                        {paymentRows.length}{' '}
                                        {paymentRows.length === 1
                                            ? 'método'
                                            : 'métodos'}
                                    </Badge>
                                </div>

                                {paymentRows.length === 0 ? (
                                    <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                        Este fechamento não possui os pagamentos
                                        detalhados disponíveis.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {paymentRows.map((payment) => {
                                            const isReversed =
                                                paymentWasReversed(payment);
                                            const reversal = payment.reversal;
                                            const isCash =
                                                payment.payment_method ===
                                                'cash';

                                            return (
                                                <div
                                                    key={payment.id}
                                                    className="rounded-lg border border-border/80 bg-background p-3"
                                                >
                                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                        <div className="flex items-start gap-3">
                                                            <div className="min-w-0">
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <p className="text-sm font-semibold text-foreground">
                                                                        {formatPaymentMethod(
                                                                            payment.payment_method,
                                                                        )}
                                                                    </p>
                                                                    {isReversed ? (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300"
                                                                        >
                                                                            Estornado
                                                                        </Badge>
                                                                    ) : (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                                                                        >
                                                                            Registrado
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                                <p className="mt-1 text-xs text-muted-foreground">
                                                                    Registrado
                                                                    em{' '}
                                                                    {formatDateTime(
                                                                        payment.recorded_at,
                                                                    )}{' '}
                                                                    por{' '}
                                                                    {payment
                                                                        .recorded_by
                                                                        ?.name ??
                                                                        'Operador'}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center gap-2 sm:shrink-0">
                                                            <span className="text-base font-bold text-foreground tabular-nums">
                                                                {formatMoney(
                                                                    payment.amount_cents,
                                                                )}
                                                            </span>
                                                            {!isReversed &&
                                                            canReversePayment ? (
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="gap-1.5 text-rose-700 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                                                    onClick={() => {
                                                                        setReverseKey(
                                                                            createIdempotencyKey(
                                                                                `closing-session-payment-reversal:${session.id}:${payment.id}`,
                                                                            ),
                                                                        );
                                                                        setReversePayment(
                                                                            payment,
                                                                        );
                                                                    }}
                                                                >
                                                                    <RotateCcw className="size-3.5" />
                                                                    Estornar
                                                                </Button>
                                                            ) : null}
                                                        </div>
                                                    </div>

                                                    <div className="mt-3 grid gap-2 border-t border-border/60 pt-3 text-xs sm:grid-cols-3">
                                                        <div>
                                                            <span className="text-muted-foreground">
                                                                Valor aplicado
                                                            </span>
                                                            <p className="mt-0.5 font-semibold tabular-nums">
                                                                {formatMoney(
                                                                    payment.amount_cents,
                                                                )}
                                                            </p>
                                                        </div>
                                                        {isCash ? (
                                                            <>
                                                                <div>
                                                                    <span className="text-muted-foreground">
                                                                        Recebido
                                                                    </span>
                                                                    <p className="mt-0.5 font-semibold tabular-nums">
                                                                        {formatMoney(
                                                                            payment.tendered_cents ??
                                                                                payment.amount_cents,
                                                                        )}
                                                                    </p>
                                                                </div>
                                                                <div>
                                                                    <span className="text-muted-foreground">
                                                                        Troco
                                                                    </span>
                                                                    <p className="mt-0.5 font-semibold tabular-nums">
                                                                        {formatMoney(
                                                                            payment.change_cents ??
                                                                                0,
                                                                        )}
                                                                    </p>
                                                                </div>
                                                            </>
                                                        ) : (
                                                            <div className="sm:col-span-2">
                                                                <span className="text-muted-foreground">
                                                                    Impacto na
                                                                    gaveta
                                                                </span>
                                                                <p className="mt-0.5 font-semibold text-muted-foreground">
                                                                    Não altera o
                                                                    saldo físico
                                                                    do caixa
                                                                </p>
                                                            </div>
                                                        )}
                                                    </div>

                                                    {reversal ? (
                                                        <div className="mt-3 rounded-md border border-rose-200 bg-rose-50/60 p-2.5 text-xs text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">
                                                            <p className="font-semibold">
                                                                Estorno
                                                                registrado em{' '}
                                                                {formatDateTime(
                                                                    reversal.recorded_at,
                                                                )}{' '}
                                                                por{' '}
                                                                {reversal
                                                                    .recorded_by
                                                                    ?.name ??
                                                                    'Operador'}
                                                            </p>
                                                            {reversal.reversal_reason ? (
                                                                <p className="mt-1">
                                                                    Motivo:{' '}
                                                                    {
                                                                        reversal.reversal_reason
                                                                    }
                                                                </p>
                                                            ) : null}
                                                        </div>
                                                    ) : null}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </section>

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
                                <div className="flex justify-between border-t border-border pt-2 text-xs text-muted-foreground">
                                    <span>Método de pagamento</span>
                                    <span className="font-semibold text-foreground">
                                        {formatPaymentMethod(paymentMethod)}
                                    </span>
                                </div>
                                {paymentMethod === 'cash' &&
                                cashReceivedCents != null ? (
                                    <div className="flex justify-between text-xs text-muted-foreground">
                                        <span>Recebido / Troco</span>
                                        <span className="font-semibold text-foreground">
                                            {formatMoney(cashReceivedCents)} /{' '}
                                            {formatMoney(
                                                cashChangeCents ??
                                                    Math.max(
                                                        0,
                                                        cashReceivedCents -
                                                            finalTotalCents,
                                                    ),
                                            )}
                                        </span>
                                    </div>
                                ) : null}
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

            <Dialog
                open={reversePayment !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReversePayment(null);
                    }
                }}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Estornar recebimento</DialogTitle>
                        <DialogDescription>
                            Registre um estorno compensatório com motivo
                            obrigatório para manter o histórico auditável.
                        </DialogDescription>
                    </DialogHeader>

                    {reversePayment ? (
                        <Form
                            {...closingSessionPayments.reverse.form({
                                closingSession: session.id,
                                payment: reversePayment.id,
                            })}
                            headers={{ 'X-Idempotency-Key': reverseKey }}
                            onSuccess={() => setReversePayment(null)}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />

                                    <div className="flex items-start gap-2.5 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                                        <AlertCircle className="mt-0.5 size-4 shrink-0" />
                                        <p>
                                            O estorno devolve o valor deste
                                            recebimento. Se o turno de caixa já
                                            estiver fechado, ele não será
                                            alterado retroativamente; o
                                            histórico manterá a compensação
                                            auditada.
                                        </p>
                                    </div>

                                    <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm">
                                        <div className="flex justify-between gap-3">
                                            <span className="text-muted-foreground">
                                                Recebimento
                                            </span>
                                            <span className="font-semibold">
                                                {formatPaymentMethod(
                                                    reversePayment.payment_method,
                                                )}
                                            </span>
                                        </div>
                                        <div className="mt-1 flex justify-between gap-3">
                                            <span className="text-muted-foreground">
                                                Valor aplicado
                                            </span>
                                            <span className="font-bold tabular-nums">
                                                {formatMoney(
                                                    reversePayment.amount_cents,
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    <FormField
                                        label="Motivo do estorno"
                                        name="reason"
                                        required
                                        error={errors.reason}
                                        description="Ex.: pagamento duplicado, cancelamento aprovado ou correção operacional."
                                    >
                                        <Textarea
                                            id="reversal_reason"
                                            name="reason"
                                            rows={4}
                                            required
                                            placeholder="Descreva por que este recebimento está sendo estornado..."
                                            autoFocus
                                        />
                                    </FormField>

                                    <FormActions
                                        processing={processing}
                                        submitLabel="Confirmar estorno"
                                        submittingLabel="Registrando estorno…"
                                        onCancel={() => setReversePayment(null)}
                                    />
                                </>
                            )}
                        </Form>
                    ) : null}
                </DialogContent>
            </Dialog>

            {/* Print-Only Layout (Optimized for thermal printer 80mm / 58mm) */}
            <div className="mx-auto hidden w-full max-w-[80mm] p-2 font-mono text-xs leading-relaxed break-words text-black tabular-nums print:block">
                <div className="mb-2 border-b border-dashed border-black pb-2 text-center">
                    <h1 className="text-sm font-bold uppercase">
                        {tenantName}
                    </h1>
                    <p className="text-3xs">{unitName}</p>
                    <p className="mt-1 text-2xs font-bold">
                        *** RECIBO INTERNO OPERACIONAL ***
                    </p>
                    <p className="text-2xs">Nº {receiptNumber}</p>
                    <p className="text-2xs">{formatDateTime(issuedAt)}</p>
                </div>

                <div className="mb-2 border-b border-dashed border-black pb-2 text-2xs">
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

                <div className="mb-2 space-y-2 border-b border-dashed border-black pb-2">
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
                            <div key={idx} className="space-y-0.5">
                                <div className="flex justify-between text-3xs font-bold">
                                    <span className="truncate pr-1">
                                        [{categoryName}] #{s.id.slice(0, 6)}
                                    </span>
                                    <span className="shrink-0">
                                        {formatMoney(saleTotal)}
                                    </span>
                                </div>
                                {saleItems.map((item, itemIdx) => {
                                    const itemName =
                                        'name' in item
                                            ? item.name
                                            : item.name_snapshot;

                                    return (
                                        <div
                                            key={itemIdx}
                                            className="flex justify-between pl-1 text-3xs"
                                        >
                                            <span className="truncate pr-1">
                                                {item.quantity}x {itemName}
                                            </span>
                                            <span className="shrink-0">
                                                {formatMoney(item.total_cents)}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>

                <div className="mb-3 space-y-1 pt-1 text-right text-2xs">
                    <div className="flex justify-between">
                        <span>Subtotal Bruto:</span>
                        <span className="font-semibold">
                            {formatMoney(totalGrossCents)}
                        </span>
                    </div>
                    {totalDiscountCents > 0 ? (
                        <div className="flex justify-between">
                            <span>Descontos:</span>
                            <span className="font-semibold">
                                -{formatMoney(totalDiscountCents)}
                            </span>
                        </div>
                    ) : null}
                    <div className="flex justify-between border-t border-black pt-1 text-xs font-bold">
                        <span>TOTAL:</span>
                        <span>{formatMoney(finalTotalCents)}</span>
                    </div>
                    <div className="flex justify-between pt-1 text-2xs">
                        <span>Pagamento:</span>
                        <span>{formatPaymentMethod(paymentMethod)}</span>
                    </div>
                    {paymentMethod === 'cash' && cashReceivedCents != null ? (
                        <>
                            <div className="flex justify-between text-2xs">
                                <span>Recebido:</span>
                                <span>{formatMoney(cashReceivedCents)}</span>
                            </div>
                            <div className="flex justify-between text-2xs">
                                <span>Troco:</span>
                                <span>
                                    {formatMoney(
                                        cashChangeCents ??
                                            Math.max(
                                                0,
                                                cashReceivedCents -
                                                    finalTotalCents,
                                            ),
                                    )}
                                </span>
                            </div>
                        </>
                    ) : null}
                </div>

                <div className="border-t border-dashed border-black pt-2 text-center text-[9px]">
                    <p>Obrigado pela preferência!</p>
                    <p>Caldas Gestão • www.caldasgestao.com.br</p>
                </div>

                <div className="mt-4 border-t border-dashed border-black pt-2 text-center text-[9px] tracking-widest uppercase">
                    - - - - - corte aqui - - - - -
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
