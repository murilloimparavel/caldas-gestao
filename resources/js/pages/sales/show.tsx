import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    CheckCircle2,
    History,
    Package,
    Percent,
    Plus,
    Printer,
    Receipt,
    RotateCcw,
    Scissors,
    Sparkles,
    Trash2,
    Undo2,
    User,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    parseBrazilianCurrency,
} from '@/components/operational';
import {
    QuickCreateProductModal,
    QuickCreateServiceModal,
} from '@/components/operational/quick-create-dialogs';
import type { CreatedEntity } from '@/components/operational/quick-create-dialogs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CatalogItemPicker } from '@/components/catalog-item-picker';
import {
    CashShiftQuickOpenDialog,
    PaymentAllocationFields,
} from '@/components/payment-allocation-fields';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { SaleStatusBadge } from '@/pages/sales/index';
import calendar from '@/routes/calendar';
import closingSessions from '@/routes/closing-sessions';
import sales from '@/routes/sales';
import type {
    ProductOption,
    ProfessionalOption,
    Sale,
    SaleCategoryOption,
    SaleItem,
    ServiceOption,
    SharedPageProps,
    CashShift,
} from '@/types';

type Props = {
    sale: Sale;
    services: ServiceOption[];
    products: ProductOption[];
    professionals: ProfessionalOption[];
    categories: SaleCategoryOption[];
    active_cash_shift?: CashShift | null;
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

function ItemTypeBadge({ type }: { type: 'service' | 'product' | 'custom' }) {
    switch (type) {
        case 'service':
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300"
                >
                    <Scissors className="size-3" /> Serviço
                </Badge>
            );
        case 'product':
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-950/40 dark:text-purple-300"
                >
                    <Package className="size-3" /> Produto
                </Badge>
            );
        case 'custom':
        default:
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-300"
                >
                    <Sparkles className="size-3" /> Personalizado
                </Badge>
            );
    }
}

export default function SalesShow({
    sale,
    services: initialServices,
    products: initialProducts,
    professionals,
    active_cash_shift,
}: Props) {
    const { props } = usePage<SharedPageProps>();
    const permissions = new Set(props.auth.permissions);
    const canManage = permissions.has('sale.manage');
    const canAdjust =
        permissions.has('sale.adjust') || permissions.has('sale.manage');
    const canClosePermission =
        permissions.has('sale.close') || permissions.has('sale.manage');
    const canDiscount = permissions.has('sale.discount');

    const [addItemOpen, setAddItemOpen] = useState(false);
    const [discountOpen, setDiscountOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [closeOpen, setCloseOpen] = useState(false);
    const [openCashShiftDialog, setOpenCashShiftDialog] = useState(false);
    const [paymentAllocationValid, setPaymentAllocationValid] = useState(true);
    const [adjustOpen, setAdjustOpen] = useState(false);

    // Dynamic lists for quick-created items
    const [services, setServices] = useState<ServiceOption[]>(initialServices);
    const [products, setProducts] = useState<ProductOption[]>(initialProducts);

    const [quickServiceOpen, setQuickServiceOpen] = useState(false);
    const [quickProductOpen, setQuickProductOpen] = useState(false);

    // Add item form state
    const [itemType, setItemType] = useState<'service' | 'product' | 'custom'>(
        sale.category?.type === 'product' ? 'product' : 'service',
    );
    const [selectedServiceId, setSelectedServiceId] = useState('');
    const [selectedProductId, setSelectedProductId] = useState('');
    const [sellerProfessionalId, setSellerProfessionalId] = useState('');
    const [customName, setCustomName] = useState('');
    const [customPriceStr, setCustomPriceStr] = useState('');
    const [quantity, setQuantity] = useState(1);
    const [itemDiscountStr, setItemDiscountStr] = useState('');

    const handleServiceCreated = (created: CreatedEntity) => {
        const newOpt: ServiceOption = {
            id: created.id,
            name: created.name,
            price_cents: created.price_cents ?? 0,
            duration_minutes: created.duration_minutes ?? 30,
        };
        setServices((prev) => [
            ...prev.filter((s) => s.id !== created.id),
            newOpt,
        ]);
        setSelectedServiceId(created.id);
    };

    const handleProductCreated = (created: CreatedEntity) => {
        const newOpt: ProductOption = {
            id: created.id,
            name: created.name,
            price_cents: created.price_cents ?? 0,
            current_stock: created.current_stock ?? 0,
        };
        setProducts((prev) => [
            ...prev.filter((p) => p.id !== created.id),
            newOpt,
        ]);
        setSelectedProductId(created.id);
    };

    // General discount state
    const [generalDiscountStr, setGeneralDiscountStr] = useState(
        sale.discount_amount_cents > 0
            ? (sale.discount_amount_cents / 100).toFixed(2).replace('.', ',')
            : '',
    );
    const [discountNotes, setDiscountNotes] = useState(sale.notes ?? '');

    // Cancel reason state
    const [cancelReason, setCancelReason] = useState('');

    // Adjust reason state
    const [adjustReason, setAdjustReason] = useState('');

    const [transitionKey, setTransitionKey] = useState(() =>
        createIdempotencyKey(`sale-transition:${sale.id}`),
    );
    const [closeKey] = useState(() =>
        createIdempotencyKey(`sale-close:${sale.id}`),
    );
    const [adjustKey] = useState(() =>
        createIdempotencyKey(`sale-adjust:${sale.id}`),
    );

    const isSaleActive =
        sale.status === 'open' || sale.status === 'ready_to_bill';
    const isSaleOpen = sale.status === 'open';
    const isSaleClosed =
        sale.status === 'finalized' || (sale.status as string) === 'closed';

    const selectedService = services.find((s) => s.id === selectedServiceId);
    const selectedProduct = products.find((p) => p.id === selectedProductId);

    const calculatedUnitPriceCents =
        itemType === 'service'
            ? (selectedService?.price_cents ?? 0)
            : itemType === 'product'
              ? (selectedProduct?.price_cents ?? 0)
              : parseBrazilianCurrency(customPriceStr);

    const calculatedItemDiscountCents = parseBrazilianCurrency(itemDiscountStr);
    const calculatedItemSubtotalCents = Math.max(
        0,
        calculatedUnitPriceCents * quantity - calculatedItemDiscountCents,
    );

    const handleServiceChange = (serviceId: string) => {
        setSelectedServiceId(serviceId);
    };

    const handleProductChange = (productId: string) => {
        setSelectedProductId(productId);
    };

    const resetItemForm = () => {
        setSelectedServiceId('');
        setSelectedProductId('');
        setSellerProfessionalId('');
        setCustomName('');
        setCustomPriceStr('');
        setQuantity(1);
        setItemDiscountStr('');
    };

    const latestAdjustment = (sale.status_histories ?? [])
        .slice()
        .reverse()
        .find((h) => h.to_status === 'adjusted');

    return (
        <>
            <Head
                title={`Comanda #${sale.reference_label || sale.id.slice(0, 8)}`}
            />
            <PageCanvas>
                {/* Back Link & Header */}
                <div className="flex flex-col gap-4">
                    <div className="no-print print:hidden">
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
                    </div>

                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h1 className="font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                                    {sale.reference_label
                                        ? `${sale.reference_label} — ${sale.customer?.name ?? 'Cliente Avulso'}`
                                        : (sale.customer?.name ??
                                          `Comanda #${sale.id.slice(0, 8)}`)}
                                </h1>
                                <SaleStatusBadge status={sale.status} />
                                <Badge
                                    variant="secondary"
                                    className="text-xs font-medium"
                                >
                                    {sale.category_name_snapshot ||
                                        sale.category?.name ||
                                        'Geral'}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground sm:text-sm">
                                Aberta em {formatDateTime(sale.created_at)} •
                                ID:{' '}
                                <span className="font-mono text-xs">
                                    {sale.id}
                                </span>
                            </p>
                        </div>

                        {/* Status Transition Action Buttons */}
                        {canManage && isSaleActive ? (
                            <div className="no-print flex flex-wrap items-center gap-2 print:hidden">
                                {isSaleOpen ? (
                                    <Form
                                        {...sales.transition.form(sale.id)}
                                        headers={{
                                            'X-Idempotency-Key': transitionKey,
                                        }}
                                        onSuccess={() =>
                                            setTransitionKey(
                                                createIdempotencyKey(
                                                    `sale-transition:${sale.id}`,
                                                ),
                                            )
                                        }
                                        className="inline-block"
                                    >
                                        <input
                                            type="hidden"
                                            name="status"
                                            value="ready_to_bill"
                                        />
                                        <input
                                            type="hidden"
                                            name="lock_version"
                                            value={sale.lock_version}
                                        />
                                        <Button
                                            type="submit"
                                            className="bg-amber-600 font-medium text-white hover:bg-amber-700 dark:bg-amber-600 dark:hover:bg-amber-700"
                                        >
                                            <CheckCircle2 className="size-4" />
                                            Pronto para Fechar
                                        </Button>
                                    </Form>
                                ) : (
                                    <Form
                                        {...sales.transition.form(sale.id)}
                                        headers={{
                                            'X-Idempotency-Key': transitionKey,
                                        }}
                                        onSuccess={() =>
                                            setTransitionKey(
                                                createIdempotencyKey(
                                                    `sale-transition:${sale.id}`,
                                                ),
                                            )
                                        }
                                        className="inline-block"
                                    >
                                        <input
                                            type="hidden"
                                            name="status"
                                            value="open"
                                        />
                                        <input
                                            type="hidden"
                                            name="reason"
                                            value="Reabertura de comanda para inclusão de itens"
                                        />
                                        <input
                                            type="hidden"
                                            name="lock_version"
                                            value={sale.lock_version}
                                        />
                                        <Button
                                            type="submit"
                                            variant="secondary"
                                        >
                                            <RotateCcw className="size-4" />
                                            Reabrir Comanda
                                        </Button>
                                    </Form>
                                )}

                                {canClosePermission ? (
                                    <Button
                                        onClick={() => setCloseOpen(true)}
                                        className="gap-1.5 bg-emerald-600 font-semibold text-white hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                                    >
                                        <Receipt className="size-4" />
                                        Fechar Comanda
                                    </Button>
                                ) : null}

                                <Dialog
                                    open={cancelOpen}
                                    onOpenChange={setCancelOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="text-destructive hover:bg-destructive/10"
                                        >
                                            <XCircle className="size-4" />
                                            Cancelar Comanda
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Cancelar comanda
                                            </DialogTitle>
                                            <DialogDescription>
                                                Informe a justificativa do
                                                cancelamento desta comanda.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            {...sales.transition.form(sale.id)}
                                            headers={{
                                                'X-Idempotency-Key':
                                                    createIdempotencyKey(
                                                        `sale-cancel:${sale.id}`,
                                                    ),
                                            }}
                                            onSuccess={() =>
                                                setCancelOpen(false)
                                            }
                                            className="space-y-4"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <FormErrorSummary
                                                        errors={errors}
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="status"
                                                        value="cancelled"
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="lock_version"
                                                        value={
                                                            sale.lock_version
                                                        }
                                                    />
                                                    <FormField
                                                        label="Motivo do cancelamento"
                                                        name="reason"
                                                        error={errors.reason}
                                                    >
                                                        <Input
                                                            id="reason"
                                                            name="reason"
                                                            value={cancelReason}
                                                            onChange={(e) =>
                                                                setCancelReason(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Ex.: Cliente desistiu, erro de lançamento, etc."
                                                            required
                                                        />
                                                    </FormField>
                                                    <FormActions
                                                        processing={processing}
                                                        onCancel={() =>
                                                            setCancelOpen(false)
                                                        }
                                                        label="Confirmar cancelamento"
                                                    />
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>

                                {/* Fechamento Consolidado Dialog */}
                                <Dialog
                                    open={closeOpen}
                                    onOpenChange={setCloseOpen}
                                >
                                    <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Fechar Comanda
                                            </DialogTitle>
                                            <DialogDescription>
                                                Confirme o encerramento desta
                                                comanda para emitir o recibo
                                                operacional interno.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            {...closingSessions.store.form()}
                                            headers={{
                                                'X-Idempotency-Key': closeKey,
                                            }}
                                            onSubmit={(event) => {
                                                if (!paymentAllocationValid) {
                                                    event.preventDefault();
                                                }
                                            }}
                                            onSuccess={() =>
                                                setCloseOpen(false)
                                            }
                                            className="space-y-4"
                                        >
                                            {({ errors, processing }) => (
                                                <>
                                                    <FormErrorSummary
                                                        errors={errors}
                                                    />

                                                    <input
                                                        type="hidden"
                                                        name="sale_ids[]"
                                                        value={sale.id}
                                                    />
                                                    <input
                                                        type="hidden"
                                                        name="expected_total_cents"
                                                        value={
                                                            sale.final_amount_cents
                                                        }
                                                    />

                                                    <div className="space-y-2 rounded-xl border border-border bg-muted/40 p-4">
                                                        <div className="flex justify-between text-xs text-muted-foreground">
                                                            <span>
                                                                Subtotal da
                                                                comanda:
                                                            </span>
                                                            <span>
                                                                {formatMoney(
                                                                    sale.total_amount_cents,
                                                                )}
                                                            </span>
                                                        </div>

                                                        {sale.discount_amount_cents >
                                                        0 ? (
                                                            <div className="flex justify-between text-xs text-emerald-600 dark:text-emerald-400">
                                                                <span>
                                                                    Desconto
                                                                    aplicado:
                                                                </span>
                                                                <span>
                                                                    -
                                                                    {formatMoney(
                                                                        sale.discount_amount_cents,
                                                                    )}
                                                                </span>
                                                            </div>
                                                        ) : null}
                                                        <div className="flex items-center justify-between border-t border-border pt-2">
                                                            <span className="text-sm font-semibold text-foreground">
                                                                Total a Fechar:
                                                            </span>
                                                            <span className="font-display text-xl font-bold text-foreground">
                                                                {formatMoney(
                                                                    sale.final_amount_cents,
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <PaymentAllocationFields
                                                        key={closeKey}
                                                        totalCents={
                                                            sale.final_amount_cents
                                                        }
                                                        activeCashShift={
                                                            active_cash_shift
                                                        }
                                                        errors={errors}
                                                        onValidityChange={
                                                            setPaymentAllocationValid
                                                        }
                                                        onRequestOpenCashShift={() =>
                                                            setOpenCashShiftDialog(
                                                                true,
                                                            )
                                                        }
                                                    />

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
                                                            placeholder="Anotações para constar no recibo..."
                                                        />
                                                    </FormField>

                                                    <FormActions
                                                        submitLabel="Confirmar Fechamento e Emitir Recibo"
                                                        processing={processing}
                                                        onCancel={() =>
                                                            setCloseOpen(false)
                                                        }
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
                            </div>
                        ) : isSaleClosed ? (
                            <div className="no-print flex flex-wrap items-center gap-2 print:hidden">
                                <Button
                                    type="button"
                                    onClick={() => window.print()}
                                    variant="outline"
                                    className="gap-2"
                                >
                                    <Printer className="size-4" />
                                    Imprimir Recibo
                                </Button>

                                {sale.closing_sessions &&
                                sale.closing_sessions.length > 0 ? (
                                    <Button
                                        asChild
                                        className="gap-2 bg-emerald-600 font-semibold text-white hover:bg-emerald-700"
                                    >
                                        <Link
                                            href={closingSessions.show(
                                                sale.closing_sessions[0].id,
                                            )}
                                        >
                                            <Receipt className="size-4" />
                                            Ver Recibo Interno
                                        </Link>
                                    </Button>
                                ) : null}

                                {canAdjust ? (
                                    <Dialog
                                        open={adjustOpen}
                                        onOpenChange={setAdjustOpen}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className="gap-1.5 border-rose-300 text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/50"
                                            >
                                                <Undo2 className="size-4" />
                                                Estornar Comanda
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-md">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Estornar Comanda
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Realize o estorno
                                                    compensatório desta comanda
                                                    já finalizada.
                                                </DialogDescription>
                                            </DialogHeader>

                                            <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                                                <div className="flex items-start gap-2.5">
                                                    <AlertCircle className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                                                    <div className="space-y-1">
                                                        <p className="font-semibold">
                                                            Atenção sobre o
                                                            estorno
                                                            compensatório
                                                        </p>
                                                        <p className="text-amber-800 dark:text-amber-300">
                                                            O estorno reverterá
                                                            automaticamente o
                                                            estoque dos produtos
                                                            vendidos (lançando
                                                            ajuste de ganho) e
                                                            cancelará as
                                                            comissões apuradas
                                                            dos profissionais. A
                                                            comanda permanecerá
                                                            no sistema como{' '}
                                                            <strong>
                                                                Estornada
                                                            </strong>
                                                            .
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <Form
                                                {...sales.adjust.form(sale.id)}
                                                headers={{
                                                    'X-Idempotency-Key':
                                                        adjustKey,
                                                }}
                                                onSuccess={() => {
                                                    setAdjustOpen(false);
                                                    setAdjustReason('');
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
                                                                sale.lock_version
                                                            }
                                                        />

                                                        <FormField
                                                            label="Motivo do Estorno (obrigatório)"
                                                            name="reason"
                                                            error={
                                                                errors.reason
                                                            }
                                                        >
                                                            <textarea
                                                                id="adjust_reason"
                                                                name="reason"
                                                                rows={3}
                                                                value={
                                                                    adjustReason
                                                                }
                                                                onChange={(e) =>
                                                                    setAdjustReason(
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder="Ex.: Desistência do cliente, erro no lançamento de itens, estorno solicitado pela gerência..."
                                                                required
                                                                className="min-h-20 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/50"
                                                            />
                                                        </FormField>

                                                        <FormActions
                                                            submitLabel="Confirmar Estorno da Comanda"
                                                            processing={
                                                                processing
                                                            }
                                                            onCancel={() =>
                                                                setAdjustOpen(
                                                                    false,
                                                                )
                                                            }
                                                        />
                                                    </>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                ) : null}
                            </div>
                        ) : null}
                    </div>

                    {/* Adjusted Sale Banner */}
                    {sale.status === 'adjusted' && (
                        <div className="rounded-xl border border-rose-200 bg-rose-50/80 p-4 text-rose-900 shadow-sm dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">
                            <div className="flex items-start gap-3">
                                <AlertCircle className="mt-0.5 size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                                <div className="space-y-1 text-sm">
                                    <p className="font-semibold text-rose-950 dark:text-rose-100">
                                        Esta comanda foi estornada
                                    </p>
                                    {latestAdjustment?.reason ? (
                                        <p className="text-xs text-rose-800 dark:text-rose-300">
                                            <span className="font-medium">
                                                Motivo:
                                            </span>{' '}
                                            {latestAdjustment.reason}
                                        </p>
                                    ) : null}
                                    <p className="text-3xs text-rose-700/80 dark:text-rose-400/80">
                                        Estorno realizado por{' '}
                                        <span className="font-medium">
                                            {latestAdjustment?.user?.name ??
                                                'Operador'}
                                        </span>{' '}
                                        em{' '}
                                        {formatDateTime(
                                            latestAdjustment?.created_at ??
                                                sale.updated_at,
                                        )}
                                        . As baixas de estoque foram estornadas
                                        e as comissões apuradas foram
                                        canceladas.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* 2-Column Grid: Main Items + Summary Sidebar */}
                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Main Column: Items Table & Actions */}
                    <div className="space-y-6 lg:col-span-8">
                        <section className="surface-panel overflow-hidden p-0">
                            <div className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                                <div>
                                    <h2 className="text-lg font-semibold text-foreground">
                                        Itens da Comanda
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Serviços, produtos consumidos e itens
                                        avulsos.
                                    </p>
                                </div>

                                {canManage && isSaleOpen ? (
                                    <Dialog
                                        open={addItemOpen}
                                        onOpenChange={(open) => {
                                            setAddItemOpen(open);

                                            if (!open) {
                                                resetItemForm();
                                            }
                                        }}
                                    >
                                        <DialogTrigger asChild>
                                            <Button size="sm">
                                                <Plus className="size-4" />
                                                Adicionar Item
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Adicionar item à comanda
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Selecione o catálogo ou
                                                    cadastre um item
                                                    personalizado.
                                                </DialogDescription>
                                            </DialogHeader>

                                            <Form
                                                {...sales.items.store.form(
                                                    sale.id,
                                                )}
                                                headers={{
                                                    'X-Idempotency-Key':
                                                        createIdempotencyKey(
                                                            `sale-item-add:${sale.id}`,
                                                        ),
                                                }}
                                                resetOnSuccess
                                                onSuccess={() => {
                                                    setAddItemOpen(false);
                                                    resetItemForm();
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
                                                                sale.lock_version
                                                            }
                                                        />

                                                        {/* Type Selector */}
                                                        <div className="space-y-1.5">
                                                            <span className="text-sm font-medium text-foreground">
                                                                Tipo de Item
                                                            </span>
                                                            <div className="grid grid-cols-3 gap-2">
                                                                {(
                                                                    [
                                                                        'service',
                                                                        'product',
                                                                        'custom',
                                                                    ] as const
                                                                ).map((t) => (
                                                                    <button
                                                                        key={t}
                                                                        type="button"
                                                                        onClick={() => {
                                                                            setItemType(
                                                                                t,
                                                                            );
                                                                            resetItemForm();
                                                                        }}
                                                                        className={`flex flex-col items-center justify-center gap-1 rounded-xl border p-2.5 text-xs font-semibold transition-all ${
                                                                            itemType ===
                                                                            t
                                                                                ? 'border-primary bg-primary/10 text-primary'
                                                                                : 'border-border bg-card text-muted-foreground hover:border-border/80'
                                                                        }`}
                                                                    >
                                                                        {t ===
                                                                            'service' && (
                                                                            <Scissors className="size-4" />
                                                                        )}
                                                                        {t ===
                                                                            'product' && (
                                                                            <Package className="size-4" />
                                                                        )}
                                                                        {t ===
                                                                            'custom' && (
                                                                            <Sparkles className="size-4" />
                                                                        )}
                                                                        <span>
                                                                            {t ===
                                                                            'service'
                                                                                ? 'Serviço'
                                                                                : t ===
                                                                                    'product'
                                                                                  ? 'Produto'
                                                                                  : 'Avulso'}
                                                                        </span>
                                                                    </button>
                                                                ))}
                                                            </div>
                                                            <input
                                                                type="hidden"
                                                                name="item_type"
                                                                value={itemType}
                                                            />
                                                        </div>

                                                        {/* Dynamic Fields based on Type */}
                                                        {itemType ===
                                                        'service' ? (
                                                            <div className="space-y-3">
                                                                <FormField
                                                                    label="Serviço"
                                                                    name="service_id"
                                                                    required
                                                                    error={
                                                                        errors.service_id
                                                                    }
                                                                    action={
                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                setQuickServiceOpen(
                                                                                    true,
                                                                                )
                                                                            }
                                                                            className="rounded-xs text-xs font-semibold text-primary hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                                                        >
                                                                            +
                                                                            Novo
                                                                            Serviço
                                                                        </button>
                                                                    }
                                                                >
                                                                    <CatalogItemPicker
                                                                        id="service_id"
                                                                        name="service_id"
                                                                        type="service"
                                                                        options={
                                                                            services
                                                                        }
                                                                        required
                                                                        value={
                                                                            selectedServiceId
                                                                        }
                                                                        onChange={
                                                                            handleServiceChange
                                                                        }
                                                                    />
                                                                </FormField>

                                                                <FormField
                                                                    label="Profissional Executor (opcional)"
                                                                    name="professional_id"
                                                                    error={
                                                                        errors.professional_id
                                                                    }
                                                                >
                                                                    <select
                                                                        id="professional_id"
                                                                        name="professional_id"
                                                                        defaultValue=""
                                                                        className="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/50"
                                                                    >
                                                                        <option value="">
                                                                            Sem
                                                                            profissional
                                                                            atribuído
                                                                        </option>
                                                                        {professionals.map(
                                                                            (
                                                                                p,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        p.id
                                                                                    }
                                                                                    value={
                                                                                        p.id
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        p.name
                                                                                    }
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                    </select>
                                                                </FormField>
                                                            </div>
                                                        ) : null}

                                                        {itemType ===
                                                        'product' ? (
                                                            <div className="space-y-3">
                                                                <FormField
                                                                    label="Produto"
                                                                    name="product_id"
                                                                    required
                                                                    error={
                                                                        errors.product_id
                                                                    }
                                                                    action={
                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                setQuickProductOpen(
                                                                                    true,
                                                                                )
                                                                            }
                                                                            className="rounded-xs text-xs font-semibold text-primary hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                                                        >
                                                                            +
                                                                            Novo
                                                                            Produto
                                                                        </button>
                                                                    }
                                                                >
                                                                    <CatalogItemPicker
                                                                        id="product_id"
                                                                        name="product_id"
                                                                        type="product"
                                                                        options={
                                                                            products
                                                                        }
                                                                        required
                                                                        value={
                                                                            selectedProductId
                                                                        }
                                                                        onChange={
                                                                            handleProductChange
                                                                        }
                                                                    />
                                                                </FormField>

                                                                <FormField
                                                                    label="Profissional vendedor (opcional)"
                                                                    name="seller_professional_id"
                                                                    error={
                                                                        errors.seller_professional_id
                                                                    }
                                                                >
                                                                    <select
                                                                        id="seller_professional_id"
                                                                        name="seller_professional_id"
                                                                        value={
                                                                            sellerProfessionalId
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            setSellerProfessionalId(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        className="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/50"
                                                                    >
                                                                        <option value="">
                                                                            Sem
                                                                            vendedor
                                                                            atribuído
                                                                        </option>
                                                                        {professionals.map(
                                                                            (
                                                                                p,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        p.id
                                                                                    }
                                                                                    value={
                                                                                        p.id
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        p.name
                                                                                    }
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                    </select>
                                                                </FormField>
                                                            </div>
                                                        ) : null}

                                                        {itemType ===
                                                        'custom' ? (
                                                            <div className="space-y-3">
                                                                <FormField
                                                                    label="Descrição do item"
                                                                    name="name_snapshot"
                                                                    error={
                                                                        errors.name_snapshot
                                                                    }
                                                                >
                                                                    <Input
                                                                        id="name_snapshot"
                                                                        name="name_snapshot"
                                                                        required
                                                                        value={
                                                                            customName
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            setCustomName(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        placeholder="Ex.: Taxa de entrega, Bebida especial, Ajuste manual"
                                                                    />
                                                                </FormField>

                                                                <FormField
                                                                    label="Preço Unitário (R$)"
                                                                    name="unit_price_cents"
                                                                    error={
                                                                        errors.unit_price_cents
                                                                    }
                                                                >
                                                                    <Input
                                                                        id="unit_price_display"
                                                                        required
                                                                        value={
                                                                            customPriceStr
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            setCustomPriceStr(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        placeholder="0,00"
                                                                    />
                                                                    <input
                                                                        type="hidden"
                                                                        name="unit_price_cents"
                                                                        value={parseBrazilianCurrency(
                                                                            customPriceStr,
                                                                        )}
                                                                    />
                                                                </FormField>

                                                                <FormField
                                                                    label="Profissional responsável (opcional)"
                                                                    name="professional_id"
                                                                    error={
                                                                        errors.professional_id
                                                                    }
                                                                >
                                                                    <select
                                                                        id="professional_id"
                                                                        name="professional_id"
                                                                        defaultValue=""
                                                                        className="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/50"
                                                                    >
                                                                        <option value="">
                                                                            Sem
                                                                            profissional
                                                                            atribuído
                                                                        </option>
                                                                        {professionals.map(
                                                                            (
                                                                                p,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        p.id
                                                                                    }
                                                                                    value={
                                                                                        p.id
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        p.name
                                                                                    }
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                    </select>
                                                                </FormField>
                                                            </div>
                                                        ) : null}

                                                        {/* Quantity & Item Discount */}
                                                        <div className="grid grid-cols-2 gap-3 pt-1">
                                                            <FormField
                                                                label="Quantidade"
                                                                name="quantity"
                                                                error={
                                                                    errors.quantity
                                                                }
                                                            >
                                                                <Input
                                                                    id="quantity"
                                                                    name="quantity"
                                                                    type="number"
                                                                    min={1}
                                                                    value={
                                                                        quantity
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setQuantity(
                                                                            Math.max(
                                                                                1,
                                                                                Number.parseInt(
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                ) ||
                                                                                    1,
                                                                            ),
                                                                        )
                                                                    }
                                                                    required
                                                                />
                                                            </FormField>

                                                            <FormField
                                                                label="Desconto no item (R$)"
                                                                name="discount_cents"
                                                                error={
                                                                    errors.discount_cents
                                                                }
                                                            >
                                                                <Input
                                                                    id="discount_display"
                                                                    value={
                                                                        itemDiscountStr
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setItemDiscountStr(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="0,00"
                                                                />
                                                                <input
                                                                    type="hidden"
                                                                    name="discount_cents"
                                                                    value={
                                                                        calculatedItemDiscountCents
                                                                    }
                                                                />
                                                            </FormField>
                                                        </div>

                                                        {/* Item Preview Total */}
                                                        <div className="flex items-center justify-between rounded-xl bg-muted/40 p-3 text-sm">
                                                            <span className="text-muted-foreground">
                                                                Subtotal
                                                                estimado:
                                                            </span>
                                                            <span className="font-semibold text-foreground">
                                                                {formatMoney(
                                                                    calculatedItemSubtotalCents,
                                                                )}
                                                            </span>
                                                        </div>

                                                        <FormActions
                                                            processing={
                                                                processing
                                                            }
                                                            onCancel={() => {
                                                                setAddItemOpen(
                                                                    false,
                                                                );
                                                                resetItemForm();
                                                            }}
                                                            label="Adicionar Item"
                                                        />
                                                    </>
                                                )}
                                            </Form>

                                            <QuickCreateServiceModal
                                                open={quickServiceOpen}
                                                onOpenChange={
                                                    setQuickServiceOpen
                                                }
                                                onSuccess={handleServiceCreated}
                                            />
                                            <QuickCreateProductModal
                                                open={quickProductOpen}
                                                onOpenChange={
                                                    setQuickProductOpen
                                                }
                                                onSuccess={handleProductCreated}
                                            />
                                        </DialogContent>
                                    </Dialog>
                                ) : null}
                            </div>

                            {/* Items List / Table */}
                            {(sale.items?.length ?? 0) === 0 ? (
                                <div className="flex flex-col items-center justify-center p-8 text-center text-muted-foreground">
                                    <Receipt className="mb-2 size-8 text-muted-foreground/50" />
                                    <p className="font-medium text-foreground">
                                        Nenhum item adicionado ainda
                                    </p>
                                    <p className="text-xs">
                                        {isSaleOpen && canManage
                                            ? 'Clique no botão "Adicionar Item" acima para lançar serviços ou produtos.'
                                            : 'Esta comanda não possui itens lançados.'}
                                    </p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-border bg-muted/30 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3 sm:px-5">
                                                    Item / Descrição
                                                </th>
                                                <th className="px-3 py-3">
                                                    Executor / Vendedor
                                                </th>
                                                <th className="px-3 py-3 text-center">
                                                    Qtd
                                                </th>
                                                <th className="px-3 py-3 text-right">
                                                    Preço Unit.
                                                </th>
                                                <th className="px-3 py-3 text-right">
                                                    Desconto
                                                </th>
                                                <th className="px-3 py-3 text-right">
                                                    Subtotal
                                                </th>
                                                {canManage && isSaleOpen ? (
                                                    <th className="w-10 px-3 py-3 text-center">
                                                        <span className="sr-only">
                                                            Ações
                                                        </span>
                                                    </th>
                                                ) : null}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {sale.items?.map(
                                                (item: SaleItem) => (
                                                    <tr
                                                        key={item.id}
                                                        className="transition-colors hover:bg-muted/20"
                                                    >
                                                        <td className="px-4 py-3.5 sm:px-5">
                                                            <div className="flex flex-col gap-1">
                                                                <div className="flex items-center gap-2">
                                                                    <ItemTypeBadge
                                                                        type={
                                                                            item.item_type
                                                                        }
                                                                    />
                                                                    <span className="font-semibold text-foreground">
                                                                        {
                                                                            item.name_snapshot
                                                                        }
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3.5 text-xs text-muted-foreground">
                                                            <div className="flex flex-col gap-1">
                                                                <span>
                                                                    Executor:{' '}
                                                                    {item
                                                                        .professional
                                                                        ?.name ??
                                                                        '—'}
                                                                </span>
                                                                {item.item_type ===
                                                                    'product' &&
                                                                item.seller_professional ? (
                                                                    <span className="text-purple-700 dark:text-purple-300">
                                                                        Vendedor:{' '}
                                                                        {
                                                                            item
                                                                                .seller_professional
                                                                                .name
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3.5 text-center font-medium">
                                                            {item.quantity}
                                                        </td>
                                                        <td className="px-3 py-3.5 text-right text-xs">
                                                            {formatMoney(
                                                                item.unit_price_cents,
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-3.5 text-right text-xs text-muted-foreground">
                                                            {item.discount_cents >
                                                            0
                                                                ? `-${formatMoney(item.discount_cents)}`
                                                                : '—'}
                                                        </td>
                                                        <td className="px-3 py-3.5 text-right font-semibold text-foreground">
                                                            {formatMoney(
                                                                item.total_cents,
                                                            )}
                                                        </td>
                                                        {canManage &&
                                                        isSaleOpen ? (
                                                            <td className="px-3 py-3.5 text-center">
                                                                <Form
                                                                    {...sales.items.destroy.form(
                                                                        {
                                                                            sale: sale.id,
                                                                            item: item.id,
                                                                        },
                                                                    )}
                                                                    onSubmit={(
                                                                        e,
                                                                    ) => {
                                                                        if (
                                                                            !window.confirm(
                                                                                `Remover "${item.name_snapshot}" da comanda?`,
                                                                            )
                                                                        ) {
                                                                            e.preventDefault();
                                                                        }
                                                                    }}
                                                                >
                                                                    <input
                                                                        type="hidden"
                                                                        name="lock_version"
                                                                        value={
                                                                            sale.lock_version
                                                                        }
                                                                    />
                                                                    <button
                                                                        type="submit"
                                                                        title="Remover item"
                                                                        aria-label={`Remover item ${item.name_snapshot}`}
                                                                        className="rounded p-1 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                                                    >
                                                                        <Trash2 className="size-4" />
                                                                    </button>
                                                                </Form>
                                                            </td>
                                                        ) : null}
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>

                        {/* Notes Card */}
                        {sale.notes ? (
                            <section className="surface-panel space-y-1.5 p-4 sm:p-5">
                                <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Observações da Comanda
                                </h3>
                                <p className="text-sm whitespace-pre-wrap text-foreground">
                                    {sale.notes}
                                </p>
                            </section>
                        ) : null}
                    </div>

                    {/* Financial Summary Sidebar */}
                    <div className="space-y-6 lg:col-span-4">
                        {/* Totals Card */}
                        <section className="surface-panel space-y-4 p-5">
                            <h2 className="text-base font-semibold text-foreground">
                                Resumo Financeiro
                            </h2>

                            <div className="space-y-2.5 text-sm">
                                <div className="flex items-center justify-between text-muted-foreground">
                                    <span>Total Bruto:</span>
                                    <span>
                                        {formatMoney(sale.total_amount_cents)}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between text-muted-foreground">
                                    <span className="flex items-center gap-1">
                                        <Percent className="size-3.5" />
                                        Desconto Geral:
                                    </span>
                                    <span
                                        className={
                                            sale.discount_amount_cents > 0
                                                ? 'font-medium text-amber-600 dark:text-amber-400'
                                                : ''
                                        }
                                    >
                                        {sale.discount_amount_cents > 0
                                            ? `-${formatMoney(sale.discount_amount_cents)}`
                                            : formatMoney(0)}
                                    </span>
                                </div>

                                <div className="border-t border-border pt-3">
                                    <div className="flex items-baseline justify-between">
                                        <span className="font-bold text-foreground">
                                            Total a Pagar:
                                        </span>
                                        <span className="font-display text-2xl font-extrabold text-foreground">
                                            {formatMoney(
                                                sale.final_amount_cents,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Apply Discount Button & Dialog */}
                            {canDiscount && isSaleActive ? (
                                <div className="pt-2">
                                    <Dialog
                                        open={discountOpen}
                                        onOpenChange={setDiscountOpen}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                            >
                                                <Percent className="size-4" />
                                                {sale.discount_amount_cents > 0
                                                    ? 'Alterar Desconto Geral'
                                                    : 'Aplicar Desconto Geral'}
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent className="sm:max-w-md">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Desconto Geral na Comanda
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Defina o valor em reais do
                                                    desconto sobre o valor total
                                                    da comanda.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <Form
                                                {...sales.discount.form(
                                                    sale.id,
                                                )}
                                                headers={{
                                                    'X-Idempotency-Key':
                                                        createIdempotencyKey(
                                                            `sale-discount:${sale.id}`,
                                                        ),
                                                }}
                                                onSuccess={() =>
                                                    setDiscountOpen(false)
                                                }
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
                                                                sale.lock_version
                                                            }
                                                        />

                                                        <FormField
                                                            label="Valor do desconto (R$)"
                                                            name="discount_amount_cents"
                                                            error={
                                                                errors.discount_amount_cents
                                                            }
                                                        >
                                                            <Input
                                                                id="discount_amount_display"
                                                                required
                                                                autoFocus
                                                                value={
                                                                    generalDiscountStr
                                                                }
                                                                onChange={(e) =>
                                                                    setGeneralDiscountStr(
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder="0,00"
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name="discount_amount_cents"
                                                                value={parseBrazilianCurrency(
                                                                    generalDiscountStr,
                                                                )}
                                                            />
                                                        </FormField>

                                                        <FormField
                                                            label="Justificativa / Notas do desconto"
                                                            name="notes"
                                                            error={errors.notes}
                                                        >
                                                            <textarea
                                                                id="discount_notes"
                                                                name="notes"
                                                                rows={2}
                                                                value={
                                                                    discountNotes
                                                                }
                                                                onChange={(e) =>
                                                                    setDiscountNotes(
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder="Ex.: Cortesia de gerência, fidelidade, etc."
                                                                className="min-h-20 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[2px] focus-visible:ring-ring/50"
                                                            />
                                                        </FormField>

                                                        <FormActions
                                                            processing={
                                                                processing
                                                            }
                                                            onCancel={() =>
                                                                setDiscountOpen(
                                                                    false,
                                                                )
                                                            }
                                                            label="Salvar Desconto"
                                                        />
                                                    </>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            ) : null}
                        </section>

                        {/* Customer & Appointment Info Card */}
                        <section className="surface-panel space-y-3.5 p-5">
                            <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Vínculo & Atendimento
                            </h3>

                            <div className="space-y-3 text-sm">
                                <div className="flex items-start gap-2.5">
                                    <User className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0">
                                        <p className="font-medium text-foreground">
                                            {sale.customer?.name ??
                                                'Cliente Avulso'}
                                        </p>
                                        {sale.customer?.phone ? (
                                            <p className="text-xs text-muted-foreground">
                                                {sale.customer.phone}
                                            </p>
                                        ) : null}
                                    </div>
                                </div>

                                {sale.appointment_link?.appointment ? (
                                    <div className="space-y-1 rounded-xl border border-primary/20 bg-primary/5 p-3 text-xs">
                                        <div className="flex items-center gap-1.5 font-semibold text-primary">
                                            <Calendar className="size-3.5" />
                                            Agendamento Vinculado
                                        </div>
                                        <p className="text-muted-foreground">
                                            Horário:{' '}
                                            {formatDateTime(
                                                sale.appointment_link
                                                    .appointment.starts_at,
                                            )}
                                        </p>
                                        <p className="text-muted-foreground capitalize">
                                            Status:{' '}
                                            {
                                                sale.appointment_link
                                                    .appointment.status
                                            }
                                        </p>
                                        <div className="pt-1">
                                            <Button
                                                asChild
                                                variant="link"
                                                size="sm"
                                                className="h-auto p-0 text-xs font-medium text-primary"
                                            >
                                                <Link href={calendar.index()}>
                                                    Ver na Agenda →
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        </section>

                        {/* Status History Timeline */}
                        {(sale.status_histories?.length ?? 0) > 0 ? (
                            <section className="surface-panel space-y-3.5 p-5">
                                <div className="flex items-center gap-2">
                                    <History className="size-4 text-muted-foreground" />
                                    <h3 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Histórico de Transições
                                    </h3>
                                </div>

                                <div className="space-y-3 text-xs">
                                    {sale.status_histories?.map((history) => (
                                        <div
                                            key={history.id}
                                            className="relative border-l-2 border-border pb-1 pl-3"
                                        >
                                            <div className="flex items-center justify-between text-muted-foreground">
                                                <span className="font-semibold text-foreground capitalize">
                                                    {history.to_status.replace(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </span>
                                                <span>
                                                    {formatDateTime(
                                                        history.created_at,
                                                    )}
                                                </span>
                                            </div>
                                            {history.reason ? (
                                                <p className="mt-0.5 text-muted-foreground">
                                                    {history.reason}
                                                </p>
                                            ) : null}
                                            {history.user?.name ? (
                                                <p className="mt-0.5 text-2xs text-muted-foreground/80">
                                                    Por {history.user.name}
                                                </p>
                                            ) : null}
                                        </div>
                                    ))}
                                </div>
                            </section>
                        ) : null}
                    </div>
                </div>
            </PageCanvas>
        </>
    );
}

SalesShow.layout = {
    breadcrumbs: [
        { title: 'Comandas', href: sales.index() },
        { title: 'Detalhes da Comanda' },
    ],
};
