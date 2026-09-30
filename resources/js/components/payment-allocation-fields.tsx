import { Form } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Banknote,
    CreditCard,
    Plus,
    QrCode,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    MoneyInput,
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
import cashShifts from '@/routes/cash_shifts';
import type { CashShift, PaymentMethod } from '@/types';

type PaymentAllocation = {
    id: string;
    method: PaymentMethod;
    amount_cents: number;
    tendered_cents: number;
};

type Props = {
    totalCents: number;
    activeCashShift?: CashShift | null;
    errors?: Record<string, unknown>;
    onRequestOpenCashShift?: () => void;
    onValidityChange?: (isValid: boolean) => void;
};

const paymentMethods: Array<{
    value: PaymentMethod;
    label: string;
    description: string;
}> = [
    { value: 'pix', label: 'PIX', description: 'Transferência instantânea' },
    { value: 'debit_card', label: 'Débito', description: 'Cartão de débito' },
    {
        value: 'credit_card',
        label: 'Crédito',
        description: 'Cartão de crédito',
    },
    {
        value: 'cash',
        label: 'Dinheiro',
        description: 'Entrada física na gaveta',
    },
    { value: 'permuta', label: 'Permuta', description: 'Compensação ou troca' },
];

const methodIcons: Record<PaymentMethod, typeof QrCode> = {
    pix: QrCode,
    debit_card: CreditCard,
    credit_card: CreditCard,
    cash: Banknote,
    permuta: ArrowLeftRight,
};

function newAllocation(amountCents: number): PaymentAllocation {
    return {
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        method: 'pix',
        amount_cents: Math.max(0, amountCents),
        tendered_cents: Math.max(0, amountCents),
    };
}

function getError(
    errors: Record<string, unknown> | undefined,
    key: string,
): string | undefined {
    const value = errors?.[key];

    if (typeof value === 'string') {
        return value;
    }

    return Array.isArray(value) ? String(value[0]) : undefined;
}

export function PaymentAllocationFields({
    totalCents,
    activeCashShift,
    errors,
    onRequestOpenCashShift,
    onValidityChange,
}: Props) {
    const [allocations, setAllocations] = useState<PaymentAllocation[]>(() => [
        newAllocation(totalCents),
    ]);

    const allocatedCents = useMemo(
        () =>
            allocations.reduce(
                (sum, allocation) => sum + allocation.amount_cents,
                0,
            ),
        [allocations],
    );
    const remainingCents = totalCents - allocatedCents;
    const cashAllocations = allocations.filter(
        (allocation) => allocation.method === 'cash',
    );
    const cashAppliedCents = cashAllocations.reduce(
        (sum, allocation) => sum + allocation.amount_cents,
        0,
    );
    const cashTenderedCents = cashAllocations.reduce(
        (sum, allocation) =>
            sum + Math.max(allocation.tendered_cents, allocation.amount_cents),
        0,
    );
    const changeCents = Math.max(0, cashTenderedCents - cashAppliedCents);
    const hasCashWithoutShift = cashAllocations.length > 0 && !activeCashShift;
    const overageCents = Math.max(0, allocatedCents - totalCents);
    const isValid =
        remainingCents === 0 &&
        overageCents === 0 &&
        allocations.every(
            (allocation) =>
                allocation.amount_cents > 0 &&
                (allocation.method !== 'cash' ||
                    allocation.tendered_cents >= allocation.amount_cents),
        ) &&
        !hasCashWithoutShift;
    const allocationError = getError(errors, 'payment_allocations');

    useEffect(() => {
        onValidityChange?.(isValid);
    }, [isValid, onValidityChange]);

    const updateAllocation = (
        id: string,
        changes: Partial<PaymentAllocation>,
    ) => {
        setAllocations((current) =>
            current.map((allocation) =>
                allocation.id === id
                    ? { ...allocation, ...changes }
                    : allocation,
            ),
        );
    };

    const addAllocation = () => {
        const nextAmount = Math.max(0, remainingCents);
        setAllocations((current) => [...current, newAllocation(nextAmount)]);
    };

    const removeAllocation = (id: string) => {
        setAllocations((current) =>
            current.length === 1
                ? current
                : current.filter((allocation) => allocation.id !== id),
        );
    };

    return (
        <section
            className="space-y-3 rounded-xl border border-border bg-card/70 p-4"
            aria-labelledby="payment-allocation-title"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3
                        id="payment-allocation-title"
                        className="text-sm font-semibold text-foreground"
                    >
                        Como o cliente pagou?
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Divida entre métodos se precisar. O total aplicado
                        precisa fechar exatamente.
                    </p>
                </div>
                <Badge variant="secondary" className="shrink-0 tabular-nums">
                    {formatMoney(totalCents)}
                </Badge>
            </div>

            {allocations.map((allocation, index) => {
                const Icon = methodIcons[allocation.method];
                const methodError = getError(
                    errors,
                    `payment_allocations.${index}.amount_cents`,
                );
                const tenderedError = getError(
                    errors,
                    `payment_allocations.${index}.tendered_cents`,
                );

                return (
                    <div
                        key={allocation.id}
                        className="space-y-3 rounded-lg border border-border/80 bg-background p-3"
                    >
                        <div className="flex items-center gap-2">
                            <div className="flex size-8 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <Icon className="size-4" aria-hidden="true" />
                            </div>
                            <select
                                aria-label={`Método de pagamento ${index + 1}`}
                                value={allocation.method}
                                onChange={(event) =>
                                    updateAllocation(allocation.id, {
                                        method: event.target
                                            .value as PaymentMethod,
                                        tendered_cents:
                                            event.target.value === 'cash'
                                                ? Math.max(
                                                      allocation.tendered_cents,
                                                      allocation.amount_cents,
                                                  )
                                                : allocation.amount_cents,
                                    })
                                }
                                className="h-10 min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {paymentMethods.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label} — {item.description}
                                    </option>
                                ))}
                            </select>
                            {allocations.length > 1 ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-9 shrink-0 text-muted-foreground hover:text-destructive"
                                    onClick={() =>
                                        removeAllocation(allocation.id)
                                    }
                                    aria-label={`Remover pagamento ${index + 1}`}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            ) : null}
                        </div>

                        <div
                            className={
                                allocation.method === 'cash'
                                    ? 'grid gap-3 sm:grid-cols-2'
                                    : ''
                            }
                        >
                            <div>
                                <label
                                    htmlFor={`payment-allocation-${allocation.id}`}
                                    className="mb-1.5 block text-xs font-medium text-muted-foreground"
                                >
                                    Valor aplicado
                                </label>
                                <MoneyInput
                                    id={`payment-allocation-${allocation.id}`}
                                    prefix="R$"
                                    value={formatMoney(
                                        allocation.amount_cents,
                                        '',
                                    )}
                                    onValueChange={(cents) =>
                                        updateAllocation(allocation.id, {
                                            amount_cents: cents,
                                            tendered_cents:
                                                allocation.method === 'cash'
                                                    ? Math.max(
                                                          allocation.tendered_cents,
                                                          cents,
                                                      )
                                                    : cents,
                                        })
                                    }
                                    aria-describedby={
                                        methodError
                                            ? `payment-allocation-error-${allocation.id}`
                                            : undefined
                                    }
                                />
                                <input
                                    type="hidden"
                                    name={`payment_allocations[${index}][method]`}
                                    value={allocation.method}
                                />
                                <input
                                    type="hidden"
                                    name={`payment_allocations[${index}][amount_cents]`}
                                    value={allocation.amount_cents}
                                />
                                {methodError ? (
                                    <p
                                        id={`payment-allocation-error-${allocation.id}`}
                                        className="mt-1 text-xs text-destructive"
                                    >
                                        {methodError}
                                    </p>
                                ) : null}
                            </div>

                            {allocation.method === 'cash' ? (
                                <div>
                                    <label
                                        htmlFor={`payment-tendered-${allocation.id}`}
                                        className="mb-1.5 block text-xs font-medium text-muted-foreground"
                                    >
                                        Dinheiro recebido
                                    </label>
                                    <MoneyInput
                                        id={`payment-tendered-${allocation.id}`}
                                        prefix="R$"
                                        value={formatMoney(
                                            allocation.tendered_cents,
                                            '',
                                        )}
                                        onValueChange={(cents) =>
                                            updateAllocation(allocation.id, {
                                                tendered_cents: cents,
                                            })
                                        }
                                    />
                                    <input
                                        type="hidden"
                                        name={`payment_allocations[${index}][tendered_cents]`}
                                        value={allocation.tendered_cents}
                                    />
                                    {tenderedError ? (
                                        <p className="mt-1 text-xs text-destructive">
                                            {tenderedError}
                                        </p>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>

                        {allocation.method === 'cash' ? (
                            <p className="text-xs text-muted-foreground">
                                {allocation.tendered_cents >=
                                allocation.amount_cents
                                    ? `Troco calculado: ${formatMoney(Math.max(0, allocation.tendered_cents - allocation.amount_cents))}. Entra na gaveta apenas ${formatMoney(allocation.amount_cents)}.`
                                    : 'O valor recebido precisa ser igual ou maior que o valor aplicado.'}
                            </p>
                        ) : null}
                    </div>
                );
            })}

            <Button
                type="button"
                variant="outline"
                size="sm"
                className="w-full"
                onClick={addAllocation}
                disabled={remainingCents <= 0}
            >
                <Plus className="size-4" aria-hidden="true" />
                Adicionar outro método
            </Button>

            <div className="grid gap-2 rounded-lg bg-muted/40 p-3 text-xs sm:grid-cols-3">
                <div>
                    <span className="text-muted-foreground">Aplicado</span>
                    <p className="mt-0.5 font-semibold tabular-nums">
                        {formatMoney(allocatedCents)}
                    </p>
                </div>
                <div>
                    <span className="text-muted-foreground">
                        {overageCents > 0 ? 'Excesso' : 'Falta alocar'}
                    </span>
                    <p
                        className={`mt-0.5 font-semibold tabular-nums ${remainingCents === 0 && overageCents === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}
                    >
                        {formatMoney(
                            overageCents > 0
                                ? overageCents
                                : Math.max(0, remainingCents),
                        )}
                    </p>
                </div>
                {cashAllocations.length > 0 ? (
                    <div>
                        <span className="text-muted-foreground">Troco</span>
                        <p className="mt-0.5 font-semibold tabular-nums">
                            {formatMoney(changeCents)}
                        </p>
                    </div>
                ) : null}
            </div>

            {cashAllocations.length > 0 ? (
                hasCashWithoutShift ? (
                    <div className="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                        <div>
                            <p className="font-semibold">
                                Abra um turno para registrar dinheiro
                            </p>
                            <p className="mt-1 text-xs opacity-90">
                                O pagamento em espécie só pode entrar na gaveta
                                do turno aberto pelo operador atual.
                            </p>
                        </div>
                        {onRequestOpenCashShift ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onRequestOpenCashShift}
                                className="shrink-0 border-amber-400 bg-transparent text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:text-amber-100 dark:hover:bg-amber-900/40"
                            >
                                Abrir turno e continuar
                            </Button>
                        ) : null}
                    </div>
                ) : (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 text-xs text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/20 dark:text-emerald-300">
                        <p className="font-semibold">
                            Turno aberto:{' '}
                            {activeCashShift?.opened_by?.name ??
                                'operador atual'}
                        </p>
                        <p className="mt-1">
                            Recebido {formatMoney(cashTenderedCents)} · troco{' '}
                            {formatMoney(changeCents)} · entrada líquida na
                            gaveta {formatMoney(cashAppliedCents)}.
                        </p>
                    </div>
                )
            ) : null}

            {allocationError ? (
                <p className="text-xs font-medium text-destructive">
                    {allocationError}
                </p>
            ) : null}
            {overageCents > 0 ? (
                <p className="text-xs font-medium text-destructive">
                    Reduza os pagamentos em {formatMoney(overageCents)} para
                    fechar no total da comanda.
                </p>
            ) : null}
        </section>
    );
}

export type { PaymentAllocation };

type CashShiftQuickOpenDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function CashShiftQuickOpenDialog({
    open,
    onOpenChange,
}: CashShiftQuickOpenDialogProps) {
    const [initialAmountCents, setInitialAmountCents] = useState(0);
    const [initialAmountFloat, setInitialAmountFloat] = useState('0,00');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Abrir turno e continuar</DialogTitle>
                    <DialogDescription>
                        Informe o fundo inicial. Depois de abrir, você volta
                        para concluir esta comanda.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...cashShifts.store.form({
                        query: { return_to: 'sales' },
                    })}
                    headers={{
                        'X-Idempotency-Key': createIdempotencyKey(
                            'cash-shift-open-from-sale',
                        ),
                    }}
                    onSuccess={() => {
                        onOpenChange(false);
                    }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormErrorSummary errors={errors} />
                            <FormField
                                label="Fundo inicial de troco"
                                name="initial_amount_cents"
                                required
                                error={errors.initial_amount_cents}
                                description="O valor que já estará na gaveta no início do turno."
                            >
                                <MoneyInput
                                    id="sale_cash_shift_initial_amount"
                                    prefix="R$"
                                    value={initialAmountFloat}
                                    onValueChange={(cents, formatted) => {
                                        setInitialAmountCents(cents);
                                        setInitialAmountFloat(formatted);
                                    }}
                                    autoFocus
                                    required
                                />
                                <input
                                    type="hidden"
                                    name="initial_amount_cents"
                                    value={initialAmountCents}
                                />
                            </FormField>
                            <FormActions
                                processing={processing}
                                submitLabel="Abrir turno e voltar"
                                onCancel={() => onOpenChange(false)}
                            />
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
