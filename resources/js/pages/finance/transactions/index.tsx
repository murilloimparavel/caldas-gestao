import { Form, Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowDownRight,
    ArrowLeftRight,
    ArrowUpRight,
    Ban,
    Calendar,
    CheckCircle2,
    Clock,
    Edit3,
    Plus,
    Search,
    User,
    Users,
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
import type { Paginated } from '@/components/operational';
import {
    QuickCreateCategoryModal,
    QuickCreateCustomerModal,
    QuickCreateSupplierModal,
} from '@/components/operational/quick-create-dialogs';
import type { CreatedEntity } from '@/components/operational/quick-create-dialogs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CustomerPicker } from '@/components/customer-picker';
import { RemoteOptionPicker } from '@/components/remote-option-picker';
import { Card, CardContent } from '@/components/ui/card';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    FinancialObligation,
    FinancialObligationType,
    SharedPageProps,
} from '@/types';

type Option = {
    id: string;
    name: string;
    phone?: string | null;
};

type Props = {
    obligations: Paginated<FinancialObligation>;
    metrics: {
        total_payable_pending_cents: number;
        total_receivable_pending_cents: number;
        total_overdue_cents: number;
        total_paid_month_cents: number;
    };
    filters: {
        search: string;
        type: string;
        status: string;
        category_id: string;
        supplier_id: string;
        customer_id: string;
        date_start: string;
        date_end: string;
    };
    categoryOptions: Option[];
    supplierOptions: Option[];
    customerOptions: Option[];
};

const PAYMENT_METHODS = [
    { value: 'pix', label: 'PIX' },
    { value: 'dinheiro', label: 'Dinheiro' },
    { value: 'cartao_credito', label: 'Cartão de Crédito' },
    { value: 'cartao_debito', label: 'Cartão de Débito' },
    { value: 'boleto', label: 'Boleto Bancário' },
    { value: 'transferencia', label: 'Transferência Bancária' },
    { value: 'outros', label: 'Outro' },
];

export default function FinancialTransactionsIndex({
    obligations,
    metrics,
    filters,
    categoryOptions: initialCategoryOptions,
    supplierOptions: initialSupplierOptions,
    customerOptions: initialCustomerOptions,
}: Props) {
    const { auth } = usePage<SharedPageProps>().props;
    const canManage = auth.permissions.includes('financial.manage');
    const canSettle = auth.permissions.includes('financial.settle');

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [createType, setCreateType] =
        useState<FinancialObligationType>('payable');
    const [createAmount, setCreateAmount] = useState('');
    const [createKey] = useState(() =>
        createIdempotencyKey('create-obligation'),
    );

    // Dynamic lists & selected values for quick create auto-selection
    const [categories, setCategories] = useState<Option[]>(
        initialCategoryOptions,
    );
    const [suppliers, setSuppliers] = useState<Option[]>(
        initialSupplierOptions,
    );
    const [customersList, setCustomersList] = useState<Option[]>(
        initialCustomerOptions,
    );

    const [selectedCategoryId, setSelectedCategoryId] = useState('');
    const [selectedSupplierId, setSelectedSupplierId] = useState('');
    const [selectedCustomerId, setSelectedCustomerId] = useState('');

    const [quickCategoryOpen, setQuickCategoryOpen] = useState(false);
    const [quickSupplierOpen, setQuickSupplierOpen] = useState(false);
    const [quickCustomerOpen, setQuickCustomerOpen] = useState(false);

    const handleCategoryCreated = (created: CreatedEntity) => {
        const newOpt: Option = { id: created.id, name: created.name };
        setCategories((prev) => [
            ...prev.filter((c) => c.id !== created.id),
            newOpt,
        ]);
        setSelectedCategoryId(created.id);
    };

    const handleSupplierCreated = (created: CreatedEntity) => {
        const newOpt: Option = { id: created.id, name: created.name };
        setSuppliers((prev) => [
            ...prev.filter((s) => s.id !== created.id),
            newOpt,
        ]);
        setSelectedSupplierId(created.id);
    };

    const handleCustomerCreated = (created: CreatedEntity) => {
        const newOpt: Option = { id: created.id, name: created.name };
        setCustomersList((prev) => [
            ...prev.filter((c) => c.id !== created.id),
            newOpt,
        ]);
        setSelectedCustomerId(created.id);
    };

    const [editingObligation, setEditingObligation] =
        useState<FinancialObligation | null>(null);
    const [editAmount, setEditAmount] = useState('');
    const [editCustomerId, setEditCustomerId] = useState('');

    const [settlingObligation, setSettlingObligation] =
        useState<FinancialObligation | null>(null);
    const [cancellingObligation, setCancellingObligation] =
        useState<FinancialObligation | null>(null);

    // Search and filter state
    const [search, setSearch] = useState(filters.search || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [categoryIdFilter, setCategoryIdFilter] = useState(
        filters.category_id || '',
    );
    const [dateStart, setDateStart] = useState(filters.date_start || '');
    const [dateEnd, setDateEnd] = useState(filters.date_end || '');

    const applyFilters = (overrides = {}) => {
        router.get(
            '/finance/transactions',
            {
                search,
                type: typeFilter,
                status: statusFilter,
                category_id: categoryIdFilter,
                date_start: dateStart,
                date_end: dateEnd,
                ...overrides,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const handleTypeChange = (newType: string) => {
        setTypeFilter(newType);
        applyFilters({ type: newType });
    };

    const handleStatusChange = (newStatus: string) => {
        setStatusFilter(newStatus);
        applyFilters({ status: newStatus });
    };

    const openEdit = (item: FinancialObligation) => {
        const category = item.category;
        const supplier = item.supplier;
        const customer = item.customer;

        if (category) {
            setCategories((current) => [
                ...current.filter((option) => option.id !== category.id),
                category,
            ]);
        }

        if (supplier) {
            setSuppliers((current) => [
                ...current.filter((option) => option.id !== supplier.id),
                supplier,
            ]);
        }

        if (customer) {
            setCustomersList((current) => [
                ...current.filter((option) => option.id !== customer.id),
                customer,
            ]);
        }

        setEditingObligation(item);
        setEditAmount((item.amount_cents / 100).toFixed(2));
        setEditCustomerId(item.customer_id ?? '');
    };

    const todayStr = new Date().toISOString().split('T')[0];

    const isOverdue = (item: FinancialObligation) => {
        return item.status === 'pending' && item.due_date < todayStr;
    };

    return (
        <>
            <Head title="Contas a Pagar e Receber - Financeiro" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Financeiro"
                    title="Contas a Pagar e Receber"
                    description="Controle e liquidação de obrigações financeiras, despesas operacionais e receitas da unidade."
                    action={
                        canManage ? (
                            <Dialog
                                open={isCreateOpen}
                                onOpenChange={setIsCreateOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button className="w-full gap-2 sm:w-auto">
                                        <Plus
                                            className="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                        Novo Lançamento
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Novo Lançamento Financeiro
                                        </DialogTitle>
                                        <DialogDescription>
                                            Cadastre uma conta a pagar ou a
                                            receber com data de vencimento.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="mb-4 flex gap-2 rounded-lg bg-muted p-1">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setCreateType('payable')
                                            }
                                            className={`flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                                createType === 'payable'
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            <ArrowDownRight
                                                className="h-4 w-4 text-rose-500"
                                                aria-hidden="true"
                                            />
                                            Conta a Pagar (Despesa)
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setCreateType('receivable')
                                            }
                                            className={`flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                                createType === 'receivable'
                                                    ? 'bg-background text-foreground shadow-sm'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            <ArrowUpRight
                                                className="h-4 w-4 text-emerald-500"
                                                aria-hidden="true"
                                            />
                                            Conta a Receber (Receita)
                                        </button>
                                    </div>

                                    <Form
                                        action="/finance/transactions"
                                        method="post"
                                        headers={{
                                            'X-Idempotency-Key': createKey,
                                        }}
                                        resetOnSuccess
                                        onSuccess={() => {
                                            setIsCreateOpen(false);
                                            setCreateAmount('');
                                            setSelectedCategoryId('');
                                            setSelectedSupplierId('');
                                            setSelectedCustomerId('');
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
                                                    value={createType}
                                                />

                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Descrição"
                                                            name="description"
                                                            error={
                                                                errors.description
                                                            }
                                                        >
                                                            <Input
                                                                id="description"
                                                                name="description"
                                                                required
                                                                placeholder={
                                                                    createType ===
                                                                    'payable'
                                                                        ? 'Ex.: Conta de Energia Elétrica'
                                                                        : 'Ex.: Pagamento de Pacote Corporativo'
                                                                }
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField
                                                            label="Valor (R$)"
                                                            name="amount"
                                                            error={
                                                                errors.amount_cents
                                                            }
                                                        >
                                                            <div className="relative">
                                                                <span className="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                                                                    R$
                                                                </span>
                                                                <Input
                                                                    id="amount_input"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0.01"
                                                                    required
                                                                    value={
                                                                        createAmount
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setCreateAmount(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    className="pl-10"
                                                                    placeholder="0,00"
                                                                />
                                                                <input
                                                                    type="hidden"
                                                                    name="amount_cents"
                                                                    value={Math.round(
                                                                        (parseFloat(
                                                                            createAmount,
                                                                        ) ||
                                                                            0) *
                                                                            100,
                                                                    )}
                                                                />
                                                            </div>
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField
                                                            label="Data de Vencimento"
                                                            name="due_date"
                                                            error={
                                                                errors.due_date
                                                            }
                                                        >
                                                            <Input
                                                                id="due_date"
                                                                name="due_date"
                                                                type="date"
                                                                required
                                                                defaultValue={
                                                                    todayStr
                                                                }
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField
                                                            label="Categoria"
                                                            name="category_id"
                                                            error={
                                                                errors.category_id
                                                            }
                                                            action={
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        setQuickCategoryOpen(
                                                                            true,
                                                                        )
                                                                    }
                                                                    className="rounded-xs text-xs font-semibold text-primary hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                                                >
                                                                    + Nova
                                                                    Categoria
                                                                </button>
                                                            }
                                                        >
                                                            <RemoteOptionPicker
                                                                id="category_id"
                                                                name="category_id"
                                                                options={
                                                                    categories
                                                                }
                                                                placeholder="Selecione uma categoria (opcional)"
                                                                resource="categories"
                                                                value={
                                                                    selectedCategoryId
                                                                }
                                                                onChange={
                                                                    setSelectedCategoryId
                                                                }
                                                            />
                                                        </FormField>
                                                    </div>

                                                    {createType ===
                                                    'payable' ? (
                                                        <div>
                                                            <FormField
                                                                label="Fornecedor"
                                                                name="supplier_id"
                                                                error={
                                                                    errors.supplier_id
                                                                }
                                                                action={
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setQuickSupplierOpen(
                                                                                true,
                                                                            )
                                                                        }
                                                                        className="rounded-xs text-xs font-semibold text-primary hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                                                    >
                                                                        + Novo
                                                                        Fornecedor
                                                                    </button>
                                                                }
                                                            >
                                                                <RemoteOptionPicker
                                                                    id="supplier_id"
                                                                    name="supplier_id"
                                                                    options={
                                                                        suppliers
                                                                    }
                                                                    placeholder="Selecione o fornecedor (opcional)"
                                                                    resource="suppliers"
                                                                    value={
                                                                        selectedSupplierId
                                                                    }
                                                                    onChange={
                                                                        setSelectedSupplierId
                                                                    }
                                                                />
                                                            </FormField>
                                                        </div>
                                                    ) : (
                                                        <div>
                                                            <CustomerPicker
                                                                label="Cliente"
                                                                name="customer_id"
                                                                id="customer_id"
                                                                value={
                                                                    selectedCustomerId
                                                                }
                                                                options={
                                                                    customersList
                                                                }
                                                                onChange={
                                                                    setSelectedCustomerId
                                                                }
                                                                error={
                                                                    errors.customer_id
                                                                }
                                                                helper="Opcional — pesquise por nome ou telefone"
                                                                action={
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setQuickCustomerOpen(
                                                                                true,
                                                                            )
                                                                        }
                                                                        className="rounded-xs text-xs font-semibold text-primary hover:underline focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                                                    >
                                                                        + Novo
                                                                        Cliente
                                                                    </button>
                                                                }
                                                            />
                                                        </div>
                                                    )}

                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Observações"
                                                            name="notes"
                                                            error={errors.notes}
                                                        >
                                                            <Input
                                                                id="notes"
                                                                name="notes"
                                                                placeholder="Detalhes ou anotações adicionais..."
                                                            />
                                                        </FormField>
                                                    </div>
                                                </div>

                                                <FormActions
                                                    onCancel={() =>
                                                        setIsCreateOpen(false)
                                                    }
                                                    processing={processing}
                                                    submitText="Criar Lançamento"
                                                />
                                            </>
                                        )}
                                    </Form>

                                    <QuickCreateCategoryModal
                                        open={quickCategoryOpen}
                                        onOpenChange={setQuickCategoryOpen}
                                        onSuccess={handleCategoryCreated}
                                    />
                                    <QuickCreateSupplierModal
                                        open={quickSupplierOpen}
                                        onOpenChange={setQuickSupplierOpen}
                                        onSuccess={handleSupplierCreated}
                                    />
                                    <QuickCreateCustomerModal
                                        open={quickCustomerOpen}
                                        onOpenChange={setQuickCustomerOpen}
                                        onSuccess={handleCustomerCreated}
                                    />
                                </DialogContent>
                            </Dialog>
                        ) : null
                    }
                />

                {/* Metrics Banner */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-emerald-500/20 bg-emerald-500/5">
                        <CardContent className="flex items-center justify-between p-4">
                            <div>
                                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    A Receber (Pendente)
                                </p>
                                <p className="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                    {formatMoney(
                                        metrics.total_receivable_pending_cents,
                                    )}
                                </p>
                            </div>
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
                                <ArrowUpRight
                                    className="h-5 w-5"
                                    aria-hidden="true"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-rose-500/20 bg-rose-500/5">
                        <CardContent className="flex items-center justify-between p-4">
                            <div>
                                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    A Pagar (Pendente)
                                </p>
                                <p className="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">
                                    {formatMoney(
                                        metrics.total_payable_pending_cents,
                                    )}
                                </p>
                            </div>
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-rose-500/10 text-rose-600">
                                <ArrowDownRight
                                    className="h-5 w-5"
                                    aria-hidden="true"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-amber-500/20 bg-amber-500/5">
                        <CardContent className="flex items-center justify-between p-4">
                            <div>
                                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Total Vencido
                                </p>
                                <p className="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">
                                    {formatMoney(metrics.total_overdue_cents)}
                                </p>
                            </div>
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/10 text-amber-600">
                                <AlertCircle
                                    className="h-5 w-5"
                                    aria-hidden="true"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sky-500/20 bg-sky-500/5">
                        <CardContent className="flex items-center justify-between p-4">
                            <div>
                                <p className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Liquidado no Mês
                                </p>
                                <p className="mt-1 text-2xl font-bold text-sky-600 dark:text-sky-400">
                                    {formatMoney(
                                        metrics.total_paid_month_cents,
                                    )}
                                </p>
                            </div>
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-sky-500/10 text-sky-600">
                                <CheckCircle2
                                    className="h-5 w-5"
                                    aria-hidden="true"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters & Search Toolbar */}
                <div className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="flex rounded-lg border bg-muted p-1 text-xs">
                                <button
                                    type="button"
                                    onClick={() => handleTypeChange('')}
                                    className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                        typeFilter === ''
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Todos
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleTypeChange('payable')}
                                    className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                        typeFilter === 'payable'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    A Pagar
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        handleTypeChange('receivable')
                                    }
                                    className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                        typeFilter === 'receivable'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    A Receber
                                </button>
                            </div>

                            <div className="flex rounded-lg border bg-muted p-1 text-xs">
                                <button
                                    type="button"
                                    onClick={() => handleStatusChange('')}
                                    className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                        statusFilter === ''
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Todos Status
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        handleStatusChange('pending')
                                    }
                                    className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                        statusFilter === 'pending'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Pendentes
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        handleStatusChange('overdue')
                                    }
                                    className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                        statusFilter === 'overdue'
                                            ? 'bg-background font-semibold text-rose-600 shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Vencidos
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusChange('paid')}
                                    className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                        statusFilter === 'paid'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Liquidados
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        handleStatusChange('cancelled')
                                    }
                                    className={`rounded-md px-3 py-1.5 font-medium transition-colors ${
                                        statusFilter === 'cancelled'
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Cancelados
                                </button>
                            </div>
                        </div>

                        <form
                            onSubmit={handleSearchSubmit}
                            className="flex max-w-md min-w-[240px] flex-1 items-center gap-2"
                        >
                            <div className="relative flex-1">
                                <Search
                                    className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    aria-label="Buscar descrição, cliente ou fornecedor"
                                    placeholder="Buscar descrição, cliente ou fornecedor..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="secondary" size="sm">
                                Buscar
                            </Button>
                        </form>
                    </div>

                    <div className="grid grid-cols-1 gap-3 border-t pt-2 text-xs sm:grid-cols-3">
                        <div>
                            <RemoteOptionPicker
                                id="filter-category"
                                label="Categoria"
                                name="category_id"
                                value={categoryIdFilter}
                                onChange={(value) => {
                                    setCategoryIdFilter(value);
                                    applyFilters({ category_id: value });
                                }}
                                options={categories}
                                placeholder="Todas as categorias"
                                resource="categories"
                            />
                        </div>
                        <div>
                            <label
                                htmlFor="filter-date-start"
                                className="mb-1 block font-medium text-muted-foreground"
                            >
                                Data De
                            </label>
                            <Input
                                id="filter-date-start"
                                type="date"
                                value={dateStart}
                                onChange={(e) => {
                                    setDateStart(e.target.value);
                                    applyFilters({
                                        date_start: e.target.value,
                                    });
                                }}
                                className="h-8 text-xs"
                            />
                        </div>
                        <div>
                            <label
                                htmlFor="filter-date-end"
                                className="mb-1 block font-medium text-muted-foreground"
                            >
                                Data Até
                            </label>
                            <Input
                                id="filter-date-end"
                                type="date"
                                value={dateEnd}
                                onChange={(e) => {
                                    setDateEnd(e.target.value);
                                    applyFilters({ date_end: e.target.value });
                                }}
                                className="h-8 text-xs"
                            />
                        </div>
                    </div>
                </div>

                {/* Obligations Table */}
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    {obligations.data.length === 0 ? (
                        <EmptyState
                            icon={ArrowLeftRight}
                            title="Nenhum lançamento encontrado"
                            description="Não há contas a pagar ou receber correspondentes aos filtros selecionados."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                    <TableHead className="px-4 py-3 text-xs uppercase">
                                        Tipo
                                    </TableHead>
                                    <TableHead className="px-4 py-3 text-xs uppercase">
                                        Descrição / Contato
                                    </TableHead>
                                    <TableHead className="px-4 py-3 text-xs uppercase">
                                        Categoria
                                    </TableHead>
                                    <TableHead className="px-4 py-3 text-xs uppercase">
                                        Vencimento
                                    </TableHead>
                                    <TableHead className="px-4 py-3 text-xs uppercase">
                                        Valor
                                    </TableHead>
                                    <TableHead className="px-4 py-3 text-xs uppercase">
                                        Status
                                    </TableHead>
                                    <TableHead className="px-4 py-3 text-right text-xs uppercase">
                                        Ações
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="divide-y">
                                {obligations.data.map((item) => {
                                    const overdue = isOverdue(item);

                                    return (
                                        <TableRow
                                            key={item.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <TableCell className="px-4 py-3 whitespace-nowrap">
                                                {item.type === 'payable' ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="gap-1 border-destructive/30 bg-destructive/10 font-medium text-destructive"
                                                    >
                                                        <ArrowDownRight
                                                            className="h-3 w-3"
                                                            aria-hidden="true"
                                                        />
                                                        A Pagar
                                                    </Badge>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className="gap-1 border-success/30 bg-success/10 font-medium text-success"
                                                    >
                                                        <ArrowUpRight
                                                            className="h-3 w-3"
                                                            aria-hidden="true"
                                                        />
                                                        A Receber
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-4 py-3">
                                                <p className="font-medium text-foreground">
                                                    {item.description}
                                                </p>
                                                {item.supplier && (
                                                    <p className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                                        <Users
                                                            className="h-3 w-3"
                                                            aria-hidden="true"
                                                        />
                                                        Fornecedor:{' '}
                                                        {item.supplier.name}
                                                    </p>
                                                )}
                                                {item.customer && (
                                                    <p className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                                        <User
                                                            className="h-3 w-3"
                                                            aria-hidden="true"
                                                        />
                                                        Cliente:{' '}
                                                        {item.customer.name}
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                {item.category?.name || '—'}
                                            </TableCell>
                                            <TableCell className="px-4 py-3 whitespace-nowrap">
                                                <div className="flex items-center gap-1.5">
                                                    <Calendar
                                                        className="h-3.5 w-3.5 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    <span
                                                        className={
                                                            overdue
                                                                ? 'font-semibold text-destructive'
                                                                : 'text-foreground'
                                                        }
                                                    >
                                                        {new Date(
                                                            item.due_date +
                                                                'T00:00:00',
                                                        ).toLocaleDateString(
                                                            'pt-BR',
                                                        )}
                                                    </span>
                                                    {overdue && (
                                                        <Badge
                                                            variant="destructive"
                                                            className="px-1.5 py-0 text-2xs"
                                                        >
                                                            Vencido
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-4 py-3 font-semibold whitespace-nowrap">
                                                <span
                                                    className={
                                                        item.type === 'payable'
                                                            ? 'text-destructive'
                                                            : 'text-success'
                                                    }
                                                >
                                                    {item.type === 'payable'
                                                        ? '- '
                                                        : '+ '}
                                                    {formatMoney(
                                                        item.amount_cents,
                                                    )}
                                                </span>
                                            </TableCell>
                                            <TableCell className="px-4 py-3 whitespace-nowrap">
                                                {item.status === 'paid' && (
                                                    <div className="flex flex-col gap-0.5">
                                                        <Badge
                                                            variant="secondary"
                                                            className="w-fit gap-1 bg-success/15 text-success"
                                                        >
                                                            <CheckCircle2
                                                                className="h-3 w-3"
                                                                aria-hidden="true"
                                                            />
                                                            Liquidado
                                                        </Badge>
                                                        {item.paid_date && (
                                                            <span className="text-3xs text-muted-foreground">
                                                                {new Date(
                                                                    item.paid_date +
                                                                        'T00:00:00',
                                                                ).toLocaleDateString(
                                                                    'pt-BR',
                                                                )}{' '}
                                                                (
                                                                {item.payment_method ||
                                                                    '—'}
                                                                )
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                                {item.status === 'pending' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="gap-1 border-warning/30 bg-warning/15 text-warning"
                                                    >
                                                        <Clock
                                                            className="h-3 w-3"
                                                            aria-hidden="true"
                                                        />
                                                        Pendente
                                                    </Badge>
                                                )}
                                                {item.status ===
                                                    'cancelled' && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="gap-1 bg-muted text-muted-foreground"
                                                    >
                                                        <Ban
                                                            className="h-3 w-3"
                                                            aria-hidden="true"
                                                        />
                                                        Cancelado
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="px-4 py-3 text-right whitespace-nowrap">
                                                {item.status === 'pending' && (
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        {canSettle && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="h-8 border-success/30 text-success hover:bg-success/10 hover:text-success"
                                                                onClick={() =>
                                                                    setSettlingObligation(
                                                                        item,
                                                                    )
                                                                }
                                                                aria-label={`Liquidar ${item.description || 'lançamento'}`}
                                                            >
                                                                <CheckCircle2
                                                                    className="mr-1 h-3.5 w-3.5"
                                                                    aria-hidden="true"
                                                                />
                                                                Liquidar
                                                                <span className="sr-only">
                                                                    Liquidar{' '}
                                                                    {item.description ||
                                                                        'lançamento'}
                                                                </span>
                                                            </Button>
                                                        )}
                                                        {canManage && (
                                                            <>
                                                                <Button
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="h-8 w-8"
                                                                    onClick={() =>
                                                                        openEdit(
                                                                            item,
                                                                        )
                                                                    }
                                                                    aria-label={`Editar ${item.description || 'lançamento'}`}
                                                                    title="Editar Lançamento"
                                                                >
                                                                    <Edit3
                                                                        className="h-3.5 w-3.5"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <span className="sr-only">
                                                                        Editar{' '}
                                                                        {item.description ||
                                                                            'lançamento'}
                                                                    </span>
                                                                </Button>
                                                                <Button
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                    onClick={() =>
                                                                        setCancellingObligation(
                                                                            item,
                                                                        )
                                                                    }
                                                                    aria-label={`Cancelar ${item.description || 'lançamento'}`}
                                                                    title="Cancelar Lançamento"
                                                                >
                                                                    <Ban
                                                                        className="h-3.5 w-3.5"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <span className="sr-only">
                                                                        Cancelar{' '}
                                                                        {item.description ||
                                                                            'lançamento'}
                                                                    </span>
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}

                    {obligations.links && obligations.links.length > 3 && (
                        <div className="border-t p-4">
                            <Pagination links={obligations.links} />
                        </div>
                    )}
                </div>

                {/* Edit Modal */}
                {editingObligation && (
                    <Dialog
                        open={!!editingObligation}
                        onOpenChange={(open) =>
                            !open && setEditingObligation(null)
                        }
                    >
                        <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>
                                    Editar Lançamento Financeiro
                                </DialogTitle>
                                <DialogDescription>
                                    Atualize as informações do lançamento
                                    pendente.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                action={`/finance/transactions/${editingObligation.id}`}
                                method="put"
                                resetOnSuccess
                                onSuccess={() => setEditingObligation(null)}
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormErrorSummary errors={errors} />
                                        <input
                                            type="hidden"
                                            name="lock_version"
                                            value={
                                                editingObligation.lock_version
                                            }
                                        />
                                        <input
                                            type="hidden"
                                            name="type"
                                            value={editingObligation.type}
                                        />

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="sm:col-span-2">
                                                <FormField
                                                    label="Descrição"
                                                    name="description"
                                                    error={errors.description}
                                                >
                                                    <Input
                                                        id="edit_description"
                                                        name="description"
                                                        required
                                                        defaultValue={
                                                            editingObligation.description
                                                        }
                                                    />
                                                </FormField>
                                            </div>

                                            <div>
                                                <FormField
                                                    label="Valor (R$)"
                                                    name="amount"
                                                    error={errors.amount_cents}
                                                >
                                                    <div className="relative">
                                                        <span className="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                                                            R$
                                                        </span>
                                                        <Input
                                                            id="edit_amount_input"
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            required
                                                            value={editAmount}
                                                            onChange={(e) =>
                                                                setEditAmount(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="pl-10"
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="amount_cents"
                                                            value={Math.round(
                                                                (parseFloat(
                                                                    editAmount,
                                                                ) || 0) * 100,
                                                            )}
                                                        />
                                                    </div>
                                                </FormField>
                                            </div>

                                            <div>
                                                <FormField
                                                    label="Data de Vencimento"
                                                    name="due_date"
                                                    error={errors.due_date}
                                                >
                                                    <Input
                                                        id="edit_due_date"
                                                        name="due_date"
                                                        type="date"
                                                        required
                                                        defaultValue={
                                                            editingObligation.due_date
                                                        }
                                                    />
                                                </FormField>
                                            </div>

                                            <div>
                                                <FormField
                                                    label="Categoria"
                                                    name="category_id"
                                                    error={errors.category_id}
                                                >
                                                    <RemoteOptionPicker
                                                        id="edit_category_id"
                                                        name="category_id"
                                                        options={categories}
                                                        selectedOption={
                                                            editingObligation.category ??
                                                            undefined
                                                        }
                                                        placeholder="Selecione uma categoria (opcional)"
                                                        resource="categories"
                                                        value={
                                                            editingObligation.category_id ||
                                                            ''
                                                        }
                                                        onChange={(value) =>
                                                            setEditingObligation(
                                                                {
                                                                    ...editingObligation,
                                                                    category_id:
                                                                        value ||
                                                                        null,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>

                                            {editingObligation.type ===
                                            'payable' ? (
                                                <div>
                                                    <FormField
                                                        label="Fornecedor"
                                                        name="supplier_id"
                                                        error={
                                                            errors.supplier_id
                                                        }
                                                    >
                                                        <RemoteOptionPicker
                                                            id="edit_supplier_id"
                                                            name="supplier_id"
                                                            options={suppliers}
                                                            selectedOption={
                                                                editingObligation.supplier ??
                                                                undefined
                                                            }
                                                            placeholder="Selecione o fornecedor (opcional)"
                                                            resource="suppliers"
                                                            value={
                                                                editingObligation.supplier_id ||
                                                                ''
                                                            }
                                                            onChange={(value) =>
                                                                setEditingObligation(
                                                                    {
                                                                        ...editingObligation,
                                                                        supplier_id:
                                                                            value ||
                                                                            null,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </FormField>
                                                </div>
                                            ) : (
                                                <div>
                                                    <CustomerPicker
                                                        label="Cliente"
                                                        name="customer_id"
                                                        id="edit_customer_id"
                                                        value={editCustomerId}
                                                        options={customersList}
                                                        selectedOption={
                                                            editingObligation?.customer ??
                                                            null
                                                        }
                                                        onChange={
                                                            setEditCustomerId
                                                        }
                                                        error={
                                                            errors.customer_id
                                                        }
                                                        helper="Opcional — pesquise por nome ou telefone"
                                                    />
                                                </div>
                                            )}

                                            <div className="sm:col-span-2">
                                                <FormField
                                                    label="Observações"
                                                    name="notes"
                                                    error={errors.notes}
                                                >
                                                    <Input
                                                        id="edit_notes"
                                                        name="notes"
                                                        defaultValue={
                                                            editingObligation.notes ||
                                                            ''
                                                        }
                                                    />
                                                </FormField>
                                            </div>
                                        </div>

                                        <FormActions
                                            onCancel={() =>
                                                setEditingObligation(null)
                                            }
                                            processing={processing}
                                            submitText="Salvar Alterações"
                                        />
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                )}

                {/* Settle Modal */}
                {settlingObligation && (
                    <Dialog
                        open={!!settlingObligation}
                        onOpenChange={(open) =>
                            !open && setSettlingObligation(null)
                        }
                    >
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Liquidar Lançamento</DialogTitle>
                                <DialogDescription>
                                    Confirme o pagamento/recebimento de{' '}
                                    <strong>
                                        {formatMoney(
                                            settlingObligation.amount_cents,
                                        )}
                                    </strong>{' '}
                                    ({settlingObligation.description}).
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                action={`/finance/transactions/${settlingObligation.id}/settle`}
                                method="post"
                                resetOnSuccess
                                onSuccess={() => setSettlingObligation(null)}
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormErrorSummary errors={errors} />
                                        <input
                                            type="hidden"
                                            name="lock_version"
                                            value={
                                                settlingObligation.lock_version
                                            }
                                        />

                                        <FormField
                                            label="Data do Pagamento / Recebimento"
                                            name="paid_date"
                                            error={errors.paid_date}
                                        >
                                            <Input
                                                id="settle_paid_date"
                                                name="paid_date"
                                                type="date"
                                                required
                                                defaultValue={todayStr}
                                            />
                                        </FormField>

                                        <FormField
                                            label="Forma de Pagamento"
                                            name="payment_method"
                                            error={errors.payment_method}
                                        >
                                            <select
                                                id="settle_payment_method"
                                                name="payment_method"
                                                required
                                                defaultValue="pix"
                                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                                            >
                                                {PAYMENT_METHODS.map((pm) => (
                                                    <option
                                                        key={pm.value}
                                                        value={pm.value}
                                                    >
                                                        {pm.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </FormField>

                                        <FormActions
                                            onCancel={() =>
                                                setSettlingObligation(null)
                                            }
                                            processing={processing}
                                            submitText="Confirmar Liquidação"
                                        />
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                )}

                {/* Cancel Modal */}
                {cancellingObligation && (
                    <Dialog
                        open={!!cancellingObligation}
                        onOpenChange={(open) =>
                            !open && setCancellingObligation(null)
                        }
                    >
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>
                                    Cancelar Lançamento Financeiro
                                </DialogTitle>
                                <DialogDescription>
                                    Tem certeza que deseja cancelar o lançamento{' '}
                                    <strong>
                                        "{cancellingObligation.description}"
                                    </strong>{' '}
                                    no valor de{' '}
                                    {formatMoney(
                                        cancellingObligation.amount_cents,
                                    )}
                                    ? Esta ação não pode ser desfeita.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                action={`/finance/transactions/${cancellingObligation.id}/cancel`}
                                method="post"
                                resetOnSuccess
                                onSuccess={() => setCancellingObligation(null)}
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormErrorSummary errors={errors} />
                                        <input
                                            type="hidden"
                                            name="lock_version"
                                            value={
                                                cancellingObligation.lock_version
                                            }
                                        />

                                        <FormActions
                                            onCancel={() =>
                                                setCancellingObligation(null)
                                            }
                                            processing={processing}
                                            submitText="Sim, Cancelar Lançamento"
                                        />
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                )}
            </PageCanvas>
        </>
    );
}
