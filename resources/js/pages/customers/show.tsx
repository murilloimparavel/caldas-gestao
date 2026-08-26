import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    CalendarDays,
    Clock,
    ExternalLink,
    Gift,
    History,
    Mail,
    Package,
    Phone,
    Receipt,
    RefreshCw,
    Scissors,
    Sparkles,
    TrendingUp,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { statusLabels } from '@/components/calendar';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    ResourceHeader,
    StatusBadge,
} from '@/components/operational';
import type { ResourceStatus } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { index as calendarIndex } from '@/routes/calendar';
import customerPackagesRoutes from '@/routes/customer-packages';
import customerSubscriptionsRoutes from '@/routes/customer-subscriptions';
import customers from '@/routes/customers';
import sales from '@/routes/sales';
import type { SharedPageProps } from '@/types';

type CustomerAppointment = {
    ends_at: string;
    id: string;
    notes?: string | null;
    professional?: { id: string; name: string } | null;
    starts_at: string;
    status: string;
};

type CustomerSaleItem = {
    discount_cents: number;
    id: string;
    item_type: 'service' | 'product' | 'custom';
    name_snapshot: string;
    product_id?: string | null;
    professional_id?: string | null;
    quantity: number;
    service_id?: string | null;
    total_cents: number;
    unit_price_cents: number;
};

type CustomerSale = {
    category_name_snapshot?: string | null;
    created_at: string;
    discount_amount_cents: number;
    final_amount_cents: number;
    id: string;
    items?: CustomerSaleItem[];
    notes?: string | null;
    reference_label?: string | null;
    sale_category?: { id: string; name: string } | null;
    status: 'draft' | 'open' | 'ready_to_bill' | 'finalized' | 'cancelled';
    total_amount_cents: number;
};

type CustomerPackageUsage = {
    created_at: string;
    id: string;
    sessions_consumed: number;
    user?: { id: string; name: string } | null;
};

type CustomerPackageItem = {
    created_at: string;
    expires_at: string | null;
    id: string;
    lock_version: number;
    package_template?: {
        description?: string | null;
        id: string;
        name: string;
        price_cents: number;
        services?: Array<{ id: string; name: string }>;
        total_sessions: number;
        validity_days: number;
    } | null;
    package_template_id: string;
    remaining_sessions: number;
    status: ResourceStatus;
    total_sessions: number;
    usages?: CustomerPackageUsage[];
};

type PackageTemplateOption = {
    description?: string | null;
    id: string;
    name: string;
    price_cents: number;
    services?: Array<{ id: string; name: string }>;
    total_sessions: number;
    validity_days: number;
};

type ActiveSubscription = {
    billing_cycle?: string;
    cancelled_at: string | null;
    id: string;
    lock_version: number;
    next_billing_date: string | null;
    plan: {
        billing_cycle: string;
        id: string;
        name: string;
        price_cents: number;
    };
    price_cents?: number;
    start_date: string;
    status: 'active' | 'paused' | 'cancelled' | 'expired';
};

type SubscriptionPlanOption = {
    billing_cycle: string;
    id: string;
    name: string;
    price_cents: number;
};

type Customer = {
    appointments?: CustomerAppointment[];
    birth_date: string | null;
    created_at?: string;
    customerPackages?: CustomerPackageItem[];
    email: string | null;
    id: string;
    lock_version: number;
    name: string;
    notes: string | null;
    phone: string | null;
    sales?: CustomerSale[];
    status: ResourceStatus;
};

type Props = {
    active_subscription?: ActiveSubscription | null;
    customer: Customer;
    metrics?: {
        total_spent_cents: number;
        total_visits: number;
    };
    packageTemplates?: PackageTemplateOption[];
    planOptions?: SubscriptionPlanOption[];
    subscription_history?: ActiveSubscription[];
};


function formatAppointmentDate(isoString: string): string {
    const date = new Date(isoString);

    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function formatAppointmentTime(isoString: string): string {
    const date = new Date(isoString);

    return date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatSaleDateTime(isoString: string): string {
    const date = new Date(isoString);

    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function SaleStatusBadge({ status }: { status: CustomerSale['status'] }) {
    switch (status) {
        case 'open':
            return (
                <Badge
                    variant="outline"
                    className="border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300"
                >
                    Aberta
                </Badge>
            );
        case 'ready_to_bill':
            return (
                <Badge
                    variant="outline"
                    className="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                >
                    Pronta p/ Fechar
                </Badge>
            );
        case 'finalized':
            return (
                <Badge
                    variant="outline"
                    className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                >
                    Finalizada
                </Badge>
            );
        case 'cancelled':
            return (
                <Badge variant="outline" className="border-border bg-muted text-muted-foreground">
                    Cancelada
                </Badge>
            );
        default:
            return (
                <Badge variant="outline" className="border-border bg-muted text-muted-foreground">
                    Rascunho
                </Badge>
            );
    }
}

function ItemTypeBadge({ type }: { type: CustomerSaleItem['item_type'] }) {
    switch (type) {
        case 'service':
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300 text-[11px] py-0"
                >
                    <Scissors className="size-3" /> Serviço
                </Badge>
            );
        case 'product':
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-950/40 dark:text-purple-300 text-[11px] py-0"
                >
                    <Package className="size-3" /> Produto
                </Badge>
            );
        default:
            return (
                <Badge
                    variant="outline"
                    className="gap-1 border-border bg-muted text-muted-foreground text-[11px] py-0"
                >
                    <Sparkles className="size-3" /> Item
                </Badge>
            );
    }
}

export default function CustomerShow({
    customer,
    metrics,
    packageTemplates = [],
    active_subscription = null,
    subscription_history = [],
    planOptions = [],
}: Props) {
    const [activeTab, setActiveTab] = useState<
        'sales' | 'appointments' | 'packages' | 'details' | 'subscriptions'
    >('sales');
    const [updateKey] = useState(() => createIdempotencyKey('customer-update'));
    const [destroyKey] = useState(() =>
        createIdempotencyKey('customer-destroy'),
    );
    const [reactivateKey] = useState(() =>
        createIdempotencyKey('customer-reactivate'),
    );
    const [sellKey] = useState(() =>
        createIdempotencyKey('customer-package-sell'),
    );
    const [consumeKey] = useState(() =>
        createIdempotencyKey('customer-package-consume'),
    );
    const [inactivateOpen, setInactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);
    const [sellPackageOpen, setSellPackageOpen] = useState(false);
    const [consumePackageOpen, setConsumePackageOpen] = useState(false);
    const [selectedPackageForConsume, setSelectedPackageForConsume] =
        useState<CustomerPackageItem | null>(null);

    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('customer.manage');
    const canSellPackage =
        props.auth.permissions.includes('package.sell') ||
        props.auth.permissions.includes('package.manage');
    const canConsumePackage =
        props.auth.permissions.includes('package.consume') ||
        props.auth.permissions.includes('package.manage');
    const canSubscribe = props.auth.permissions.includes('subscription.subscribe');
    const canViewSub = props.auth.permissions.includes('subscription.view');
    const canCancelSub = props.auth.permissions.includes('subscription.cancel');
    const canManageSub = props.auth.permissions.includes('subscription.manage');
    const [subscribeKey] = useState(() => createIdempotencyKey('customer-subscribe'));
    const appointments = customer.appointments ?? [];
    const salesList = customer.sales ?? [];
    const customerPackages = customer.customerPackages ?? [];

    const totalSpentCents =
        metrics?.total_spent_cents ??
        salesList
            .filter((s) => s.status === 'finalized')
            .reduce((acc, s) => acc + (s.final_amount_cents || 0), 0);

    const totalVisits = metrics?.total_visits ?? appointments.length;

    return (
        <>
            <Head title={customer.name} />
            <PageCanvas>
                <div>
                    <Button asChild variant="ghost" className="mb-4 -ml-3">
                        <Link href={customers.index()}>
                            <ArrowLeft aria-hidden="true" />
                            Voltar para clientes
                        </Link>
                    </Button>
                    <ResourceHeader
                        eyebrow="Cadastro de cliente"
                        title={customer.name}
                        description="Histórico completo de visitas, consumo, comandas e dados cadastrais."
                        action={
                            <div className="flex items-center gap-2">
                                <StatusBadge status={customer.status} />
                                {active_subscription?.status === 'active' && (
                                    <Badge className="gap-1 bg-emerald-500 text-white hover:bg-emerald-600">
                                        <RefreshCw className="h-3 w-3" />
                                        Assinante
                                    </Badge>
                                )}
                            </div>
                        }
                    />
                </div>

                {/* Métricas de Consumo e Fidelidade */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="surface-panel flex items-center gap-4 p-4 sm:p-5">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                            <TrendingUp className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Total Gasto
                            </p>
                            <p className="mt-0.5 text-xl font-bold tracking-tight text-foreground">
                                {formatMoney(totalSpentCents)}
                            </p>
                        </div>
                    </div>

                    <div className="surface-panel flex items-center gap-4 p-4 sm:p-5">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400">
                            <CalendarDays className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Total de Visitas
                            </p>
                            <p className="mt-0.5 text-xl font-bold tracking-tight text-foreground">
                                {totalVisits} {totalVisits === 1 ? 'visita' : 'visitas'}
                            </p>
                        </div>
                    </div>

                    <div className="surface-panel flex items-center gap-4 p-4 sm:p-5">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-purple-500/10 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400">
                            <Receipt className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Comandas
                            </p>
                            <p className="mt-0.5 text-xl font-bold tracking-tight text-foreground">
                                {salesList.length} {salesList.length === 1 ? 'comanda' : 'comandas'}
                            </p>
                        </div>
                    </div>

                    <div className="surface-panel flex items-center gap-4 p-4 sm:p-5">
                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400">
                            <Calendar className="size-5" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                Agendamentos
                            </p>
                            <p className="mt-0.5 text-xl font-bold tracking-tight text-foreground">
                                {appointments.length} {appointments.length === 1 ? 'registro' : 'registros'}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <div className="space-y-5">
                        {/* Abas de Navegação */}
                        <div className="flex border-b border-border space-x-1 sm:space-x-2">
                            <button
                                type="button"
                                onClick={() => setActiveTab('sales')}
                                className={`flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors sm:px-4 ${
                                    activeTab === 'sales'
                                        ? 'border-primary text-primary font-semibold'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <Receipt className="size-4" />
                                <span>Histórico de Comandas & Consumo</span>
                                <Badge variant="secondary" className="ml-1 text-xs">
                                    {salesList.length}
                                </Badge>
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('appointments')}
                                className={`flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors sm:px-4 ${
                                    activeTab === 'appointments'
                                        ? 'border-primary text-primary font-semibold'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <Calendar className="size-4" />
                                <span>Agendamentos</span>
                                <Badge variant="secondary" className="ml-1 text-xs">
                                    {appointments.length}
                                </Badge>
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('packages')}
                                className={`flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors sm:px-4 ${
                                    activeTab === 'packages'
                                        ? 'border-primary text-primary font-semibold'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <Gift className="size-4" />
                                <span>Pacotes de Serviços</span>
                                <Badge variant="secondary" className="ml-1 text-xs">
                                    {customerPackages.length}
                                </Badge>
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('details')}
                                className={`flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors sm:px-4 ${
                                    activeTab === 'details'
                                        ? 'border-primary text-primary font-semibold'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <UserRound className="size-4" />
                                <span>Dados Cadastrais</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab('subscriptions')}
                                className={`flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors sm:px-4 ${
                                    activeTab === 'subscriptions'
                                        ? 'border-primary text-primary font-semibold'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <RefreshCw className="size-4" />
                                <span>Assinaturas</span>
                                {active_subscription?.status === 'active' && (
                                    <Badge variant="default" className="ml-1 text-xs bg-emerald-500">
                                        Ativo
                                    </Badge>
                                )}
                            </button>
                        </div>

                        {/* Aba: Histórico de Comandas & Consumo */}
                        {activeTab === 'sales' && (
                            <section className="surface-panel p-5 sm:p-6 space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-1">
                                        <h2 className="text-base font-semibold">
                                            Histórico de Comandas & Consumo
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Todas as comandas, serviços prestados e produtos adquiridos por este cliente.
                                        </p>
                                    </div>
                                </div>

                                {salesList.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                                        <Receipt className="mx-auto size-8 text-muted-foreground/50 mb-2" />
                                        Nenhuma comanda registrada para este cliente até o momento.
                                    </div>
                                ) : (
                                    <div className="divide-y divide-border overflow-hidden rounded-lg border border-border">
                                        {salesList.map((sale) => (
                                            <div
                                                key={sale.id}
                                                className="p-4 transition-colors hover:bg-muted/20 space-y-3"
                                            >
                                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2.5 flex-wrap">
                                                            <span className="font-semibold text-foreground">
                                                                {sale.reference_label || `Comanda #${sale.id.slice(0, 8).toUpperCase()}`}
                                                            </span>
                                                            <SaleStatusBadge status={sale.status} />
                                                            {(sale.category_name_snapshot || sale.sale_category?.name) && (
                                                                <Badge variant="outline" className="text-xs">
                                                                    {sale.category_name_snapshot || sale.sale_category?.name}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                            <Clock className="size-3.5" />
                                                            <span>{formatSaleDateTime(sale.created_at)}</span>
                                                        </div>
                                                    </div>

                                                    <div className="flex items-center gap-3">
                                                        <div className="text-right">
                                                            <p className="text-sm font-bold text-foreground">
                                                                {formatMoney(sale.final_amount_cents)}
                                                            </p>
                                                            {sale.discount_amount_cents > 0 && (
                                                                <p className="text-[11px] text-emerald-600 dark:text-emerald-400">
                                                                    Desc. {formatMoney(sale.discount_amount_cents)}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                            className="gap-1.5"
                                                        >
                                                            <Link href={sales.show(sale.id)}>
                                                                Ver comanda
                                                                <ExternalLink className="size-3.5" />
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                </div>

                                                {/* Itens da Comanda */}
                                                {sale.items && sale.items.length > 0 && (
                                                    <div className="mt-2 rounded-md bg-muted/40 p-3 space-y-2 border border-border/50">
                                                        <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                                            Itens consumidos ({sale.items.length})
                                                        </p>
                                                        <div className="grid gap-1.5">
                                                            {sale.items.map((item) => (
                                                                <div
                                                                    key={item.id}
                                                                    className="flex items-center justify-between text-xs text-muted-foreground"
                                                                >
                                                                    <div className="flex items-center gap-2 min-w-0">
                                                                        <ItemTypeBadge type={item.item_type} />
                                                                        <span className="font-medium text-foreground truncate">
                                                                            {item.name_snapshot}
                                                                        </span>
                                                                        <span>
                                                                            ({item.quantity}x {formatMoney(item.unit_price_cents)})
                                                                        </span>
                                                                    </div>
                                                                    <span className="font-semibold text-foreground shrink-0">
                                                                        {formatMoney(item.total_cents)}
                                                                    </span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </section>
                        )}

                        {/* Aba: Histórico de Agendamentos */}
                        {activeTab === 'appointments' && (
                            <section className="surface-panel p-5 sm:p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <div className="space-y-1">
                                        <h2 className="text-base font-semibold">
                                            Histórico de Agendamentos
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Atendimentos recentes e agendamentos deste cliente.
                                        </p>
                                    </div>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={calendarIndex()}>
                                            <Calendar className="size-4" />
                                            Abrir Agenda
                                        </Link>
                                    </Button>
                                </div>

                                {appointments.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                                        Nenhum agendamento registrado até o momento.
                                    </div>
                                ) : (
                                    <div className="divide-y divide-border overflow-hidden rounded-lg border border-border">
                                        {appointments.map((apt) => (
                                            <div
                                                key={apt.id}
                                                className="flex flex-col gap-2 p-4 transition-colors hover:bg-muted/30 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium text-foreground">
                                                            {formatAppointmentDate(apt.starts_at)}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatAppointmentTime(apt.starts_at)} - {formatAppointmentTime(apt.ends_at)}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <UserRound className="size-3.5" />
                                                        <span>
                                                            Profissional: {apt.professional?.name || 'Não informado'}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <Badge variant="outline">
                                                        {statusLabels[apt.status] || apt.status}
                                                    </Badge>
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                        className="size-8 p-0"
                                                    >
                                                        <Link
                                                            href={calendarIndex({
                                                                query: {
                                                                    date: apt.starts_at.slice(0, 10),
                                                                },
                                                            })}
                                                            title="Ver na agenda"
                                                        >
                                                            <ExternalLink className="size-4" />
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </section>
                        )}

                        {/* Aba: Pacotes de Serviços */}
                        {activeTab === 'packages' && (
                            <section className="surface-panel p-5 sm:p-6 space-y-4">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div className="space-y-1">
                                        <h2 className="text-base font-semibold">
                                            Pacotes de Serviços
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            Sessões pré-pagas, validades e histórico de utilização de pacotes.
                                        </p>
                                    </div>
                                    {canSellPackage && packageTemplates.length > 0 && (
                                        <Dialog open={sellPackageOpen} onOpenChange={setSellPackageOpen}>
                                            <DialogTrigger asChild>
                                                <Button size="sm">
                                                    <Gift className="mr-2 h-4 w-4" />
                                                    Vender / Adicionar Pacote
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                                                <DialogHeader>
                                                    <DialogTitle>Vender Pacote para {customer.name}</DialogTitle>
                                                    <DialogDescription>
                                                        Selecione o modelo do pacote para atribuir as sessões e calcular a validade.
                                                    </DialogDescription>
                                                </DialogHeader>

                                                <Form
                                                    method="post"
                                                    action={customerPackagesRoutes.store().url}
                                                    headers={{ 'X-Idempotency-Key': sellKey }}
                                                    onSuccess={() => setSellPackageOpen(false)}
                                                    className="space-y-4"
                                                >
                                                    {({ processing, errors }) => (
                                                        <>
                                                            <FormErrorSummary errors={errors} />
                                                            <input type="hidden" name="customer_id" value={customer.id} />

                                                            <FormField
                                                                id="package_template_id"
                                                                label="Modelo de Pacote"
                                                                required
                                                                error={errors.package_template_id}
                                                            >
                                                                <select
                                                                    id="package_template_id"
                                                                    name="package_template_id"
                                                                    required
                                                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                                                    defaultValue=""
                                                                >
                                                                    <option value="" disabled>Selecione um pacote...</option>
                                                                    {packageTemplates.map((tmpl) => (
                                                                        <option key={tmpl.id} value={tmpl.id}>
                                                                            {tmpl.name} ({tmpl.total_sessions} sessões - {formatMoney(tmpl.price_cents)})
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </FormField>

                                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                                <FormField
                                                                    id="total_sessions"
                                                                    label="Sessões (opcional)"
                                                                    error={errors.total_sessions}
                                                                >
                                                                    <Input
                                                                        id="total_sessions"
                                                                        name="total_sessions"
                                                                        type="number"
                                                                        min="1"
                                                                        placeholder="Padrão do modelo"
                                                                    />
                                                                </FormField>

                                                                <FormField
                                                                    id="expires_at"
                                                                    label="Validade personalizada (opcional)"
                                                                    error={errors.expires_at}
                                                                >
                                                                    <Input
                                                                        id="expires_at"
                                                                        name="expires_at"
                                                                        type="date"
                                                                    />
                                                                </FormField>
                                                            </div>

                                                            <FormActions
                                                                cancelLabel="Cancelar"
                                                                onCancel={() => setSellPackageOpen(false)}
                                                                submitLabel="Confirmar Venda"
                                                                submitting={processing}
                                                            />
                                                        </>
                                                    )}
                                                </Form>
                                            </DialogContent>
                                        </Dialog>
                                    )}
                                </div>

                                {customerPackages.length === 0 ? (
                                    <div className="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                                        <Gift className="mx-auto size-8 text-muted-foreground/50 mb-2" />
                                        Nenhum pacote contratado por este cliente até o momento.
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {customerPackages.map((cp) => (
                                            <div
                                                key={cp.id}
                                                className="rounded-xl border bg-card p-5 shadow-xs space-y-4"
                                            >
                                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <h3 className="font-semibold text-foreground text-base">
                                                                {cp.package_template?.name ?? 'Pacote de Serviços'}
                                                            </h3>
                                                            <StatusBadge status={cp.status} />
                                                        </div>
                                                        <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                            <span>Adquirido em {new Date(cp.created_at).toLocaleDateString('pt-BR')}</span>
                                                            {cp.expires_at ? (
                                                                <span className="font-medium text-foreground">
                                                                    Válido até {new Date(cp.expires_at).toLocaleDateString('pt-BR')}
                                                                </span>
                                                            ) : (
                                                                <span>Sem validade</span>
                                                            )}
                                                        </div>
                                                    </div>

                                                    <div className="flex items-center gap-3">
                                                        <div className="text-right">
                                                            <div className="text-lg font-bold text-foreground">
                                                                {cp.remaining_sessions} / {cp.total_sessions}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                sessões restantes
                                                            </div>
                                                        </div>

                                                        {canConsumePackage && cp.status === 'active' && cp.remaining_sessions > 0 && (
                                                            <Button
                                                                size="sm"
                                                                onClick={() => {
                                                                    setSelectedPackageForConsume(cp);
                                                                    setConsumePackageOpen(true);
                                                                }}
                                                            >
                                                                Consumir Sessão
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>

                                                {cp.package_template?.services && cp.package_template.services.length > 0 && (
                                                    <div className="flex flex-wrap gap-1.5 pt-2 border-t">
                                                        <span className="text-xs text-muted-foreground self-center mr-1">Serviços inclusos:</span>
                                                        {cp.package_template.services.map((srv) => (
                                                            <Badge key={srv.id} variant="secondary" className="text-xs">
                                                                <Scissors className="mr-1 h-3 w-3" />
                                                                {srv.name}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                )}

                                                {cp.usages && cp.usages.length > 0 && (
                                                    <div className="pt-2 border-t">
                                                        <p className="text-xs font-medium text-muted-foreground mb-1.5 flex items-center gap-1">
                                                            <History className="h-3 w-3" /> Utilizações:
                                                        </p>
                                                        <div className="space-y-1">
                                                            {cp.usages.map((u) => (
                                                                <div key={u.id} className="text-xs text-muted-foreground flex justify-between">
                                                                    <span>
                                                                        {u.sessions_consumed} {u.sessions_consumed === 1 ? 'sessão consumida' : 'sessões consumidas'}
                                                                        {u.user ? ` por ${u.user.name}` : ''}
                                                                    </span>
                                                                    <span>{new Date(u.created_at).toLocaleString('pt-BR')}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </section>
                        )}

                        {/* Aba: Dados Principais */}
                        {activeTab === 'subscriptions' && canViewSub && (
                            <section className="surface-panel space-y-5 p-5 sm:p-6">
                                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                    <div className="space-y-1">
                                        <h2 className="text-base font-semibold">Assinatura recorrente</h2>
                                        <p className="text-sm text-muted-foreground">Apenas uma assinatura ativa ou pausada pode existir por cliente nesta unidade.</p>
                                    </div>
                                    {!active_subscription && canSubscribe && planOptions.length > 0 && (
                                        <Form
                                            method="post"
                                            action={customerSubscriptionsRoutes.store().url}
                                            headers={{ 'X-Idempotency-Key': subscribeKey }}
                                            className="flex flex-col gap-2 sm:flex-row sm:items-end"
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <input type="hidden" name="customer_id" value={customer.id} />
                                                    <FormField id="subscription_plan_id" label="Plano" error={errors.subscription_plan_id}>
                                                        <select id="subscription_plan_id" name="subscription_plan_id" required className="flex h-10 min-w-56 rounded-md border border-input bg-background px-3 py-2 text-sm">
                                                            <option value="">Selecione um plano</option>
                                                            {planOptions.map((plan) => (
                                                                <option key={plan.id} value={plan.id}>{plan.name} — {formatMoney(plan.price_cents)}</option>
                                                            ))}
                                                        </select>
                                                    </FormField>
                                                    <Button type="submit" disabled={processing}>Contratar</Button>
                                                </>
                                            )}
                                        </Form>
                                    )}
                                </div>

                                {active_subscription ? (
                                    <div className="rounded-xl border bg-card p-5">
                                        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="font-semibold">{active_subscription.plan.name}</h3>
                                                    <StatusBadge status={active_subscription.status as ResourceStatus} />
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">{formatMoney(active_subscription.price_cents ?? active_subscription.plan.price_cents)} · {active_subscription.billing_cycle === 'yearly' ? 'Anual' : active_subscription.billing_cycle === 'quarterly' ? 'Trimestral' : 'Mensal'}</p>
                                                <p className="mt-2 text-xs text-muted-foreground">Início: {active_subscription.start_date}{active_subscription.next_billing_date ? ` · Próxima cobrança: ${active_subscription.next_billing_date}` : ''}</p>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {active_subscription.status === 'active' && canManageSub && (
                                                    <Form method="post" action={customerSubscriptionsRoutes.pause(active_subscription.id).url}>
                                                        <input type="hidden" name="lock_version" value={active_subscription.lock_version} />
                                                        <Button type="submit" variant="outline">Pausar</Button>
                                                    </Form>
                                                )}
                                                {active_subscription.status === 'paused' && canManageSub && (
                                                    <Form method="post" action={customerSubscriptionsRoutes.resume(active_subscription.id).url}>
                                                        <input type="hidden" name="lock_version" value={active_subscription.lock_version} />
                                                        <Button type="submit" variant="outline">Retomar</Button>
                                                    </Form>
                                                )}
                                                {canCancelSub && (
                                                    <Form method="post" action={customerSubscriptionsRoutes.cancel(active_subscription.id).url}>
                                                        <input type="hidden" name="lock_version" value={active_subscription.lock_version} />
                                                        <Button type="submit" variant="destructive">Cancelar</Button>
                                                    </Form>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">Nenhuma assinatura vigente.</div>
                                )}

                                {subscription_history.length > 0 && (
                                    <div className="space-y-3">
                                        <h3 className="text-sm font-semibold">Histórico</h3>
                                        <div className="divide-y rounded-lg border">
                                            {subscription_history.map((subscription) => (
                                                <div key={subscription.id} className="flex flex-col justify-between gap-1 px-4 py-3 text-sm sm:flex-row">
                                                    <span>{subscription.plan.name} · {formatMoney(subscription.price_cents ?? subscription.plan.price_cents)}</span>
                                                    <span className="text-muted-foreground">{subscription.status} · {subscription.start_date}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </section>
                        )}

                        {/* Aba: Dados Principais */}
                        {activeTab === 'details' && (
                            <section className="surface-panel p-5 sm:p-6">
                                <div className="mb-6 space-y-1">
                                    <h2 className="text-base font-semibold">
                                        Dados principais
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Apenas pessoas com cadastro ativo aparecem nas
                                        próximas escolhas operacionais.
                                    </p>
                                </div>
                                <Form
                                    {...customers.update.form(customer.id)}
                                    headers={{ 'X-Idempotency-Key': updateKey }}
                                    className="space-y-5"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <FormErrorSummary errors={errors} />
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="sm:col-span-2">
                                                    <FormField
                                                        label="Nome completo"
                                                        name="name"
                                                        error={errors.name}
                                                    >
                                                        <Input
                                                            id="name"
                                                            name="name"
                                                            defaultValue={customer.name}
                                                            required
                                                            disabled={!canManage}
                                                        />
                                                    </FormField>
                                                </div>
                                                <FormField
                                                    label="E-mail"
                                                    name="email"
                                                    error={errors.email}
                                                >
                                                    <Input
                                                        id="email"
                                                        name="email"
                                                        type="email"
                                                        defaultValue={
                                                            customer.email ?? ''
                                                        }
                                                        disabled={!canManage}
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Telefone"
                                                    name="phone"
                                                    error={errors.phone}
                                                >
                                                    <Input
                                                        id="phone"
                                                        name="phone"
                                                        inputMode="tel"
                                                        defaultValue={
                                                            customer.phone ?? ''
                                                        }
                                                        disabled={!canManage}
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Data de nascimento"
                                                    name="birth_date"
                                                    error={errors.birth_date}
                                                >
                                                    <Input
                                                        id="birth_date"
                                                        name="birth_date"
                                                        type="date"
                                                        defaultValue={
                                                            customer.birth_date?.slice(
                                                                0,
                                                                10,
                                                            ) ?? ''
                                                        }
                                                        disabled={!canManage}
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Status"
                                                    name="status"
                                                    error={errors.status}
                                                >
                                                    <select
                                                        id="status"
                                                        name="status"
                                                        defaultValue={customer.status}
                                                        disabled={!canManage}
                                                        className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                                    >
                                                        <option value="active">
                                                            Ativo
                                                        </option>
                                                        <option value="inactive">
                                                            Inativo
                                                        </option>
                                                    </select>
                                                </FormField>
                                                <div className="sm:col-span-2">
                                                    <FormField
                                                        label="Observações"
                                                        name="notes"
                                                        error={errors.notes}
                                                    >
                                                        <textarea
                                                            id="notes"
                                                            name="notes"
                                                            rows={4}
                                                            defaultValue={
                                                                customer.notes ?? ''
                                                            }
                                                            disabled={!canManage}
                                                            className="min-h-28 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                                        />
                                                    </FormField>
                                                </div>
                                            </div>
                                            {canManage ? (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="lock_version"
                                                        value={customer.lock_version}
                                                    />
                                                    <FormActions
                                                        processing={processing}
                                                        label="Salvar alterações"
                                                    />
                                                </>
                                            ) : (
                                                <p
                                                    className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
                                                    role="status"
                                                >
                                                    Você tem acesso somente para
                                                    consulta a este cadastro.
                                                </p>
                                            )}
                                        </>
                                    )}
                                </Form>
                            </section>
                        )}
                    </div>

                    <aside className="space-y-5">
                        <section className="surface-panel p-5 sm:p-6">
                            <h2 className="text-base font-semibold">
                                Resumo de contato
                            </h2>
                            <div className="mt-5 grid gap-4">
                                <div className="flex items-start gap-3">
                                    <Phone
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            Telefone
                                        </p>
                                        <p className="mt-0.5 truncate text-sm font-medium">
                                            {customer.phone || 'Não informado'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <Mail
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            E-mail
                                        </p>
                                        <p className="mt-0.5 truncate text-sm font-medium">
                                            {customer.email || 'Não informado'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <CalendarDays
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            Nascimento
                                        </p>
                                        <p className="mt-0.5 text-sm font-medium">
                                            {customer.birth_date
                                                ? customer.birth_date
                                                      .slice(0, 10)
                                                      .split('-')
                                                      .reverse()
                                                      .join('/')
                                                : 'Não informado'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>
                        {canManage ? (
                            customer.status === 'inactive' ? (
                                <section className="surface-panel border-emerald-500/30 bg-emerald-50/20 p-5 sm:p-6 dark:bg-emerald-950/20">
                                    <div className="flex items-start gap-3">
                                        <UserRound
                                            aria-hidden="true"
                                            className="mt-0.5 size-4 text-emerald-600 dark:text-emerald-400"
                                        />
                                        <div>
                                            <h2 className="text-base font-semibold text-foreground">
                                                Reativar cadastro
                                            </h2>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                Este cliente está atualmente inativo. Reative o cadastro para que ele volte a aparecer em novos agendamentos e atendimentos.
                                            </p>
                                        </div>
                                    </div>
                                    <Dialog
                                        open={reactivateOpen}
                                        onOpenChange={setReactivateOpen}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                type="button"
                                                className="mt-4 w-full bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                                            >
                                                Reativar cadastro
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Reativar cliente?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    O cliente voltará a ficar ativo e poderá ser selecionado em novos agendamentos.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <Form
                                                {...customers.reactivate.form(
                                                    customer.id,
                                                )}
                                                method="patch"
                                                headers={{
                                                    'X-Idempotency-Key':
                                                        reactivateKey,
                                                }}
                                                onSuccess={() =>
                                                    setReactivateOpen(false)
                                                }
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="lock_version"
                                                            value={
                                                                customer.lock_version
                                                            }
                                                        />
                                                        <DialogFooter className="mt-4">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setReactivateOpen(
                                                                        false,
                                                                    )
                                                                }
                                                            >
                                                                Cancelar
                                                            </Button>
                                                            <Button
                                                                type="submit"
                                                                disabled={
                                                                    processing
                                                                }
                                                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                                                            >
                                                                {processing
                                                                    ? 'Reativando…'
                                                                    : 'Confirmar reativação'}
                                                            </Button>
                                                        </DialogFooter>
                                                    </>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                </section>
                            ) : (
                                <section className="surface-panel border-destructive/30 p-5 sm:p-6">
                                    <div className="flex items-start gap-3">
                                        <UserRound
                                            aria-hidden="true"
                                            className="mt-0.5 size-4 text-destructive"
                                        />
                                        <div>
                                            <h2 className="text-base font-semibold">
                                                Desativar cadastro
                                            </h2>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                O histórico é preservado e o cliente
                                                pode ser reativado a qualquer momento.
                                            </p>
                                        </div>
                                    </div>
                                    <Dialog
                                        open={inactivateOpen}
                                        onOpenChange={setInactivateOpen}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                className="mt-4 w-full"
                                            >
                                                Desativar cliente
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Desativar cliente?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    O cliente deixará de aparecer em novas buscas e agendamentos, mantendo todo o histórico de visitas.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <Form
                                                {...customers.destroy.form(
                                                    customer.id,
                                                )}
                                                headers={{
                                                    'X-Idempotency-Key':
                                                        destroyKey,
                                                }}
                                                method="delete"
                                                onSuccess={() =>
                                                    setInactivateOpen(false)
                                                }
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="lock_version"
                                                            value={
                                                                customer.lock_version
                                                            }
                                                        />
                                                        <DialogFooter className="mt-4">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setInactivateOpen(
                                                                        false,
                                                                    )
                                                                }
                                                            >
                                                                Cancelar
                                                            </Button>
                                                            <Button
                                                                type="submit"
                                                                variant="destructive"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {processing
                                                                    ? 'Desativando…'
                                                                    : 'Confirmar desativação'}
                                                            </Button>
                                                        </DialogFooter>
                                                    </>
                                                )}
                                            </Form>
                                        </DialogContent>
                                    </Dialog>
                                </section>
                            )
                        ) : null}
                    </aside>
                </div>

                {selectedPackageForConsume && (
                    <Dialog open={consumePackageOpen} onOpenChange={setConsumePackageOpen}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Consumir Sessão do Pacote</DialogTitle>
                                <DialogDescription>
                                    Confirmar a baixa de sessão do pacote para {customer.name}.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                method="post"
                                action={customerPackagesRoutes.consume(selectedPackageForConsume.id).url}
                                headers={{ 'X-Idempotency-Key': consumeKey }}
                                onSuccess={() => {
                                    setConsumePackageOpen(false);
                                    setSelectedPackageForConsume(null);
                                }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <FormErrorSummary errors={errors} />

                                        <div className="rounded-lg bg-muted p-4 space-y-2 text-sm">
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Pacote:</span>
                                                <span className="font-semibold text-foreground">
                                                    {selectedPackageForConsume.package_template?.name ?? 'Pacote de Serviços'}
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Sessões disponíveis:</span>
                                                <span className="font-semibold">{selectedPackageForConsume.remaining_sessions} de {selectedPackageForConsume.total_sessions}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Após o consumo:</span>
                                                <span className="font-semibold text-primary">{Math.max(0, selectedPackageForConsume.remaining_sessions - 1)} restantes</span>
                                            </div>
                                        </div>

                                        <FormField
                                            id="sessions_consumed"
                                            label="Quantidade de Sessões a Consumir"
                                            required
                                            error={errors.sessions_consumed}
                                        >
                                            <Input
                                                id="sessions_consumed"
                                                name="sessions_consumed"
                                                type="number"
                                                min="1"
                                                max={selectedPackageForConsume.remaining_sessions}
                                                defaultValue={1}
                                                required
                                            />
                                        </FormField>

                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => {
                                                    setConsumePackageOpen(false);
                                                    setSelectedPackageForConsume(null);
                                                }}
                                            >
                                                Cancelar
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing ? 'Registrando…' : 'Confirmar Consumo'}
                                            </Button>
                                        </DialogFooter>
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

CustomerShow.layout = {
    breadcrumbs: [
        { title: 'Clientes', href: customers.index() },
        { title: 'Cadastro', href: customers.index() },
    ],
};
