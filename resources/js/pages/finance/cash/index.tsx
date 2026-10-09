import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Calculator,
    ChevronDown,
    History,
    Lock,
    MinusCircle,
    Plus,
    PlusCircle,
    TrendingDown,
    TrendingUp,
    User,
} from 'lucide-react';
import { useState } from 'react';
import {
    useIdempotencyKey,
    DenominationShortcuts,
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    MoneyInput,
    PageCanvas,
    ResourceHeader,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import cashShifts from '@/routes/cash_shifts';
import type {
    CashMetrics,
    CashMovementType,
    CashShift,
    SharedPageProps,
} from '@/types';

type Props = {
    active_shift: CashShift | null;
    metrics: CashMetrics;
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
    sale_reversal_outflow: {
        label: 'Estorno de Venda (Saída)',
        bgClass:
            'border-orange-300 bg-orange-50 text-orange-700 dark:border-orange-800 dark:bg-orange-950/40 dark:text-orange-300',
        isCredit: false,
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

export default function CashIndex({ active_shift, metrics }: Props) {
    const { props } = usePage<SharedPageProps>();
    const permissions = new Set(props.auth.permissions);

    const canOpen =
        permissions.has('cash_shift.open') ||
        permissions.has('cash_shift.manage');
    const canMove =
        permissions.has('cash_shift.move') ||
        permissions.has('cash_shift.manage');
    const canClose =
        permissions.has('cash_shift.close') ||
        permissions.has('cash_shift.manage');

    const [openModalOpen, setOpenModalOpen] = useState(false);
    const [supplyModalOpen, setSupplyModalOpen] = useState(false);
    const [bleedModalOpen, setBleedModalOpen] = useState(false);
    const [closeModalOpen, setCloseModalOpen] = useState(false);
    const [openShiftKey, rotateOpenShiftKey] = useIdempotencyKey(
        'cash-shift-open',
        props.auth.user?.id ?? 'anonymous',
    );
    const [supplyKey, rotateSupplyKey] = useIdempotencyKey(
        'cash-supply',
        active_shift?.id,
    );
    const [bleedKey, rotateBleedKey] = useIdempotencyKey(
        'cash-bleed',
        active_shift?.id,
    );
    const [closeShiftKey, rotateCloseShiftKey] = useIdempotencyKey(
        'cash-close',
        active_shift?.id,
    );

    // Initial Shift Form
    const [initialAmountFloat, setInitialAmountFloat] = useState('0,00');
    const [initialAmountCents, setInitialAmountCents] = useState(0);
    const [openNotes, setOpenNotes] = useState('');

    // Movement Forms (Suprimento & Sangria)
    const [supplyAmountFloat, setSupplyAmountFloat] = useState('');
    const [supplyAmountCents, setSupplyAmountCents] = useState(0);
    const [supplyReason, setSupplyReason] = useState('');

    const [bleedAmountFloat, setBleedAmountFloat] = useState('');
    const [bleedAmountCents, setBleedAmountCents] = useState(0);
    const [bleedReason, setBleedReason] = useState('');

    // Close Shift Form
    const [finalAmountFloat, setFinalAmountFloat] = useState('');
    const [finalAmountCents, setFinalAmountCents] = useState(0);
    const [closeNotes, setCloseNotes] = useState('');
    const [showDenominationCount, setShowDenominationCount] = useState(false);
    const [denominationCounts, setDenominationCounts] = useState<
        Record<number, number>
    >({});

    const denominationOptions = [
        { label: 'R$ 2', cents: 200 },
        { label: 'R$ 5', cents: 500 },
        { label: 'R$ 10', cents: 1000 },
        { label: 'R$ 20', cents: 2000 },
        { label: 'R$ 50', cents: 5000 },
        { label: 'R$ 100', cents: 10000 },
        { label: 'R$ 200', cents: 20000 },
        { label: 'R$ 0,05', cents: 5 },
        { label: 'R$ 0,10', cents: 10 },
        { label: 'R$ 0,25', cents: 25 },
        { label: 'R$ 0,50', cents: 50 },
        { label: 'R$ 1,00', cents: 100 },
    ] as const;

    const denominationTotalCents = denominationOptions.reduce(
        (total, { cents }) => total + cents * (denominationCounts[cents] ?? 0),
        0,
    );

    const calculatedDifferenceCents = active_shift
        ? finalAmountCents - active_shift.expected_amount_cents
        : 0;

    const totalSuppliesCents =
        active_shift?.movements
            ?.filter((m) => m.type === 'supply' || m.type === 'sale_inflow')
            .reduce((acc, m) => acc + m.amount_cents, 0) ?? 0;

    const totalBleedsCents =
        active_shift?.movements
            ?.filter((m) => m.type !== 'supply' && m.type !== 'sale_inflow')
            .reduce((acc, m) => acc + m.amount_cents, 0) ?? 0;

    return (
        <PageCanvas>
            <Head title="Caixa Operacional" />

            <ResourceHeader
                title="Caixa Operacional"
                description="Gestão de abertura de turno, suprimentos, sangrias e fechamento com conferência física."
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={cashShifts.history()}>
                                <History className="mr-2 h-4 w-4" />
                                Histórico de Turnos
                            </Link>
                        </Button>

                        {!active_shift && canOpen && (
                            <Button
                                size="sm"
                                className="bg-primary text-primary-foreground"
                                onClick={() => setOpenModalOpen(true)}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Abrir Turno de Caixa
                            </Button>
                        )}
                    </div>
                }
            />

            {/* Modal de Abertura de Caixa Independente */}
            {canOpen && (
                <Dialog
                    open={openModalOpen}
                    onOpenChange={(open) => {
                        if (open) {
                            rotateOpenShiftKey();
                        }

                        setOpenModalOpen(open);
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Abrir Novo Turno de Caixa</DialogTitle>
                            <DialogDescription>
                                Informe o fundo de troco (saldo inicial) para
                                iniciar a operação.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            {...cashShifts.store.form()}
                            headers={{
                                'X-Idempotency-Key': openShiftKey,
                            }}
                            onChange={rotateOpenShiftKey}
                            onSuccess={() => {
                                rotateOpenShiftKey();
                                setOpenModalOpen(false);
                                setInitialAmountCents(0);
                                setInitialAmountFloat('0,00');
                                setOpenNotes('');
                            }}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />

                                    <FormField
                                        label="Saldo Inicial de Fundo de Troco (R$)"
                                        name="initial_amount_cents"
                                        required
                                        error={errors.initial_amount_cents}
                                    >
                                        <MoneyInput
                                            id="initial_amount"
                                            prefix="R$"
                                            className="text-lg font-bold"
                                            placeholder="0,00"
                                            value={initialAmountFloat}
                                            onValueChange={(
                                                cents,
                                                formatted,
                                            ) => {
                                                setInitialAmountCents(cents);
                                                setInitialAmountFloat(
                                                    formatted,
                                                );
                                            }}
                                            required
                                        />
                                        <input
                                            type="hidden"
                                            name="initial_amount_cents"
                                            value={initialAmountCents}
                                        />
                                        <DenominationShortcuts
                                            disabled={processing}
                                            onAdd={(amountInReais) => {
                                                const nextCents =
                                                    initialAmountCents +
                                                    amountInReais * 100;
                                                setInitialAmountCents(
                                                    nextCents,
                                                );
                                                setInitialAmountFloat(
                                                    formatMoney(
                                                        nextCents,
                                                        'R$',
                                                    ),
                                                );
                                            }}
                                            onReset={() => {
                                                setInitialAmountCents(0);
                                                setInitialAmountFloat('0,00');
                                            }}
                                        />
                                    </FormField>

                                    <FormField
                                        label="Observações de Abertura"
                                        name="notes"
                                        error={errors.notes}
                                    >
                                        <Textarea
                                            id="open-notes"
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                            placeholder="Ex: Gaveta 1, troco em notas miúdas"
                                            value={openNotes}
                                            onChange={(e) =>
                                                setOpenNotes(e.target.value)
                                            }
                                            name="notes"
                                        />
                                    </FormField>

                                    <FormActions
                                        submitLabel="Confirmar Abertura"
                                        submittingLabel="Abrindo..."
                                        isSubmitting={processing}
                                        onCancel={() => setOpenModalOpen(false)}
                                    />
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}

            {!active_shift ? (
                /* Estado: Caixa Fechado */
                <div className="space-y-6">
                    <div className="rounded-xl border border-border/80 bg-card/60 p-6 text-card-foreground shadow-sm">
                        <div className="flex flex-col items-center justify-center py-10 text-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Lock className="h-8 w-8" />
                            </div>
                            <h3 className="mt-4 text-xl font-bold tracking-tight">
                                Nenhum turno de caixa aberto no momento
                            </h3>
                            <p className="mt-2 max-w-md text-sm text-muted-foreground">
                                Para registrar vendas em dinheiro, sangrias e
                                suprimentos, é necessário abrir um turno
                                operacional com o valor inicial do fundo de
                                troco.
                            </p>

                            {canOpen ? (
                                <Button
                                    size="lg"
                                    className="mt-6 font-semibold"
                                    onClick={() => setOpenModalOpen(true)}
                                >
                                    <Plus className="mr-2 h-5 w-5" />
                                    Abrir Turno de Caixa Agora
                                </Button>
                            ) : (
                                <p className="mt-4 text-xs text-amber-600 dark:text-amber-400">
                                    Seu perfil não possui permissão para abrir
                                    turnos de caixa.
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="rounded-lg border border-border bg-card p-4">
                            <span className="text-xs font-semibold text-muted-foreground">
                                Turnos Abertos na Unidade
                            </span>
                            <p className="mt-1 text-2xl font-bold tracking-tight">
                                {metrics.open_shifts_count}
                            </p>
                        </div>
                        <div className="rounded-lg border border-border bg-card p-4">
                            <span className="text-xs font-semibold text-muted-foreground">
                                Turnos Encerrados Hoje
                            </span>
                            <p className="mt-1 text-2xl font-bold tracking-tight">
                                {metrics.closed_today_count}
                            </p>
                        </div>
                    </div>
                </div>
            ) : (
                /* Estado: Caixa Ativo / Aberto */
                <div className="space-y-6">
                    {/* Header Cards do Caixa Ativo */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-xl border border-emerald-300 bg-emerald-50/50 p-5 shadow-sm dark:border-emerald-800/80 dark:bg-emerald-950/20">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold tracking-wider text-emerald-800 uppercase dark:text-emerald-300">
                                    Saldo Atual em Caixa
                                </span>
                                <Badge className="bg-emerald-600 text-white hover:bg-emerald-700">
                                    Aberto
                                </Badge>
                            </div>
                            <p className="mt-2 text-3xl font-extrabold tracking-tight text-emerald-900 dark:text-emerald-100">
                                {formatMoney(
                                    active_shift.expected_amount_cents,
                                )}
                            </p>
                            <span className="text-xs text-emerald-700 dark:text-emerald-400">
                                Saldo esperado em gaveta
                            </span>
                        </div>

                        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                            <span className="text-xs font-semibold text-muted-foreground">
                                Fundo de Troco (Inicial)
                            </span>
                            <p className="mt-2 text-2xl font-bold tracking-tight">
                                {formatMoney(active_shift.initial_amount_cents)}
                            </p>
                            <span className="text-xs text-muted-foreground">
                                Aberto em{' '}
                                {formatDateTime(active_shift.opened_at)}
                            </span>
                        </div>

                        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-muted-foreground">
                                    Total de Entradas / Suprimentos
                                </span>
                                <TrendingUp className="h-4 w-4 text-emerald-600" />
                            </div>
                            <p className="mt-2 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                                +{formatMoney(totalSuppliesCents)}
                            </p>
                            <span className="text-xs text-muted-foreground">
                                Reforços e recebimentos
                            </span>
                        </div>

                        <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-muted-foreground">
                                    Total de Saídas / Sangrias
                                </span>
                                <TrendingDown className="h-4 w-4 text-rose-600" />
                            </div>
                            <p className="mt-2 text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                                -{formatMoney(totalBleedsCents)}
                            </p>
                            <span className="text-xs text-muted-foreground">
                                Retiradas e despesas
                            </span>
                        </div>
                    </div>

                    {/* Informações do Turno e Ações Rápidas */}
                    <div className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <User className="h-5 w-5" />
                            </div>
                            <div>
                                <h4 className="text-sm font-semibold">
                                    Operador:{' '}
                                    {active_shift.opened_by?.name ?? 'Operador'}
                                </h4>
                                <p className="text-xs text-muted-foreground">
                                    Turno iniciado às{' '}
                                    {formatDateTime(active_shift.opened_at)}
                                    {active_shift.notes
                                        ? ` • ${active_shift.notes}`
                                        : ''}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {/* Modal de Suprimento */}
                            {canMove && (
                                <Dialog
                                    open={supplyModalOpen}
                                    onOpenChange={(open) => {
                                        if (open) {
                                            rotateSupplyKey();
                                        }

                                        setSupplyModalOpen(open);
                                    }}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                                        >
                                            <PlusCircle className="mr-1.5 h-4 w-4" />
                                            Suprimento (+)
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Registrar Suprimento de Caixa
                                            </DialogTitle>
                                            <DialogDescription>
                                                Adicione fundos à gaveta de
                                                dinheiro (reforço de troco).
                                            </DialogDescription>
                                        </DialogHeader>

                                        <Form
                                            {...cashShifts.move.form({
                                                cashShift: active_shift.id,
                                            })}
                                            headers={{
                                                'X-Idempotency-Key': supplyKey,
                                            }}
                                            onChange={rotateSupplyKey}
                                            onSuccess={() => {
                                                rotateSupplyKey();
                                                setSupplyModalOpen(false);
                                                setSupplyAmountCents(0);
                                                setSupplyAmountFloat('');
                                                setSupplyReason('');
                                            }}
                                            className="space-y-4"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <FormErrorSummary
                                                        errors={errors}
                                                    />

                                                    <input
                                                        type="hidden"
                                                        name="type"
                                                        value="supply"
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="lock_version"
                                                        value={
                                                            active_shift.lock_version
                                                        }
                                                    />

                                                    <FormField
                                                        label="Valor do Suprimento (R$)"
                                                        name="amount_cents"
                                                        required
                                                        error={
                                                            errors.amount_cents
                                                        }
                                                    >
                                                        <MoneyInput
                                                            id="supply_amount"
                                                            prefix="R$"
                                                            className="text-lg font-bold"
                                                            placeholder="0,00"
                                                            value={
                                                                supplyAmountFloat
                                                            }
                                                            onValueChange={(
                                                                cents,
                                                                formatted,
                                                            ) => {
                                                                setSupplyAmountCents(
                                                                    cents,
                                                                );
                                                                setSupplyAmountFloat(
                                                                    formatted,
                                                                );
                                                            }}
                                                            required
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="amount_cents"
                                                            value={
                                                                supplyAmountCents
                                                            }
                                                        />
                                                        <DenominationShortcuts
                                                            disabled={
                                                                processing
                                                            }
                                                            onAdd={(
                                                                amountInReais,
                                                            ) => {
                                                                const nextCents =
                                                                    supplyAmountCents +
                                                                    amountInReais *
                                                                        100;
                                                                setSupplyAmountCents(
                                                                    nextCents,
                                                                );
                                                                setSupplyAmountFloat(
                                                                    formatMoney(
                                                                        nextCents,
                                                                        'R$',
                                                                    ),
                                                                );
                                                            }}
                                                            onReset={() => {
                                                                setSupplyAmountCents(
                                                                    0,
                                                                );
                                                                setSupplyAmountFloat(
                                                                    '0,00',
                                                                );
                                                            }}
                                                        />
                                                    </FormField>

                                                    <FormField
                                                        label="Motivo / Justificativa"
                                                        name="reason"
                                                        required
                                                        error={errors.reason}
                                                    >
                                                        <Input
                                                            type="text"
                                                            placeholder="Ex: Troco adicional em moedas"
                                                            value={supplyReason}
                                                            onChange={(e) =>
                                                                setSupplyReason(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            name="reason"
                                                        />
                                                    </FormField>

                                                    <FormActions
                                                        submitLabel="Confirmar Entrada"
                                                        submittingLabel="Registrando..."
                                                        isSubmitting={
                                                            processing
                                                        }
                                                        onCancel={() =>
                                                            setSupplyModalOpen(
                                                                false,
                                                            )
                                                        }
                                                    />
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            )}

                            {/* Modal de Sangria */}
                            {canMove && (
                                <Dialog
                                    open={bleedModalOpen}
                                    onOpenChange={(open) => {
                                        if (open) {
                                            rotateBleedKey();
                                        }

                                        setBleedModalOpen(open);
                                    }}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="border-rose-300 text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40"
                                        >
                                            <MinusCircle className="mr-1.5 h-4 w-4" />
                                            Sangria (-)
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Registrar Sangria de Caixa
                                            </DialogTitle>
                                            <DialogDescription>
                                                Retire valores da gaveta para
                                                cofre, depósito ou despesas.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <Form
                                            {...cashShifts.move.form({
                                                cashShift: active_shift.id,
                                            })}
                                            headers={{
                                                'X-Idempotency-Key': bleedKey,
                                            }}
                                            onChange={rotateBleedKey}
                                            onSuccess={() => {
                                                rotateBleedKey();
                                                setBleedModalOpen(false);
                                                setBleedAmountCents(0);
                                                setBleedAmountFloat('');
                                                setBleedReason('');
                                            }}
                                            className="space-y-4"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <FormErrorSummary
                                                        errors={errors}
                                                    />

                                                    <input
                                                        type="hidden"
                                                        name="type"
                                                        value="bleed"
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="lock_version"
                                                        value={
                                                            active_shift.lock_version
                                                        }
                                                    />

                                                    <div className="rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground">
                                                        Saldo disponível para
                                                        sangria:{' '}
                                                        <strong className="text-foreground">
                                                            {formatMoney(
                                                                active_shift.expected_amount_cents,
                                                            )}
                                                        </strong>
                                                    </div>

                                                    <FormField
                                                        label="Valor da Sangria (R$)"
                                                        name="amount_cents"
                                                        required
                                                        error={
                                                            errors.amount_cents
                                                        }
                                                    >
                                                        <MoneyInput
                                                            id="bleed_amount"
                                                            prefix="R$"
                                                            className="text-lg font-bold"
                                                            placeholder="0,00"
                                                            value={
                                                                bleedAmountFloat
                                                            }
                                                            onValueChange={(
                                                                cents,
                                                                formatted,
                                                            ) => {
                                                                setBleedAmountCents(
                                                                    cents,
                                                                );
                                                                setBleedAmountFloat(
                                                                    formatted,
                                                                );
                                                            }}
                                                            required
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="amount_cents"
                                                            value={
                                                                bleedAmountCents
                                                            }
                                                        />
                                                        <DenominationShortcuts
                                                            disabled={
                                                                processing
                                                            }
                                                            onAdd={(
                                                                amountInReais,
                                                            ) => {
                                                                const nextCents =
                                                                    bleedAmountCents +
                                                                    amountInReais *
                                                                        100;
                                                                setBleedAmountCents(
                                                                    nextCents,
                                                                );
                                                                setBleedAmountFloat(
                                                                    formatMoney(
                                                                        nextCents,
                                                                        'R$',
                                                                    ),
                                                                );
                                                            }}
                                                            onReset={() => {
                                                                setBleedAmountCents(
                                                                    0,
                                                                );
                                                                setBleedAmountFloat(
                                                                    '0,00',
                                                                );
                                                            }}
                                                        />
                                                    </FormField>

                                                    <FormField
                                                        label="Motivo / Destino do Valor"
                                                        name="reason"
                                                        required
                                                        error={errors.reason}
                                                    >
                                                        <Input
                                                            type="text"
                                                            placeholder="Ex: Transferência para cofre principal"
                                                            value={bleedReason}
                                                            onChange={(e) =>
                                                                setBleedReason(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            name="reason"
                                                        />
                                                    </FormField>

                                                    <FormActions
                                                        submitLabel="Confirmar Retirada"
                                                        submittingLabel="Registrando..."
                                                        isSubmitting={
                                                            processing
                                                        }
                                                        onCancel={() =>
                                                            setBleedModalOpen(
                                                                false,
                                                            )
                                                        }
                                                    />
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            )}

                            {/* Modal de Fechamento de Caixa */}
                            {canClose && (
                                <Dialog
                                    open={closeModalOpen}
                                    onOpenChange={(open) => {
                                        if (open) {
                                            rotateCloseShiftKey();
                                        }

                                        setCloseModalOpen(open);
                                    }}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            size="sm"
                                            className="bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                                        >
                                            <Lock className="mr-1.5 h-4 w-4" />
                                            Fechar Turno
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-lg">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Fechamento e Conferência de
                                                Caixa
                                            </DialogTitle>
                                            <DialogDescription>
                                                Realize a contagem física do
                                                dinheiro em gaveta e informe o
                                                valor final apurado.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <Form
                                            {...cashShifts.close.form({
                                                cashShift: active_shift.id,
                                            })}
                                            headers={{
                                                'X-Idempotency-Key':
                                                    closeShiftKey,
                                            }}
                                            onChange={rotateCloseShiftKey}
                                            onSuccess={() => {
                                                rotateCloseShiftKey();
                                                setCloseModalOpen(false);
                                                setFinalAmountCents(0);
                                                setFinalAmountFloat('');
                                                setCloseNotes('');
                                                setShowDenominationCount(false);
                                                setDenominationCounts({});
                                            }}
                                            className="space-y-4"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <FormErrorSummary
                                                        errors={errors}
                                                    />

                                                    <input
                                                        type="hidden"
                                                        name="lock_version"
                                                        value={
                                                            active_shift.lock_version
                                                        }
                                                    />

                                                    {/* Resumo do Turno */}
                                                    <div className="grid grid-cols-2 gap-3 rounded-lg border border-border bg-muted/40 p-3 text-xs">
                                                        <div>
                                                            <span className="text-muted-foreground">
                                                                Fundo Inicial:
                                                            </span>
                                                            <p className="font-semibold">
                                                                {formatMoney(
                                                                    active_shift.initial_amount_cents,
                                                                )}
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <span className="text-muted-foreground">
                                                                Saldo Esperado:
                                                            </span>
                                                            <p className="text-sm font-bold text-foreground">
                                                                {formatMoney(
                                                                    active_shift.expected_amount_cents,
                                                                )}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <FormField
                                                        label="Valor Total Contado na Gaveta (R$)"
                                                        name="final_amount_cents"
                                                        required
                                                        error={
                                                            errors.final_amount_cents
                                                        }
                                                    >
                                                        <MoneyInput
                                                            id="final_amount"
                                                            prefix="R$"
                                                            className="text-xl font-black"
                                                            placeholder="0,00"
                                                            value={
                                                                finalAmountFloat
                                                            }
                                                            onValueChange={(
                                                                cents,
                                                                formatted,
                                                            ) => {
                                                                setFinalAmountCents(
                                                                    cents,
                                                                );
                                                                setFinalAmountFloat(
                                                                    formatted,
                                                                );
                                                            }}
                                                            autoFocus
                                                            required
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="final_amount_cents"
                                                            value={
                                                                finalAmountCents
                                                            }
                                                        />
                                                        <DenominationShortcuts
                                                            disabled={
                                                                processing
                                                            }
                                                            onAdd={(
                                                                amountInReais,
                                                            ) => {
                                                                const nextCents =
                                                                    finalAmountCents +
                                                                    amountInReais *
                                                                        100;
                                                                setFinalAmountCents(
                                                                    nextCents,
                                                                );
                                                                setFinalAmountFloat(
                                                                    formatMoney(
                                                                        nextCents,
                                                                        'R$',
                                                                    ),
                                                                );
                                                            }}
                                                            onReset={() => {
                                                                setFinalAmountCents(
                                                                    0,
                                                                );
                                                                setFinalAmountFloat(
                                                                    '0,00',
                                                                );
                                                            }}
                                                        />
                                                        <p className="mt-2 text-xs text-muted-foreground">
                                                            Conte todo o
                                                            dinheiro físico da
                                                            gaveta, incluindo o
                                                            fundo e os
                                                            recebimentos. O
                                                            sistema compara esse
                                                            total com o esperado
                                                            do turno.
                                                        </p>
                                                    </FormField>

                                                    <div className="rounded-lg border border-dashed border-border bg-muted/20">
                                                        <button
                                                            type="button"
                                                            className="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm font-medium text-foreground hover:bg-muted/40"
                                                            aria-expanded={
                                                                showDenominationCount
                                                            }
                                                            onClick={() =>
                                                                setShowDenominationCount(
                                                                    (current) =>
                                                                        !current,
                                                                )
                                                            }
                                                        >
                                                            <span className="flex items-center gap-2">
                                                                <Calculator className="size-4 text-muted-foreground" />
                                                                Conferir por
                                                                denominações
                                                                <span className="text-xs font-normal text-muted-foreground">
                                                                    (opcional)
                                                                </span>
                                                            </span>
                                                            <ChevronDown
                                                                className={`size-4 transition-transform ${showDenominationCount ? 'rotate-180' : ''}`}
                                                            />
                                                        </button>

                                                        {showDenominationCount && (
                                                            <div className="space-y-3 border-t border-dashed border-border px-3 py-3">
                                                                <p className="text-xs text-muted-foreground">
                                                                    Informe
                                                                    quantidades
                                                                    apenas se
                                                                    isso ajudar
                                                                    sua
                                                                    conferência.
                                                                    Não existe
                                                                    uma
                                                                    combinação
                                                                    obrigatória
                                                                    de notas.
                                                                </p>
                                                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                                    {denominationOptions.map(
                                                                        ({
                                                                            label,
                                                                            cents,
                                                                        }) => (
                                                                            <label
                                                                                key={
                                                                                    cents
                                                                                }
                                                                                className="space-y-1"
                                                                            >
                                                                                <span className="text-[11px] font-medium text-muted-foreground">
                                                                                    {
                                                                                        label
                                                                                    }
                                                                                </span>
                                                                                <Input
                                                                                    type="number"
                                                                                    min="0"
                                                                                    step="1"
                                                                                    inputMode="numeric"
                                                                                    aria-label={`Quantidade de ${label}`}
                                                                                    value={
                                                                                        denominationCounts[
                                                                                            cents
                                                                                        ] ??
                                                                                        ''
                                                                                    }
                                                                                    onChange={(
                                                                                        event,
                                                                                    ) => {
                                                                                        const value =
                                                                                            Math.max(
                                                                                                0,
                                                                                                Number.parseInt(
                                                                                                    event
                                                                                                        .target
                                                                                                        .value,
                                                                                                    10,
                                                                                                ) ||
                                                                                                    0,
                                                                                            );
                                                                                        setDenominationCounts(
                                                                                            (
                                                                                                current,
                                                                                            ) => ({
                                                                                                ...current,
                                                                                                [cents]:
                                                                                                    value,
                                                                                            }),
                                                                                        );
                                                                                    }}
                                                                                    className="h-8"
                                                                                />
                                                                            </label>
                                                                        ),
                                                                    )}
                                                                </div>
                                                                <div className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-background px-2.5 py-2 text-xs">
                                                                    <span className="text-muted-foreground">
                                                                        Total
                                                                        conferido
                                                                        por
                                                                        denominações
                                                                    </span>
                                                                    <span className="font-semibold text-foreground">
                                                                        {formatMoney(
                                                                            denominationTotalCents,
                                                                        )}
                                                                    </span>
                                                                </div>
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    disabled={
                                                                        processing ||
                                                                        denominationTotalCents ===
                                                                            0
                                                                    }
                                                                    onClick={() => {
                                                                        setFinalAmountCents(
                                                                            denominationTotalCents,
                                                                        );
                                                                        setFinalAmountFloat(
                                                                            formatMoney(
                                                                                denominationTotalCents,
                                                                                'R$',
                                                                            ),
                                                                        );
                                                                    }}
                                                                >
                                                                    Usar total
                                                                    contado
                                                                </Button>
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* Indicador de Diferença em Tempo Real */}
                                                    <div
                                                        className={`rounded-lg border p-3.5 text-sm transition-colors ${
                                                            calculatedDifferenceCents ===
                                                            0
                                                                ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                                : calculatedDifferenceCents >
                                                                    0
                                                                  ? 'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300'
                                                                  : 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300'
                                                        }`}
                                                    >
                                                        <div className="flex items-center justify-between font-semibold">
                                                            <span>
                                                                {calculatedDifferenceCents ===
                                                                0
                                                                    ? '✓ Caixa Exato (Sem divergência)'
                                                                    : calculatedDifferenceCents >
                                                                        0
                                                                      ? '↑ Sobra de Caixa'
                                                                      : '↓ Quebra / Falta de Caixa'}
                                                            </span>
                                                            <span className="text-base font-bold">
                                                                {calculatedDifferenceCents >
                                                                0
                                                                    ? '+'
                                                                    : ''}
                                                                {formatMoney(
                                                                    calculatedDifferenceCents,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="mt-1 text-xs opacity-80">
                                                            Esperado:{' '}
                                                            {formatMoney(
                                                                active_shift.expected_amount_cents,
                                                            )}{' '}
                                                            | Informado:{' '}
                                                            {formatMoney(
                                                                finalAmountCents,
                                                            )}
                                                        </p>
                                                    </div>

                                                    <FormField
                                                        label="Observações / Justificativa de Fechamento"
                                                        name="notes"
                                                        error={errors.notes}
                                                    >
                                                        <Textarea
                                                            id="close-notes"
                                                            className="flex min-h-[70px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                            placeholder="Ex: Justificativa de eventuais sobras ou quebras"
                                                            value={closeNotes}
                                                            onChange={(e) =>
                                                                setCloseNotes(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            name="notes"
                                                        />
                                                    </FormField>

                                                    <FormActions
                                                        submitLabel="Encerrar Turno de Caixa"
                                                        submittingLabel="Encerrando..."
                                                        isSubmitting={
                                                            processing
                                                        }
                                                        onCancel={() =>
                                                            setCloseModalOpen(
                                                                false,
                                                            )
                                                        }
                                                    />
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            )}
                        </div>
                    </div>

                    {/* Tabela de Movimentações do Turno */}
                    <div className="rounded-xl border border-border bg-card shadow-sm">
                        <div className="flex items-center justify-between border-b border-border px-6 py-4">
                            <div>
                                <h3 className="text-base font-semibold">
                                    Movimentações do Turno
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Histórico de todas as entradas, sangrias e
                                    operações registradas neste turno
                                </p>
                            </div>
                            <span className="text-xs font-semibold text-muted-foreground">
                                {active_shift.movements?.length ?? 0}{' '}
                                lançamentos
                            </span>
                        </div>

                        {!active_shift.movements ||
                        active_shift.movements.length === 0 ? (
                            <EmptyState
                                title="Nenhuma movimentação avulsa lançada"
                                description="Realize suprimentos ou sangrias para movimentar o caixa operacional."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-6 py-3">Tipo</th>
                                            <th className="px-6 py-3">
                                                Motivo / Descrição
                                            </th>
                                            <th className="px-6 py-3">
                                                Responsável
                                            </th>
                                            <th className="px-6 py-3">
                                                Horário
                                            </th>
                                            <th className="px-6 py-3 text-right">
                                                Valor
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {active_shift.movements.map(
                                            (movement) => {
                                                const config =
                                                    movementTypeConfig[
                                                        movement.type
                                                    ] ??
                                                    movementTypeConfig.supply;

                                                return (
                                                    <tr
                                                        key={movement.id}
                                                        className="transition-colors hover:bg-muted/30"
                                                    >
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <Badge
                                                                variant="outline"
                                                                className={`font-semibold ${config.bgClass}`}
                                                            >
                                                                {config.label}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-6 py-4 font-medium text-foreground">
                                                            {movement.reason}
                                                            {movement.reference_type && (
                                                                <span className="ml-2 text-xs text-muted-foreground">
                                                                    (
                                                                    {
                                                                        movement.reference_type
                                                                    }
                                                                    )
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-muted-foreground">
                                                            {movement.user
                                                                ?.name ?? '—'}
                                                        </td>
                                                        <td className="px-6 py-4 text-xs whitespace-nowrap text-muted-foreground">
                                                            {formatDateTime(
                                                                movement.created_at,
                                                            )}
                                                        </td>
                                                        <td className="px-6 py-4 text-right font-bold whitespace-nowrap">
                                                            <span
                                                                className={
                                                                    config.isCredit
                                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                                        : 'text-rose-600 dark:text-rose-400'
                                                                }
                                                            >
                                                                {config.isCredit
                                                                    ? '+'
                                                                    : '-'}
                                                                {formatMoney(
                                                                    movement.amount_cents,
                                                                )}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                );
                                            },
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </PageCanvas>
    );
}
