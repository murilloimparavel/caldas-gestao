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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type {
    FinancialObligation,
    FinancialObligationType,
    SharedPageProps,
} from '@/types';

type Option = {
    id: string;
    name: string;
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
    categoryOptions,
    supplierOptions,
    customerOptions,
}: Props) {
    const { auth } = usePage<SharedPageProps>().props;
    const canManage = auth.permissions.includes('financial.manage');
    const canSettle = auth.permissions.includes('financial.settle');

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [createType, setCreateType] = useState<FinancialObligationType>('payable');
    const [createAmount, setCreateAmount] = useState('');
    const [createKey] = useState(() => createIdempotencyKey('create-obligation'));

    const [editingObligation, setEditingObligation] = useState<FinancialObligation | null>(null);
    const [editAmount, setEditAmount] = useState('');

    const [settlingObligation, setSettlingObligation] = useState<FinancialObligation | null>(null);
    const [cancellingObligation, setCancellingObligation] = useState<FinancialObligation | null>(null);

    // Search and filter state
    const [search, setSearch] = useState(filters.search || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [categoryIdFilter, setCategoryIdFilter] = useState(filters.category_id || '');
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
        setEditingObligation(item);
        setEditAmount((item.amount_cents / 100).toFixed(2));
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
                            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                                <DialogTrigger asChild>
                                    <Button className="w-full sm:w-auto gap-2">
                                        <Plus className="h-4 w-4" />
                                        Novo Lançamento
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle>Novo Lançamento Financeiro</DialogTitle>
                                        <DialogDescription>
                                            Cadastre uma conta a pagar ou a receber com data de vencimento.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="flex gap-2 p-1 bg-muted rounded-lg mb-4">
                                        <button
                                            type="button"
                                            onClick={() => setCreateType('payable')}
                                            className={`flex-1 py-2 px-3 text-sm font-medium rounded-md transition-colors flex items-center justify-center gap-2 ${
                                                createType === 'payable'
                                                    ? 'bg-background shadow-sm text-foreground'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            <ArrowDownRight className="h-4 w-4 text-rose-500" />
                                            Conta a Pagar (Despesa)
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setCreateType('receivable')}
                                            className={`flex-1 py-2 px-3 text-sm font-medium rounded-md transition-colors flex items-center justify-center gap-2 ${
                                                createType === 'receivable'
                                                    ? 'bg-background shadow-sm text-foreground'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            <ArrowUpRight className="h-4 w-4 text-emerald-500" />
                                            Conta a Receber (Receita)
                                        </button>
                                    </div>

                                    <Form
                                        action="/finance/transactions"
                                        method="post"
                                        headers={{ 'X-Idempotency-Key': createKey }}
                                        resetOnSuccess
                                        onSuccess={() => {
                                            setIsCreateOpen(false);
                                            setCreateAmount('');
                                        }}
                                        className="space-y-4"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <FormErrorSummary errors={errors} />
                                                <input type="hidden" name="type" value={createType} />

                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="sm:col-span-2">
                                                        <FormField label="Descrição" name="description" error={errors.description}>
                                                            <Input
                                                                id="description"
                                                                name="description"
                                                                required
                                                                placeholder={createType === 'payable' ? 'Ex.: Conta de Energia Elétrica' : 'Ex.: Pagamento de Pacote Corporativo'}
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField label="Valor (R$)" name="amount" error={errors.amount_cents}>
                                                            <div className="relative">
                                                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm">R$</span>
                                                                <Input
                                                                    id="amount_input"
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0.01"
                                                                    required
                                                                    value={createAmount}
                                                                    onChange={(e) => setCreateAmount(e.target.value)}
                                                                    className="pl-10"
                                                                    placeholder="0,00"
                                                                />
                                                                <input
                                                                    type="hidden"
                                                                    name="amount_cents"
                                                                    value={Math.round((parseFloat(createAmount) || 0) * 100)}
                                                                />
                                                            </div>
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField label="Data de Vencimento" name="due_date" error={errors.due_date}>
                                                            <Input
                                                                id="due_date"
                                                                name="due_date"
                                                                type="date"
                                                                required
                                                                defaultValue={todayStr}
                                                            />
                                                        </FormField>
                                                    </div>

                                                    <div>
                                                        <FormField label="Categoria" name="category_id" error={errors.category_id}>
                                                            <select
                                                                id="category_id"
                                                                name="category_id"
                                                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                                            >
                                                                <option value="">Selecione uma categoria (opcional)</option>
                                                                {categoryOptions.map((cat) => (
                                                                    <option key={cat.id} value={cat.id}>
                                                                        {cat.name}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </FormField>
                                                    </div>

                                                    {createType === 'payable' ? (
                                                        <div>
                                                            <FormField label="Fornecedor" name="supplier_id" error={errors.supplier_id}>
                                                                <select
                                                                    id="supplier_id"
                                                                    name="supplier_id"
                                                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                                                >
                                                                    <option value="">Selecione o fornecedor (opcional)</option>
                                                                    {supplierOptions.map((sup) => (
                                                                        <option key={sup.id} value={sup.id}>
                                                                            {sup.name}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </FormField>
                                                        </div>
                                                    ) : (
                                                        <div>
                                                            <FormField label="Cliente" name="customer_id" error={errors.customer_id}>
                                                                <select
                                                                    id="customer_id"
                                                                    name="customer_id"
                                                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                                                >
                                                                    <option value="">Selecione o cliente (opcional)</option>
                                                                    {customerOptions.map((cus) => (
                                                                        <option key={cus.id} value={cus.id}>
                                                                            {cus.name}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </FormField>
                                                        </div>
                                                    )}

                                                    <div className="sm:col-span-2">
                                                        <FormField label="Observações" name="notes" error={errors.notes}>
                                                            <Input
                                                                id="notes"
                                                                name="notes"
                                                                placeholder="Detalhes ou anotações adicionais..."
                                                            />
                                                        </FormField>
                                                    </div>
                                                </div>

                                                <FormActions
                                                    onCancel={() => setIsCreateOpen(false)}
                                                    processing={processing}
                                                    submitText="Criar Lançamento"
                                                />
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        ) : null
                    }
                />

                {/* Metrics Banner */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="border-emerald-500/20 bg-emerald-500/5">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">A Receber (Pendente)</p>
                                <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                                    {formatMoney(metrics.total_receivable_pending_cents)}
                                </p>
                            </div>
                            <div className="h-10 w-10 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-600">
                                <ArrowUpRight className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-rose-500/20 bg-rose-500/5">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">A Pagar (Pendente)</p>
                                <p className="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">
                                    {formatMoney(metrics.total_payable_pending_cents)}
                                </p>
                            </div>
                            <div className="h-10 w-10 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-600">
                                <ArrowDownRight className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-amber-500/20 bg-amber-500/5">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Vencido</p>
                                <p className="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                                    {formatMoney(metrics.total_overdue_cents)}
                                </p>
                            </div>
                            <div className="h-10 w-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-600">
                                <AlertCircle className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sky-500/20 bg-sky-500/5">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">Liquidado no Mês</p>
                                <p className="text-2xl font-bold text-sky-600 dark:text-sky-400 mt-1">
                                    {formatMoney(metrics.total_paid_month_cents)}
                                </p>
                            </div>
                            <div className="h-10 w-10 rounded-full bg-sky-500/10 flex items-center justify-center text-sky-600">
                                <CheckCircle2 className="h-5 w-5" />
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
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        typeFilter === '' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Todos
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleTypeChange('payable')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        typeFilter === 'payable' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    A Pagar
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleTypeChange('receivable')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        typeFilter === 'receivable' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    A Receber
                                </button>
                            </div>

                            <div className="flex rounded-lg border bg-muted p-1 text-xs">
                                <button
                                    type="button"
                                    onClick={() => handleStatusChange('')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        statusFilter === '' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Todos Status
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusChange('pending')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        statusFilter === 'pending' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Pendentes
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusChange('overdue')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        statusFilter === 'overdue' ? 'bg-background shadow-xs text-rose-600 font-semibold' : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Vencidos
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusChange('paid')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        statusFilter === 'paid' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Liquidados
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleStatusChange('cancelled')}
                                    className={`px-3 py-1.5 rounded-md font-medium transition-colors ${
                                        statusFilter === 'cancelled' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Cancelados
                                </button>
                            </div>
                        </div>

                        <form onSubmit={handleSearchSubmit} className="flex flex-1 min-w-[240px] max-w-md items-center gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
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

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t text-xs">
                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">Categoria</label>
                            <select
                                value={categoryIdFilter}
                                onChange={(e) => {
                                    setCategoryIdFilter(e.target.value);
                                    applyFilters({ category_id: e.target.value });
                                }}
                                className="w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring"
                            >
                                <option value="">Todas as categorias</option>
                                {categoryOptions.map((cat) => (
                                    <option key={cat.id} value={cat.id}>
                                        {cat.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">Data De</label>
                            <Input
                                type="date"
                                value={dateStart}
                                onChange={(e) => {
                                    setDateStart(e.target.value);
                                    applyFilters({ date_start: e.target.value });
                                }}
                                className="h-8 text-xs"
                            />
                        </div>
                        <div>
                            <label className="block font-medium text-muted-foreground mb-1">Data Até</label>
                            <Input
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
                <div className="rounded-xl border bg-card shadow-sm overflow-hidden">
                    {obligations.data.length === 0 ? (
                        <EmptyState
                            icon={ArrowLeftRight}
                            title="Nenhum lançamento encontrado"
                            description="Não há contas a pagar ou receber correspondentes aos filtros selecionados."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/40 text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">Tipo</th>
                                        <th className="px-4 py-3">Descrição / Contato</th>
                                        <th className="px-4 py-3">Categoria</th>
                                        <th className="px-4 py-3">Vencimento</th>
                                        <th className="px-4 py-3">Valor</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {obligations.data.map((item) => {
                                        const overdue = isOverdue(item);

                                        return (
                                            <tr key={item.id} className="hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {item.type === 'payable' ? (
                                                        <Badge variant="outline" className="border-rose-500/30 text-rose-600 bg-rose-50 dark:bg-rose-950/30 gap-1 font-medium">
                                                            <ArrowDownRight className="h-3 w-3" />
                                                            A Pagar
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline" className="border-emerald-500/30 text-emerald-600 bg-emerald-50 dark:bg-emerald-950/30 gap-1 font-medium">
                                                            <ArrowUpRight className="h-3 w-3" />
                                                            A Receber
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium text-foreground">{item.description}</p>
                                                    {item.supplier && (
                                                        <p className="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                                            <Users className="h-3 w-3" />
                                                            Fornecedor: {item.supplier.name}
                                                        </p>
                                                    )}
                                                    {item.customer && (
                                                        <p className="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                                            <User className="h-3 w-3" />
                                                            Cliente: {item.customer.name}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                                    {item.category?.name || '—'}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <div className="flex items-center gap-1.5">
                                                        <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
                                                        <span className={overdue ? 'text-rose-600 font-semibold' : 'text-foreground'}>
                                                            {new Date(item.due_date + 'T00:00:00').toLocaleDateString('pt-BR')}
                                                        </span>
                                                        {overdue && (
                                                            <Badge variant="destructive" className="text-[10px] px-1.5 py-0">
                                                                Vencido
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap font-semibold">
                                                    <span className={item.type === 'payable' ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}>
                                                        {item.type === 'payable' ? '- ' : '+ '}
                                                        {formatMoney(item.amount_cents)}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {item.status === 'paid' && (
                                                        <div className="flex flex-col gap-0.5">
                                                            <Badge variant="secondary" className="bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 w-fit gap-1">
                                                                <CheckCircle2 className="h-3 w-3" />
                                                                Liquidado
                                                            </Badge>
                                                            {item.paid_date && (
                                                                <span className="text-[11px] text-muted-foreground">
                                                                    {new Date(item.paid_date + 'T00:00:00').toLocaleDateString('pt-BR')} ({item.payment_method || '—'})
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}
                                                    {item.status === 'pending' && (
                                                        <Badge variant="outline" className="border-amber-500/40 text-amber-600 bg-amber-50 dark:bg-amber-950/20 gap-1">
                                                            <Clock className="h-3 w-3" />
                                                            Pendente
                                                        </Badge>
                                                    )}
                                                    {item.status === 'cancelled' && (
                                                        <Badge variant="secondary" className="text-muted-foreground bg-muted gap-1">
                                                            <Ban className="h-3 w-3" />
                                                            Cancelado
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-right">
                                                    {item.status === 'pending' && (
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            {canSettle && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="h-8 border-emerald-600/30 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-950/50"
                                                                    onClick={() => setSettlingObligation(item)}
                                                                >
                                                                    <CheckCircle2 className="h-3.5 w-3.5 mr-1" />
                                                                    Liquidar
                                                                </Button>
                                                            )}
                                                            {canManage && (
                                                                <>
                                                                    <Button
                                                                        size="icon"
                                                                        variant="ghost"
                                                                        className="h-8 w-8"
                                                                        onClick={() => openEdit(item)}
                                                                        title="Editar Lançamento"
                                                                    >
                                                                        <Edit3 className="h-3.5 w-3.5" />
                                                                    </Button>
                                                                    <Button
                                                                        size="icon"
                                                                        variant="ghost"
                                                                        className="h-8 w-8 text-rose-500 hover:bg-rose-50 hover:text-rose-600"
                                                                        onClick={() => setCancellingObligation(item)}
                                                                        title="Cancelar Lançamento"
                                                                    >
                                                                        <Ban className="h-3.5 w-3.5" />
                                                                    </Button>
                                                                </>
                                                            )}
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {obligations.links && obligations.links.length > 3 && (
                        <div className="p-4 border-t">
                            <Pagination links={obligations.links} />
                        </div>
                    )}
                </div>

                {/* Edit Modal */}
                {editingObligation && (
                    <Dialog open={!!editingObligation} onOpenChange={(open) => !open && setEditingObligation(null)}>
                        <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Editar Lançamento Financeiro</DialogTitle>
                                <DialogDescription>
                                    Atualize as informações do lançamento pendente.
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
                                        <input type="hidden" name="lock_version" value={editingObligation.lock_version} />
                                        <input type="hidden" name="type" value={editingObligation.type} />

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="sm:col-span-2">
                                                <FormField label="Descrição" name="description" error={errors.description}>
                                                    <Input
                                                        id="edit_description"
                                                        name="description"
                                                        required
                                                        defaultValue={editingObligation.description}
                                                    />
                                                </FormField>
                                            </div>

                                            <div>
                                                <FormField label="Valor (R$)" name="amount" error={errors.amount_cents}>
                                                    <div className="relative">
                                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm">R$</span>
                                                        <Input
                                                            id="edit_amount_input"
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            required
                                                            value={editAmount}
                                                            onChange={(e) => setEditAmount(e.target.value)}
                                                            className="pl-10"
                                                        />
                                                        <input
                                                            type="hidden"
                                                            name="amount_cents"
                                                            value={Math.round((parseFloat(editAmount) || 0) * 100)}
                                                        />
                                                    </div>
                                                </FormField>
                                            </div>

                                            <div>
                                                <FormField label="Data de Vencimento" name="due_date" error={errors.due_date}>
                                                    <Input
                                                        id="edit_due_date"
                                                        name="due_date"
                                                        type="date"
                                                        required
                                                        defaultValue={editingObligation.due_date}
                                                    />
                                                </FormField>
                                            </div>

                                            <div>
                                                <FormField label="Categoria" name="category_id" error={errors.category_id}>
                                                    <select
                                                        id="edit_category_id"
                                                        name="category_id"
                                                        defaultValue={editingObligation.category_id || ''}
                                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                                    >
                                                        <option value="">Selecione uma categoria (opcional)</option>
                                                        {categoryOptions.map((cat) => (
                                                            <option key={cat.id} value={cat.id}>
                                                                {cat.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </FormField>
                                            </div>

                                            {editingObligation.type === 'payable' ? (
                                                <div>
                                                    <FormField label="Fornecedor" name="supplier_id" error={errors.supplier_id}>
                                                        <select
                                                            id="edit_supplier_id"
                                                            name="supplier_id"
                                                            defaultValue={editingObligation.supplier_id || ''}
                                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                                        >
                                                            <option value="">Selecione o fornecedor (opcional)</option>
                                                            {supplierOptions.map((sup) => (
                                                                <option key={sup.id} value={sup.id}>
                                                                    {sup.name}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </FormField>
                                                </div>
                                            ) : (
                                                <div>
                                                    <FormField label="Cliente" name="customer_id" error={errors.customer_id}>
                                                        <select
                                                            id="edit_customer_id"
                                                            name="customer_id"
                                                            defaultValue={editingObligation.customer_id || ''}
                                                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                                        >
                                                            <option value="">Selecione o cliente (opcional)</option>
                                                            {customerOptions.map((cus) => (
                                                                <option key={cus.id} value={cus.id}>
                                                                    {cus.name}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </FormField>
                                                </div>
                                            )}

                                            <div className="sm:col-span-2">
                                                <FormField label="Observações" name="notes" error={errors.notes}>
                                                    <Input
                                                        id="edit_notes"
                                                        name="notes"
                                                        defaultValue={editingObligation.notes || ''}
                                                    />
                                                </FormField>
                                            </div>
                                        </div>

                                        <FormActions
                                            onCancel={() => setEditingObligation(null)}
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
                    <Dialog open={!!settlingObligation} onOpenChange={(open) => !open && setSettlingObligation(null)}>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Liquidar Lançamento</DialogTitle>
                                <DialogDescription>
                                    Confirme o pagamento/recebimento de <strong>{formatMoney(settlingObligation.amount_cents)}</strong> ({settlingObligation.description}).
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
                                        <input type="hidden" name="lock_version" value={settlingObligation.lock_version} />

                                        <FormField label="Data do Pagamento / Recebimento" name="paid_date" error={errors.paid_date}>
                                            <Input
                                                id="settle_paid_date"
                                                name="paid_date"
                                                type="date"
                                                required
                                                defaultValue={todayStr}
                                            />
                                        </FormField>

                                        <FormField label="Forma de Pagamento" name="payment_method" error={errors.payment_method}>
                                            <select
                                                id="settle_payment_method"
                                                name="payment_method"
                                                required
                                                defaultValue="pix"
                                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                            >
                                                {PAYMENT_METHODS.map((pm) => (
                                                    <option key={pm.value} value={pm.value}>
                                                        {pm.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </FormField>

                                        <FormActions
                                            onCancel={() => setSettlingObligation(null)}
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
                    <Dialog open={!!cancellingObligation} onOpenChange={(open) => !open && setCancellingObligation(null)}>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Cancelar Lançamento Financeiro</DialogTitle>
                                <DialogDescription>
                                    Tem certeza que deseja cancelar o lançamento <strong>"{cancellingObligation.description}"</strong> no valor de {formatMoney(cancellingObligation.amount_cents)}? Esta ação não pode ser desfeita.
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
                                        <input type="hidden" name="lock_version" value={cancellingObligation.lock_version} />

                                        <FormActions
                                            onCancel={() => setCancellingObligation(null)}
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
