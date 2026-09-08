import { Form, Head, usePage } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    DollarSign,
    RefreshCw,
    Scissors,
    Users,
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
    Pagination,
    parseBrazilianCurrency,
    RelationCheckboxes,
    ResourceHeader,
    StatusBadge,
} from '@/components/operational';
import type {
    Paginated,
    RelationOption,
    ResourceStatus,
} from '@/components/operational';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import customerSubscriptionsRoutes from '@/routes/customer-subscriptions';
import subscriptionsRoutes from '@/routes/subscriptions';
import type { SharedPageProps } from '@/types';

type ServiceSummary = {
    duration_minutes?: number;
    id: string;
    name: string;
    price_cents: number;
};

type SubscriberRecord = {
    cancelled_at: string | null;
    customer: {
        email?: string | null;
        id: string;
        name: string;
        phone?: string | null;
    };
    id: string;
    lock_version: number;
    next_billing_date: string | null;
    start_date: string;
    status: 'active' | 'paused' | 'cancelled' | 'expired';
};

type SubscriberAction = {
    id: string;
    idempotencyKey: string;
    name: string;
};

type SubscriptionPlan = {
    billing_cycle: 'monthly' | 'quarterly' | 'yearly';
    description: string | null;
    id: string;
    is_active: boolean;
    lock_version: number;
    name: string;
    price_cents: number;
    services: ServiceSummary[];
};

type Props = {
    plan: SubscriptionPlan;
    serviceOptions?: RelationOption[];
    subscribers: Paginated<SubscriberRecord>;
};

const billingCycleLabel: Record<string, string> = {
    monthly: 'Mensal',
    quarterly: 'Trimestral',
    yearly: 'Anual',
};

const statusLabel: Record<string, string> = {
    active: 'Ativo',
    paused: 'Pausado',
    cancelled: 'Cancelado',
    expired: 'Expirado',
};

function PlanPriceField({ initialCents = 0 }: { initialCents?: number }) {
    const [displayValue, setDisplayValue] = useState(
        initialCents > 0
            ? (initialCents / 100).toFixed(2).replace('.', ',')
            : '',
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
            />
            <input
                type="hidden"
                name="price_cents"
                value={Number.isFinite(cents) ? cents : 0}
            />
        </>
    );
}

function SubscriberStatusBadge({ status }: { status: string }) {
    const variantMap: Record<
        string,
        'default' | 'secondary' | 'destructive' | 'outline'
    > = {
        active: 'default',
        paused: 'secondary',
        cancelled: 'destructive',
        expired: 'outline',
    };

    return (
        <Badge variant={variantMap[status] ?? 'outline'}>
            {statusLabel[status] ?? status}
        </Badge>
    );
}

export default function SubscriptionsShow({
    plan,
    serviceOptions = [],
    subscribers,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [editKey] = useState(() => createIdempotencyKey('subscription-edit'));
    const [cancelSubOpen, setCancelSubOpen] = useState<string | null>(null);
    const [consumeSubscriber, setConsumeSubscriber] =
        useState<SubscriberAction | null>(null);
    const [renewSubscriber, setRenewSubscriber] =
        useState<SubscriberAction | null>(null);
    const { props } = usePage<SharedPageProps>();
    const permissions = new Set(props.auth.permissions);
    const canManage = permissions.has('subscription.manage');
    const canCancel = permissions.has('subscription.cancel');
    const canConsume = permissions.has('subscription.usage');
    const canRenew = permissions.has('subscription.renew');

    function openConsumption(subscriber: SubscriberRecord): void {
        setConsumeSubscriber({
            id: subscriber.id,
            idempotencyKey: createIdempotencyKey(
                'subscription-usage',
                subscriber.id,
            ),
            name: subscriber.customer.name,
        });
    }

    function openRenewal(subscriber: SubscriberRecord): void {
        setRenewSubscriber({
            id: subscriber.id,
            idempotencyKey: createIdempotencyKey(
                'subscription-renew',
                subscriber.id,
            ),
            name: subscriber.customer.name,
        });
    }

    const activeCount = subscribers.data.filter(
        (s) => s.status === 'active',
    ).length;

    return (
        <PageCanvas>
            <Head title={`Plano: ${plan.name}`} />

            <ResourceHeader
                eyebrow="Gestão"
                title={plan.name}
                description={
                    plan.description ?? 'Plano de assinatura recorrente'
                }
                backHref={subscriptionsRoutes.index().url}
                backLabel="Planos"
                action={
                    canManage && (
                        <div className="flex gap-2">
                            <Dialog open={editOpen} onOpenChange={setEditOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        Editar Plano
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                                    <DialogHeader>
                                        <DialogTitle>Editar Plano</DialogTitle>
                                        <DialogDescription>
                                            Atualize as informações do plano de
                                            assinatura.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        method="put"
                                        action={
                                            subscriptionsRoutes.update(plan.id)
                                                .url
                                        }
                                        headers={{
                                            'X-Idempotency-Key': editKey,
                                        }}
                                        onSuccess={() => setEditOpen(false)}
                                        className="space-y-4"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <FormErrorSummary
                                                    errors={errors}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="lock_version"
                                                    value={plan.lock_version}
                                                />

                                                <FormField
                                                    name="name"
                                                    label="Nome"
                                                    error={errors.name}
                                                >
                                                    <Input
                                                        id="name"
                                                        name="name"
                                                        defaultValue={plan.name}
                                                        required
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
                                                        defaultValue={
                                                            plan.description ??
                                                            ''
                                                        }
                                                    />
                                                </FormField>

                                                <FormField
                                                    name="price_display"
                                                    label="Preço"
                                                    error={errors.price_cents}
                                                >
                                                    <PlanPriceField
                                                        initialCents={
                                                            plan.price_cents
                                                        }
                                                    />
                                                </FormField>

                                                {serviceOptions.length > 0 && (
                                                    <FormField
                                                        name="service_ids"
                                                        label="Serviços Inclusos"
                                                        error={
                                                            errors.service_ids
                                                        }
                                                    >
                                                        <RelationCheckboxes
                                                            name="service_ids"
                                                            options={
                                                                serviceOptions
                                                            }
                                                            selectedIds={plan.services.map(
                                                                (s) => s.id,
                                                            )}
                                                        />
                                                    </FormField>
                                                )}

                                                <FormActions
                                                    processing={processing}
                                                    onCancel={() =>
                                                        setEditOpen(false)
                                                    }
                                                    label="Salvar Alterações"
                                                />
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>

                            {plan.is_active ? (
                                <Form
                                    method="delete"
                                    action={
                                        subscriptionsRoutes.destroy(plan.id).url
                                    }
                                >
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="lock_version"
                                                value={plan.lock_version}
                                            />
                                            <Button
                                                variant="destructive"
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Desativar
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <Form
                                    method="patch"
                                    action={
                                        subscriptionsRoutes.reactivate(plan.id)
                                            .url
                                    }
                                >
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="lock_version"
                                                value={plan.lock_version}
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Reativar
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </div>
                    )
                }
            />

            <div className="space-y-6">
                {/* Métricas */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Preço
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatMoney(plan.price_cents)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {billingCycleLabel[plan.billing_cycle]}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Assinantes Ativos
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {activeCount}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                neste plano
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Status
                            </CardTitle>
                            <RefreshCw className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <StatusBadge
                                status={
                                    plan.is_active
                                        ? ('active' as ResourceStatus)
                                        : ('inactive' as ResourceStatus)
                                }
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                {billingCycleLabel[plan.billing_cycle]}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Alert className="border-amber-500/30 bg-amber-500/5 dark:bg-amber-500/10">
                    <RefreshCw className="h-4 w-4" />
                    <AlertTitle>Renovação operacional sem gateway</AlertTitle>
                    <AlertDescription>
                        O plano controla o ciclo de acesso (
                        {billingCycleLabel[plan.billing_cycle].toLowerCase()});
                        o Caldas Gestão não processa pagamentos. A renovação
                        apenas atualiza o ciclo de acesso e seu histórico
                        operacional.
                    </AlertDescription>
                </Alert>

                {(canConsume || canRenew) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Operação da assinatura
                            </CardTitle>
                            <CardDescription>
                                Registre o uso dos serviços e avance o ciclo de
                                acesso quando necessário. Estas ações não
                                processam pagamentos.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Escolha um assinante na lista abaixo para
                                registrar um serviço ou processar a renovação
                                interna.
                            </p>
                            <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                {canConsume && (
                                    <Badge variant="secondary">
                                        Uso por serviço
                                    </Badge>
                                )}
                                {canRenew && (
                                    <Badge variant="secondary">
                                        Renovação de ciclo
                                    </Badge>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Serviços Inclusos */}
                {plan.services.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Serviços Inclusos
                            </CardTitle>
                            <CardDescription>
                                Serviços disponíveis para assinantes deste
                                plano.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-2">
                                {plan.services.map((srv) => (
                                    <Badge key={srv.id} variant="secondary">
                                        <Scissors className="mr-1 h-3 w-3" />
                                        {srv.name}
                                        <span className="ml-1 text-muted-foreground">
                                            — {formatMoney(srv.price_cents)}
                                        </span>
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Assinantes */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Assinantes</CardTitle>
                        <CardDescription>
                            Lista de clientes vinculados a este plano.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {subscribers.data.length === 0 ? (
                            <div className="p-6 text-center text-sm text-muted-foreground">
                                Nenhum assinante encontrado para este plano.
                            </div>
                        ) : (
                            <div className="divide-y">
                                {subscribers.data.map((sub) => (
                                    <div
                                        key={sub.id}
                                        className="flex items-center justify-between gap-4 px-6 py-4"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {sub.customer.name}
                                            </p>
                                            <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <span className="flex items-center gap-1">
                                                    <Calendar className="h-3 w-3" />
                                                    Início: {sub.start_date}
                                                </span>
                                                {sub.next_billing_date && (
                                                    <span className="flex items-center gap-1">
                                                        <RefreshCw className="h-3 w-3" />
                                                        Próx:{' '}
                                                        {sub.next_billing_date}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <SubscriberStatusBadge
                                                status={sub.status}
                                            />
                                            {sub.status === 'active' &&
                                                canConsume &&
                                                plan.services.length > 0 && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            openConsumption(sub)
                                                        }
                                                    >
                                                        Registrar uso
                                                    </Button>
                                                )}
                                            {sub.status === 'active' &&
                                                canRenew && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            openRenewal(sub)
                                                        }
                                                    >
                                                        Renovar ciclo
                                                    </Button>
                                                )}
                                            {sub.status === 'active' &&
                                                canManage && (
                                                    <Form
                                                        method="post"
                                                        action={
                                                            customerSubscriptionsRoutes.pause(
                                                                sub.id,
                                                            ).url
                                                        }
                                                    >
                                                        {({ processing }) => (
                                                            <>
                                                                <input
                                                                    type="hidden"
                                                                    name="lock_version"
                                                                    value={
                                                                        sub.lock_version
                                                                    }
                                                                />
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    type="submit"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    Pausar
                                                                </Button>
                                                            </>
                                                        )}
                                                    </Form>
                                                )}
                                            {sub.status === 'paused' &&
                                                canManage && (
                                                    <Form
                                                        method="post"
                                                        action={
                                                            customerSubscriptionsRoutes.resume(
                                                                sub.id,
                                                            ).url
                                                        }
                                                    >
                                                        {({ processing }) => (
                                                            <>
                                                                <input
                                                                    type="hidden"
                                                                    name="lock_version"
                                                                    value={
                                                                        sub.lock_version
                                                                    }
                                                                />
                                                                <Button
                                                                    size="sm"
                                                                    type="submit"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    <CheckCircle2 className="mr-1 h-3 w-3" />
                                                                    Retomar
                                                                </Button>
                                                            </>
                                                        )}
                                                    </Form>
                                                )}
                                            {(sub.status === 'active' ||
                                                sub.status === 'paused') &&
                                                canCancel && (
                                                    <Dialog
                                                        open={
                                                            cancelSubOpen ===
                                                            sub.id
                                                        }
                                                        onOpenChange={(open) =>
                                                            setCancelSubOpen(
                                                                open
                                                                    ? sub.id
                                                                    : null,
                                                            )
                                                        }
                                                    >
                                                        <DialogTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-destructive"
                                                            >
                                                                <XCircle className="h-4 w-4" />
                                                            </Button>
                                                        </DialogTrigger>
                                                        <DialogContent>
                                                            <DialogHeader>
                                                                <DialogTitle>
                                                                    Cancelar
                                                                    Assinatura
                                                                </DialogTitle>
                                                                <DialogDescription>
                                                                    Tem certeza
                                                                    que deseja
                                                                    cancelar a
                                                                    assinatura
                                                                    de{' '}
                                                                    <strong>
                                                                        {
                                                                            sub
                                                                                .customer
                                                                                .name
                                                                        }
                                                                    </strong>
                                                                    ?
                                                                </DialogDescription>
                                                            </DialogHeader>
                                                            <Form
                                                                method="post"
                                                                action={
                                                                    customerSubscriptionsRoutes.cancel(
                                                                        sub.id,
                                                                    ).url
                                                                }
                                                                onSuccess={() =>
                                                                    setCancelSubOpen(
                                                                        null,
                                                                    )
                                                                }
                                                                className="space-y-4"
                                                            >
                                                                {({
                                                                    processing,
                                                                    errors,
                                                                }) => (
                                                                    <>
                                                                        <input
                                                                            type="hidden"
                                                                            name="lock_version"
                                                                            value={
                                                                                sub.lock_version
                                                                            }
                                                                        />
                                                                        <FormErrorSummary
                                                                            errors={
                                                                                errors
                                                                            }
                                                                        />
                                                                        <FormField
                                                                            name="notes"
                                                                            label="Motivo (opcional)"
                                                                            error={
                                                                                errors.notes
                                                                            }
                                                                        >
                                                                            <textarea
                                                                                id="notes"
                                                                                name="notes"
                                                                                rows={
                                                                                    3
                                                                                }
                                                                                className="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                                            />
                                                                        </FormField>
                                                                        <DialogFooter>
                                                                            <Button
                                                                                type="button"
                                                                                variant="outline"
                                                                                onClick={() =>
                                                                                    setCancelSubOpen(
                                                                                        null,
                                                                                    )
                                                                                }
                                                                            >
                                                                                Voltar
                                                                            </Button>
                                                                            <Button
                                                                                variant="destructive"
                                                                                type="submit"
                                                                                disabled={
                                                                                    processing
                                                                                }
                                                                            >
                                                                                Confirmar
                                                                                Cancelamento
                                                                            </Button>
                                                                        </DialogFooter>
                                                                    </>
                                                                )}
                                                            </Form>
                                                        </DialogContent>
                                                    </Dialog>
                                                )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                        <div className="p-4">
                            <Pagination links={subscribers.links} />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={consumeSubscriber !== null}
                onOpenChange={(open) => !open && setConsumeSubscriber(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Registrar uso da assinatura</DialogTitle>
                        <DialogDescription>
                            Registre um serviço utilizado por{' '}
                            {consumeSubscriber?.name ?? 'este assinante'} dentro
                            do ciclo vigente.
                        </DialogDescription>
                    </DialogHeader>
                    {consumeSubscriber && (
                        <Form
                            method="post"
                            action={
                                customerSubscriptionsRoutes.consume(
                                    consumeSubscriber.id,
                                ).url
                            }
                            headers={{
                                'X-Idempotency-Key':
                                    consumeSubscriber.idempotencyKey,
                            }}
                            onSuccess={() => setConsumeSubscriber(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <FormField
                                        name="service_id"
                                        label="Serviço"
                                        error={errors.service_id}
                                    >
                                        <select
                                            id="subscription-service-id"
                                            name="service_id"
                                            required
                                            defaultValue=""
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <option value="">
                                                Selecione um serviço
                                            </option>
                                            {plan.services.map((service) => (
                                                <option
                                                    key={service.id}
                                                    value={service.id}
                                                >
                                                    {service.name}
                                                </option>
                                            ))}
                                        </select>
                                    </FormField>
                                    <FormField
                                        name="quantity"
                                        label="Quantidade"
                                        error={errors.quantity}
                                    >
                                        <Input
                                            id="subscription-quantity"
                                            name="quantity"
                                            type="number"
                                            min={1}
                                            max={100}
                                            defaultValue={1}
                                            required
                                        />
                                    </FormField>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setConsumeSubscriber(null)
                                            }
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing || !canConsume}
                                        >
                                            {processing
                                                ? 'Registrando…'
                                                : 'Registrar uso'}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={renewSubscriber !== null}
                onOpenChange={(open) => !open && setRenewSubscriber(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Processar renovação interna</DialogTitle>
                        <DialogDescription>
                            Avance o ciclo de acesso de{' '}
                            {renewSubscriber?.name ?? 'este assinante'} conforme
                            a data informada. Nenhum pagamento é processado
                            aqui.
                        </DialogDescription>
                    </DialogHeader>
                    {renewSubscriber && (
                        <Form
                            method="post"
                            action={
                                customerSubscriptionsRoutes.renew(
                                    renewSubscriber.id,
                                ).url
                            }
                            headers={{
                                'X-Idempotency-Key':
                                    renewSubscriber.idempotencyKey,
                            }}
                            onSuccess={() => setRenewSubscriber(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <FormField
                                        name="as_of"
                                        label="Processar até"
                                        error={errors.as_of}
                                    >
                                        <Input
                                            id="subscription-renew-as-of"
                                            name="as_of"
                                            type="date"
                                            defaultValue={new Date()
                                                .toISOString()
                                                .slice(0, 10)}
                                        />
                                    </FormField>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setRenewSubscriber(null)
                                            }
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing || !canRenew}
                                        >
                                            {processing
                                                ? 'Processando…'
                                                : 'Processar renovação'}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </PageCanvas>
    );
}
