import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    Clock,
    Layers,
    Plus,
    Receipt,
    Search,
    User,
    WalletCards,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    Pagination,
    ResourceHeader,
} from '@/components/operational';
import type { Paginated, ResourceFilters } from '@/components/operational';
import { QuickCreateCustomerModal } from '@/components/operational/quick-create-dialogs';
import type { CreatedEntity } from '@/components/operational/quick-create-dialogs';
import { CustomerPicker } from '@/components/customer-picker';
import { RemoteOptionPicker } from '@/components/remote-option-picker';
import {
    CashShiftQuickOpenDialog,
    PaymentAllocationFields,
} from '@/components/payment-allocation-fields';
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
import {
    ResourceViewToggle,
    useResourceView,
} from '@/components/resource-view-toggle';
import { cn } from '@/lib/utils';
import closingSessions from '@/routes/closing-sessions';
import sales from '@/routes/sales';
import type {
    CustomerOption,
    Sale,
    SaleCategoryOption,
    SaleMetrics,
    SaleStatus,
    SharedPageProps,
    CashShift,
} from '@/types';

type Props = {
    sales: Paginated<Sale>;
    categories: SaleCategoryOption[];
    customers: CustomerOption[];
    metrics: SaleMetrics;
    filters: ResourceFilters & {
        status?: string;
        customer_id?: string;
        sale_category_id?: string;
    };
    active_cash_shift?: CashShift | null;
};

const statusConfig: Record<
    SaleStatus,
    { label: string; bgClass: string; dotClass: string }
> = {
    open: {
        label: 'Aberta',
        bgClass: 'border-info/30 bg-info/15 text-info',
        dotClass: 'bg-info',
    },
    ready_to_bill: {
        label: 'Pronta p/ Fechar',
        bgClass: 'border-warning/30 bg-warning/15 text-warning',
        dotClass: 'bg-warning',
    },
    finalized: {
        label: 'Finalizada',
        bgClass: 'border-success/30 bg-success/15 text-success',
        dotClass: 'bg-success',
    },
    cancelled: {
        label: 'Cancelada',
        bgClass: 'border-border bg-muted text-muted-foreground',
        dotClass: 'bg-muted-foreground',
    },
    adjusted: {
        label: 'Estornada',
        bgClass: 'border-destructive/30 bg-destructive/15 text-destructive',
        dotClass: 'bg-destructive',
    },
    draft: {
        label: 'Rascunho',
        bgClass: 'border-border bg-muted text-muted-foreground',
        dotClass: 'bg-muted-foreground',
    },
};

export function SaleStatusBadge({ status }: { status: SaleStatus }) {
    const config = statusConfig[status] ?? statusConfig.draft;

    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-full px-2.5 py-0.5 text-3xs font-semibold',
                config.bgClass,
            )}
        >
            <span
                aria-hidden="true"
                className={cn('mr-1.5 size-1.5 rounded-full', config.dotClass)}
            />
            {config.label}
        </Badge>
    );
}

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

export default function SalesIndex({
    sales: paginator,
    categories,
    customers: initialCustomers,
    metrics,
    filters,
    active_cash_shift,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [closeOpen, setCloseOpen] = useState(false);
    const [openCashShiftDialog, setOpenCashShiftDialog] = useState(false);
    const [paymentAllocationValid, setPaymentAllocationValid] = useState(true);
    const [createKey] = useState(() => createIdempotencyKey('sale-open'));
    const [closeKey, setCloseKey] = useState(() =>
        createIdempotencyKey('closing-session'),
    );
    const [selectedCategory, setSelectedCategory] = useState<string>('');
    const [filterSaleCategory, setFilterSaleCategory] = useState(
        filters.sale_category_id ?? '',
    );
    const [selectedIds, setSelectedIds] = useState<string[]>([]);
    const { view, setView } = useResourceView('caldas-gestao:sales:view');
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('sale.manage');
    const canClosePermission =
        props.auth.permissions.includes('sale.close') ||
        props.auth.permissions.includes('sale.manage');

    const [customerList, setCustomerList] =
        useState<CustomerOption[]>(initialCustomers);
    const [selectedCustomer, setSelectedCustomer] = useState<string>('');
    const [quickCustomerOpen, setQuickCustomerOpen] = useState(false);

    const handleCustomerCreated = (created: CreatedEntity) => {
        const newCust: CustomerOption = {
            id: created.id,
            name: created.name,
            phone: created.phone ?? null,
        };
        setCustomerList((prev) => [
            ...prev.filter((c) => c.id !== created.id),
            newCust,
        ]);
        setSelectedCustomer(created.id);
    };

    const selectedCategoryObj = categories.find(
        (c) => c.id === selectedCategory,
    );

    const toggleSelect = (id: string) => {
        setSelectedIds((prev) =>
            prev.includes(id)
                ? prev.filter((item) => item !== id)
                : [...prev, id],
        );
    };

    const selectedSales = paginator.data.filter((s) =>
        selectedIds.includes(s.id),
    );
    const selectedTotal = selectedSales.reduce(
        (sum, s) => sum + (s.final_amount_cents || 0),
        0,
    );
    const uniqueCustomers = Array.from(
        new Set(
            selectedSales
                .map((s) => s.customer?.name)
                .filter((name): name is string => Boolean(name)),
        ),
    );

    const isSameCustomer =
        selectedSales.length > 0 &&
        selectedSales.every(
            (s) =>
                s.customer_id && s.customer_id === selectedSales[0].customer_id,
        );

    const isSameReference =
        selectedSales.length > 0 &&
        selectedSales.every(
            (s) =>
                !s.customer_id &&
                s.reference_label &&
                s.reference_label.trim() ===
                    selectedSales[0].reference_label?.trim(),
        );

    const isSingleAnonymous =
        selectedSales.length === 1 &&
        !selectedSales[0].customer_id &&
        !selectedSales[0].reference_label;

    const canConsolidateSubject =
        isSameCustomer || isSameReference || isSingleAnonymous;
    const allActive =
        selectedSales.length > 0 &&
        selectedSales.every(
            (s) =>
                s.status === 'open' ||
                s.status === 'ready_to_bill' ||
                s.status === 'draft',
        );
    const canClose = canClosePermission && allActive && canConsolidateSubject;

    return (
        <>
            <Head title="Comandas" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Operação & Checkout"
                    title="Comandas"
                    description="Controle o atendimento, consumo e faturamento de clientes e mesas em tempo real."
                    action={
                        canManage ? (
                            <Dialog
                                open={createOpen}
                                onOpenChange={setCreateOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button className="w-full sm:w-auto">
                                        <Plus aria-hidden="true" />
                                        Nova comanda
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Abrir nova comanda
                                        </DialogTitle>
                                        <DialogDescription>
                                            Inicie um atendimento ou consumo
                                            selecionando a categoria e o cliente
                                            ou referência.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...sales.store.form()}
                                        headers={{
                                            'X-Idempotency-Key': createKey,
                                        }}
                                        resetOnSuccess
                                        onSuccess={() => setCreateOpen(false)}
                                        className="space-y-5"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <FormErrorSummary
                                                    errors={errors}
                                                />
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Categoria de comanda"
                                                            name="sale_category_id"
                                                            error={
                                                                errors.sale_category_id
                                                            }
                                                        >
                                                            <RemoteOptionPicker
                                                                id="sale_category_id"
                                                                name="sale_category_id"
                                                                options={
                                                                    categories
                                                                }
                                                                placeholder="Selecione a categoria"
                                                                resource="sale-categories"
                                                                value={
                                                                    selectedCategory
                                                                }
                                                                onChange={(
                                                                    value,
                                                                ) =>
                                                                    setSelectedCategory(
                                                                        value,
                                                                    )
                                                                }
                                                                required
                                                            />
                                                        </FormField>
                                                        {selectedCategoryObj ? (
                                                            <p className="mt-1.5 text-xs text-muted-foreground">
                                                                Tipo:{' '}
                                                                <strong className="text-foreground">
                                                                    {selectedCategoryObj.type ===
                                                                    'service'
                                                                        ? 'Apenas Serviços'
                                                                        : selectedCategoryObj.type ===
                                                                            'product'
                                                                          ? 'Apenas Produtos'
                                                                          : 'Misto'}
                                                                </strong>{' '}
                                                                • Regra:{' '}
                                                                <span className="text-muted-foreground">
                                                                    {selectedCategoryObj.uniqueness_scope ===
                                                                    'customer'
                                                                        ? '1 por cliente'
                                                                        : selectedCategoryObj.uniqueness_scope ===
                                                                            'reference'
                                                                          ? '1 por mesa/referência'
                                                                          : selectedCategoryObj.uniqueness_scope ===
                                                                              'appointment'
                                                                            ? '1 por agendamento'
                                                                            : 'Livre (múltiplas)'}
                                                                </span>
                                                            </p>
                                                        ) : null}
                                                    </div>

                                                    <div className="sm:col-span-2">
                                                        <CustomerPicker
                                                            label="Cliente"
                                                            name="customer_id"
                                                            id="customer_id"
                                                            value={
                                                                selectedCustomer
                                                            }
                                                            options={
                                                                customerList
                                                            }
                                                            onChange={
                                                                setSelectedCustomer
                                                            }
                                                            error={
                                                                errors.customer_id
                                                            }
                                                            helper="Opcional — cliente avulso / não identificado"
                                                            allowAnonymous
                                                            action={
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        setQuickCustomerOpen(
                                                                            true,
                                                                        )
                                                                    }
                                                                    className="font-medium text-primary hover:underline"
                                                                >
                                                                    + Novo
                                                                    Cliente
                                                                </button>
                                                            }
                                                        />
                                                    </div>

                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Identificador / Mesa / Referência (opcional)"
                                                            name="reference_label"
                                                            error={
                                                                errors.reference_label
                                                            }
                                                        >
                                                            <Input
                                                                id="reference_label"
                                                                name="reference_label"
                                                                placeholder="Ex.: Mesa 04, Cartão 12, Balcão, Pedido 33"
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Observações da comanda"
                                                            name="notes"
                                                            error={errors.notes}
                                                        >
                                                            <textarea
                                                                id="notes"
                                                                name="notes"
                                                                rows={2}
                                                                className="min-h-20 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                                placeholder="Informações adicionais para este atendimento..."
                                                            />
                                                        </FormField>
                                                    </div>
                                                </div>

                                                <FormActions
                                                    processing={processing}
                                                    onCancel={() =>
                                                        setCreateOpen(false)
                                                    }
                                                    label="Abrir comanda"
                                                />
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        ) : null
                    }
                />

                {/* Metrics Cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="surface-panel flex items-center gap-4 p-4 sm:p-5">
                        <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-info/10 text-info">
                            <Clock className="size-6" aria-hidden="true" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Comandas Abertas
                            </p>
                            <p className="font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                {metrics.open_count}
                            </p>
                        </div>
                    </div>

                    <div className="surface-panel flex items-center gap-4 p-4 sm:p-5">
                        <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-warning/10 text-warning">
                            <AlertCircle
                                className="size-6"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Prontas p/ Fechar
                            </p>
                            <p className="font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                {metrics.ready_count}
                            </p>
                        </div>
                    </div>

                    <div className="surface-panel flex items-center gap-4 p-4 sm:p-5">
                        <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-success/10 text-success">
                            <WalletCards
                                className="size-6"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Faturamento Aberto (Hoje)
                            </p>
                            <p className="font-display text-2xl font-bold tracking-tight text-success sm:text-3xl">
                                {formatMoney(metrics.today_total_cents)}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Toolbar & Filters */}
                <div className="surface-panel space-y-4 p-4">
                    <form
                        action={sales.index.url()}
                        method="get"
                        className="grid gap-3 sm:grid-cols-12"
                    >
                        <div className="relative min-w-0 sm:col-span-4">
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                aria-label="Buscar comanda"
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Buscar por cliente, mesa ou referência..."
                                className="h-10 rounded-lg pl-9 text-sm"
                            />
                        </div>

                        <div className="sm:col-span-3">
                            <select
                                aria-label="Filtrar por status"
                                name="status"
                                defaultValue={filters.status ?? ''}
                                className="h-10 w-full rounded-lg border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/50"
                            >
                                <option value="">Todos os status</option>
                                <option value="open">Abertas</option>
                                <option value="ready_to_bill">
                                    Prontas para Fechamento
                                </option>
                                <option value="finalized">Finalizadas</option>
                                <option value="cancelled">Canceladas</option>
                                <option value="draft">Rascunhos</option>
                            </select>
                        </div>

                        <div className="sm:col-span-3">
                            <RemoteOptionPicker
                                id="sale_category_filter"
                                label="Categoria"
                                name="sale_category_id"
                                options={categories.filter(
                                    (category) =>
                                        category.id === filterSaleCategory,
                                )}
                                selectedOption={categories.find(
                                    (category) =>
                                        category.id === filterSaleCategory,
                                )}
                                placeholder="Todas as categorias"
                                resource="sale-categories"
                                value={filterSaleCategory}
                                onChange={(value) =>
                                    setFilterSaleCategory(value)
                                }
                            />
                        </div>

                        <div className="flex items-center gap-2 sm:col-span-2 sm:justify-end">
                            <Button
                                type="submit"
                                variant="secondary"
                                className="h-10 w-full sm:w-auto"
                            >
                                Filtrar
                            </Button>
                            <ResourceViewToggle
                                value={view}
                                onChange={setView}
                            />
                            {filters.search ||
                            filters.status ||
                            filters.sale_category_id ||
                            filters.customer_id ? (
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="h-10 px-2.5"
                                >
                                    <Link
                                        href={sales.index.url()}
                                        title="Limpar filtros"
                                    >
                                        <X className="size-4" />
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </form>

                    <div className="flex items-center justify-between border-t border-border pt-3 text-xs text-muted-foreground">
                        <span>
                            {paginator.total}{' '}
                            {paginator.total === 1
                                ? 'comanda encontrada'
                                : 'comandas encontradas'}
                        </span>
                        {selectedIds.length > 0 ? (
                            <span className="font-semibold text-primary">
                                {selectedIds.length} selecionada(s)
                            </span>
                        ) : null}
                    </div>
                </div>

                {/* Floating/Sticky Batch Bar for multi-selection */}
                {selectedIds.length > 0 ? (
                    <div className="sticky top-4 z-20 flex flex-col items-start justify-between gap-3 rounded-2xl border border-primary/40 bg-primary/10 p-4 shadow-lg backdrop-blur-md sm:flex-row sm:items-center">
                        <div className="flex items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground">
                                {selectedIds.length}
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-foreground">
                                    {selectedIds.length === 1
                                        ? '1 comanda selecionada'
                                        : `${selectedIds.length} comandas selecionadas`}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {uniqueCustomers.length === 1
                                        ? `Cliente: ${uniqueCustomers[0]} • Total acumulado: ${formatMoney(selectedTotal)}`
                                        : `Total acumulado: ${formatMoney(selectedTotal)}`}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setSelectedIds([])}
                            >
                                Desmarcar todas
                            </Button>

                            {canClose ? (
                                <Button
                                    size="sm"
                                    className="gap-1.5 bg-success font-semibold text-success-foreground hover:bg-success/90"
                                    onClick={() => {
                                        setCloseKey(
                                            createIdempotencyKey(
                                                'closing-session',
                                            ),
                                        );
                                        setCloseOpen(true);
                                    }}
                                >
                                    <Receipt className="size-4" />
                                    Fechar selecionadas (
                                    {formatMoney(selectedTotal)})
                                </Button>
                            ) : selectedIds.length > 1 &&
                              !canConsolidateSubject ? (
                                <span className="text-3xs font-medium text-warning">
                                    Clientes ou referências diferentes não podem
                                    ser consolidados juntos
                                </span>
                            ) : null}
                        </div>
                    </div>
                ) : null}

                {/* Dialog Fechamento Consolidado */}
                <Dialog open={closeOpen} onOpenChange={setCloseOpen}>
                    <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Fechamento Consolidado</DialogTitle>
                            <DialogDescription>
                                Revise as comandas selecionadas antes de
                                encerrar o atendimento e emitir o recibo
                                operacional interno.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            {...closingSessions.store.form()}
                            headers={{
                                'X-Idempotency-Key': closeKey,
                            }}
                            onSubmit={(event) => {
                                if (
                                    selectedTotal > 0 &&
                                    !paymentAllocationValid
                                ) {
                                    event.preventDefault();
                                }
                            }}
                            onSuccess={() => {
                                setCloseOpen(false);
                                setSelectedIds([]);
                            }}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />

                                    {selectedSales.map((s) => (
                                        <input
                                            key={s.id}
                                            type="hidden"
                                            name="sale_ids[]"
                                            value={s.id}
                                        />
                                    ))}
                                    <input
                                        type="hidden"
                                        name="expected_total_cents"
                                        value={selectedTotal}
                                    />

                                    {/* Breakdown list */}
                                    <div className="max-h-56 divide-y divide-border/60 overflow-y-auto rounded-xl border border-border bg-muted/20 p-1">
                                        {selectedSales.map((s) => (
                                            <div
                                                key={s.id}
                                                className="flex items-center justify-between p-2.5 text-xs"
                                            >
                                                <div>
                                                    <p className="font-semibold text-foreground">
                                                        {s.reference_label ||
                                                            s.customer?.name ||
                                                            `Comanda #${s.id.slice(0, 6)}`}
                                                    </p>
                                                    <p className="text-3xs text-muted-foreground">
                                                        {s.category_name_snapshot ||
                                                            s.category?.name ||
                                                            'Geral'}{' '}
                                                        • {s.items?.length ?? 0}{' '}
                                                        item(ns)
                                                    </p>
                                                </div>
                                                <span className="font-semibold text-foreground">
                                                    {formatMoney(
                                                        s.final_amount_cents,
                                                    )}
                                                </span>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Totals Summary */}
                                    <div className="space-y-2 rounded-xl border border-border bg-muted/40 p-4">
                                        <div className="flex justify-between text-xs text-muted-foreground">
                                            <span>Quantidade de comandas:</span>
                                            <span className="font-medium text-foreground">
                                                {selectedSales.length}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between border-t border-border pt-2">
                                            <span className="text-sm font-semibold text-foreground">
                                                Total Consolidado:
                                            </span>
                                            <span className="font-display text-xl font-bold text-foreground">
                                                {formatMoney(selectedTotal)}
                                            </span>
                                        </div>
                                    </div>

                                    {selectedTotal > 0 ? (
                                        <PaymentAllocationFields
                                            key={closeKey}
                                            totalCents={selectedTotal}
                                            activeCashShift={active_cash_shift}
                                            errors={errors}
                                            onValidityChange={
                                                setPaymentAllocationValid
                                            }
                                            onRequestOpenCashShift={() =>
                                                setOpenCashShiftDialog(true)
                                            }
                                        />
                                    ) : (
                                        <p className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                                            As comandas selecionadas foram
                                            totalmente cobertas por pacotes. O
                                            fechamento não gera cobrança.
                                        </p>
                                    )}

                                    <FormField
                                        label="Observações do fechamento (opcional)"
                                        name="notes"
                                        error={errors.notes}
                                    >
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows={2}
                                            className="min-h-16 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            placeholder="Anotações para constar no recibo interno..."
                                        />
                                    </FormField>

                                    <FormActions
                                        submitLabel="Confirmar Fechamento e Emitir Recibo"
                                        processing={processing}
                                        onCancel={() => setCloseOpen(false)}
                                    />
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>

                <CashShiftQuickOpenDialog
                    open={openCashShiftDialog}
                    onOpenChange={setOpenCashShiftDialog}
                />

                {/* Sales List / Grid */}
                {paginator.data.length === 0 ? (
                    <EmptyState
                        title={
                            filters.search ||
                            filters.status ||
                            filters.sale_category_id
                                ? 'Nenhuma comanda encontrada'
                                : 'Nenhuma comanda aberta nesta unidade'
                        }
                        description={
                            filters.search ||
                            filters.status ||
                            filters.sale_category_id
                                ? 'Tente ajustar os filtros de busca para encontrar as comandas desejadas.'
                                : 'Abra uma comanda para registrar consumos de clientes, mesas ou atendimentos da agenda.'
                        }
                        action={
                            !filters.search && canManage ? (
                                <Button onClick={() => setCreateOpen(true)}>
                                    <Plus aria-hidden="true" />
                                    Abrir primeira comanda
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <section
                        aria-label="Lista de comandas"
                        className={
                            view === 'cards'
                                ? 'grid gap-3 sm:grid-cols-2 lg:grid-cols-3'
                                : 'grid gap-3 md:gap-0 md:divide-y md:overflow-hidden md:rounded-xl md:border md:border-border'
                        }
                    >
                        {paginator.data.map((sale) => {
                            const isSelected = selectedIds.includes(sale.id);

                            return (
                                <article
                                    key={sale.id}
                                    className={`surface-panel relative flex flex-col justify-between gap-4 p-5 transition-all hover:border-primary/50 ${
                                        isSelected
                                            ? 'border-primary bg-primary/[0.03]'
                                            : ''
                                    } ${view === 'list' ? 'max-md:min-h-0 md:min-h-0 md:flex-row md:items-center md:gap-4 md:rounded-none md:border-0 md:border-b md:p-4 md:last:border-b-0' : ''}`}
                                >
                                    {/* Top Row: Select Checkbox, Reference / Identifier, Status */}
                                    <div
                                        className={
                                            view === 'list'
                                                ? 'flex items-start justify-between gap-3 md:w-1/3'
                                                : 'flex items-start justify-between gap-3'
                                        }
                                    >
                                        <div className="flex min-w-0 items-center gap-2.5">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onChange={() =>
                                                    toggleSelect(sale.id)
                                                }
                                                className="size-4 shrink-0 rounded border-input text-primary accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                                                aria-label={`Selecionar comanda ${sale.reference_label ?? sale.id}`}
                                            />
                                            <div className="min-w-0">
                                                <h2 className="truncate text-base font-semibold text-foreground">
                                                    {sale.reference_label ||
                                                        sale.customer?.name ||
                                                        `Comanda #${sale.id.slice(0, 8)}`}
                                                </h2>
                                                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <span className="truncate">
                                                        {sale.category_name_snapshot ||
                                                            sale.category
                                                                ?.name ||
                                                            'Geral'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <SaleStatusBadge status={sale.status} />
                                    </div>

                                    {/* Customer & Appointment Info */}
                                    <div
                                        className={
                                            view === 'list'
                                                ? 'flex flex-1 flex-col gap-1.5 border-y border-border py-2.5 text-xs text-muted-foreground md:border-y-0 md:py-0'
                                                : 'flex flex-col gap-1.5 border-y border-border py-2.5 text-xs text-muted-foreground'
                                        }
                                    >
                                        <div className="flex items-center gap-2">
                                            <User className="size-3.5 shrink-0 text-muted-foreground" />
                                            <span className="truncate font-medium text-foreground">
                                                {sale.customer?.name ??
                                                    'Cliente Avulso'}
                                            </span>
                                        </div>

                                        {sale.appointment_link?.appointment ? (
                                            <div className="flex items-center gap-2 text-primary">
                                                <Calendar className="size-3.5 shrink-0" />
                                                <span className="truncate">
                                                    Agendamento vinculado (
                                                    {formatDateTime(
                                                        sale.appointment_link
                                                            .appointment
                                                            .starts_at,
                                                    )}
                                                    )
                                                </span>
                                            </div>
                                        ) : null}

                                        <div className="flex items-center justify-between pt-1">
                                            <span className="inline-flex items-center gap-1">
                                                <Layers className="size-3 shrink-0" />
                                                {sale.items?.length ?? 0}{' '}
                                                {(sale.items?.length ?? 0) === 1
                                                    ? 'item'
                                                    : 'itens'}
                                            </span>
                                            <span>
                                                Aberta às{' '}
                                                {formatDateTime(
                                                    sale.created_at,
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Bottom Row: Total & Action */}
                                    <div
                                        className={
                                            view === 'list'
                                                ? 'flex items-center justify-between gap-2 pt-1 md:border-l md:border-border md:pt-0 md:pl-4'
                                                : 'flex items-center justify-between gap-2 pt-1'
                                        }
                                    >
                                        <div>
                                            <span className="block text-2xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                Total a Pagar
                                            </span>
                                            <span className="font-display text-lg font-bold text-foreground">
                                                {formatMoney(
                                                    sale.final_amount_cents,
                                                )}
                                            </span>
                                        </div>

                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={sales.show(sale.id)}>
                                                Ver comanda
                                            </Link>
                                        </Button>
                                    </div>
                                </article>
                            );
                        })}
                    </section>
                )}

                <Pagination links={paginator.links} />

                <QuickCreateCustomerModal
                    open={quickCustomerOpen}
                    onOpenChange={setQuickCustomerOpen}
                    onSuccess={handleCustomerCreated}
                />
            </PageCanvas>
        </>
    );
}

SalesIndex.layout = {
    breadcrumbs: [{ title: 'Comandas', href: sales.index() }],
};
