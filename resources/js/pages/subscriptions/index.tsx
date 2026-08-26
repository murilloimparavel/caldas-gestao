import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, RefreshCw, Scissors, TrendingUp, Users } from 'lucide-react';
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
    parseBrazilianCurrency,
    RelationCheckboxes,
    ResourceHeader,
    SearchToolbar,
    StatusBadge,
} from '@/components/operational';
import type {
    Paginated,
    RelationOption,
    ResourceFilters,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import subscriptionsRoutes from '@/routes/subscriptions';
import type { SharedPageProps } from '@/types';

type ServiceSummary = {
    id: string;
    name: string;
    price_cents: number;
};

type SubscriptionPlan = {
    billing_cycle: 'monthly' | 'quarterly' | 'yearly';
    customer_subscriptions_count?: number;
    description: string | null;
    id: string;
    is_active: boolean;
    name: string;
    price_cents: number;
    services: ServiceSummary[];
};

type Props = {
    filters: ResourceFilters;
    metrics?: {
        estimated_monthly_revenue_cents: number;
        total_active_subscribers: number;
    };
    plans: Paginated<SubscriptionPlan>;
    serviceOptions?: RelationOption[];
};

const billingCycleLabel: Record<string, string> = {
    monthly: 'Mensal',
    quarterly: 'Trimestral',
    yearly: 'Anual',
};

function PlanPriceField({ initialCents = 0 }: { initialCents?: number }) {
    const [displayValue, setDisplayValue] = useState(
        initialCents > 0 ? (initialCents / 100).toFixed(2).replace('.', ',') : '',
    );
    const cents = parseBrazilianCurrency(displayValue);

    return (
        <>
            <Input
                id="price_display"
                name="price_display"
                inputMode="decimal"
                value={displayValue}
                onChange={(event) => setDisplayValue(event.target.value)}
                placeholder="0,00"
                aria-describedby="price-help"
            />
            <input
                type="hidden"
                name="price_cents"
                value={Number.isFinite(cents) ? cents : 0}
            />
            <p id="price-help" className="text-xs text-muted-foreground">
                Valor cobrado por ciclo (mensal, trimestral ou anual).
            </p>
        </>
    );
}

export default function SubscriptionsIndex({
    plans: paginator,
    filters,
    serviceOptions = [],
    metrics,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [createKey] = useState(() => createIdempotencyKey('subscription-create'));
    const [billingCycle, setBillingCycle] = useState('monthly');
    const { props } = usePage<SharedPageProps>();
    const permissions = new Set(props.auth.permissions);
    const canManage = permissions.has('subscription.manage');

    return (
        <PageCanvas>
            <Head title="Planos de Assinatura" />

            <ResourceHeader
                eyebrow="Gestão"
                title="Planos de Assinatura"
                description="Gerencie planos recorrentes e acompanhe assinantes ativos."
                action={
                    canManage && (
                        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                            <DialogTrigger asChild>
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Novo Plano
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                                <DialogHeader>
                                    <DialogTitle>Criar Plano de Assinatura</DialogTitle>
                                    <DialogDescription>
                                        Defina o nome, preço, ciclo de cobrança e serviços inclusos.
                                    </DialogDescription>
                                </DialogHeader>

                                <Form
                                    method="post"
                                    action={subscriptionsRoutes.store().url}
                                    headers={{ 'X-Idempotency-Key': createKey }}
                                    onSuccess={() => setCreateOpen(false)}
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <FormErrorSummary errors={errors} />

                                            <FormField
                                                name="name"
                                                label="Nome do Plano"
                                                error={errors.name}
                                            >
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    required
                                                    placeholder="Ex.: Plano Premium Mensal"
                                                />
                                            </FormField>

                                            <FormField
                                                name="description"
                                                label="Descrição"
                                                error={errors.description}
                                            >
                                                <Input
                                                    id="description"
                                                    name="description"
                                                    placeholder="Breve descrição dos benefícios..."
                                                />
                                            </FormField>

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <FormField
                                                    name="price_display"
                                                    label="Preço"
                                                    error={errors.price_cents}
                                                >
                                                    <PlanPriceField />
                                                </FormField>

                                                <FormField
                                                    name="billing_cycle"
                                                    label="Ciclo de Cobrança"
                                                    error={errors.billing_cycle}
                                                >
                                                    <Select
                                                        value={billingCycle}
                                                        onValueChange={setBillingCycle}
                                                    >
                                                        <SelectTrigger id="billing_cycle">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="monthly">Mensal</SelectItem>
                                                            <SelectItem value="quarterly">Trimestral</SelectItem>
                                                            <SelectItem value="yearly">Anual</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <input type="hidden" name="billing_cycle" value={billingCycle} />
                                                </FormField>
                                            </div>

                                            {serviceOptions.length > 0 && (
                                                <FormField
                                                    name="service_ids"
                                                    label="Serviços Inclusos"
                                                    error={errors.service_ids}
                                                >
                                                    <RelationCheckboxes
                                                        name="service_ids"
                                                        options={serviceOptions}
                                                    />
                                                </FormField>
                                            )}

                                            <FormActions processing={processing} onCancel={() => setCreateOpen(false)} label="Criar Plano" />
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )
                }
            />

            {metrics && (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Assinantes Ativos</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.total_active_subscribers}</div>
                            <p className="text-xs text-muted-foreground">assinaturas ativas no momento</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Receita Mensal Estimada</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatMoney(metrics.estimated_monthly_revenue_cents)}
                            </div>
                            <p className="text-xs text-muted-foreground">baseado em assinantes ativos</p>
                        </CardContent>
                    </Card>
                </div>
            )}

            <div className="space-y-4">
                <SearchToolbar
                    action={subscriptionsRoutes.index().url}
                    defaultValue={filters.search ?? ''}
                    placeholder="Buscar planos..."
                    status={filters.status ?? 'active'}
                    onStatusChange={(status) => router.get(subscriptionsRoutes.index().url, { search: filters.search ?? '', status })}
                />

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title="Nenhum plano encontrado"
                        description="Crie planos de assinatura recorrente para oferecer aos seus clientes."
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {paginator.data.map((plan) => (
                            <Link
                                key={plan.id}
                                href={subscriptionsRoutes.show(plan.id).url}
                                className="group relative flex flex-col justify-between rounded-xl border bg-card p-5 shadow-xs transition-all hover:border-primary/40 hover:shadow-md"
                            >
                                <div className="space-y-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <h3 className="font-semibold text-foreground group-hover:text-primary transition-colors">
                                                {plan.name}
                                            </h3>
                                            {plan.description && (
                                                <p className="line-clamp-2 text-xs text-muted-foreground mt-1">
                                                    {plan.description}
                                                </p>
                                            )}
                                        </div>
                                        <StatusBadge status={plan.is_active ? 'active' : 'inactive'} />
                                    </div>

                                    <div className="flex items-baseline gap-2">
                                        <span className="text-2xl font-bold text-foreground">
                                            {formatMoney(plan.price_cents)}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            / {billingCycleLabel[plan.billing_cycle] ?? plan.billing_cycle}
                                        </span>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 pt-2 border-t text-xs text-muted-foreground">
                                        <Badge variant="outline" className="text-[11px] font-normal">
                                            <RefreshCw className="mr-1 h-3 w-3" />
                                            {billingCycleLabel[plan.billing_cycle] ?? plan.billing_cycle}
                                        </Badge>
                                        {typeof plan.customer_subscriptions_count === 'number' && (
                                            <span className="flex items-center gap-1">
                                                <Users className="h-3 w-3" />
                                                {plan.customer_subscriptions_count}{' '}
                                                {plan.customer_subscriptions_count === 1 ? 'assinante' : 'assinantes'}
                                            </span>
                                        )}
                                    </div>

                                    {plan.services.length > 0 && (
                                        <div className="flex flex-wrap gap-1 pt-1">
                                            {plan.services.slice(0, 3).map((srv) => (
                                                <Badge key={srv.id} variant="secondary" className="text-[11px] font-normal">
                                                    <Scissors className="mr-1 h-3 w-3" />
                                                    {srv.name}
                                                </Badge>
                                            ))}
                                            {plan.services.length > 3 && (
                                                <Badge variant="outline" className="text-[11px] font-normal">
                                                    +{plan.services.length - 3} mais
                                                </Badge>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                <Pagination links={paginator.links} />
            </div>
        </PageCanvas>
    );
}
