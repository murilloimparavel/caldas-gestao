import { Form, Head } from '@inertiajs/react';
import {
    CalendarClock,
    CreditCard,
    Mail,
    PauseCircle,
    PlayCircle,
    ShieldCheck,
    UserMinus,
    UserPlus,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPageHeader } from '@/features/admin/components/admin-page-header';
import { AdminStatusBadge } from '@/features/admin/components/admin-status-badge';
import {
    adminRoutes,
    formatAdminDate,
    formatAdminMoney,
} from '@/features/admin/types';
import type {
    AdminAuditEvent,
    AdminClient,
    AdminPlan,
    AdminUser,
} from '@/features/admin/types';
import {
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
} from '@/components/operational';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type Subscription = {
    planId?: string;
    startedAt?: string | null;
    renewsAt?: string | null;
    endsAt?: string | null;
    seats?: number;
    paymentMethod?: string | null;
    status?: string;
    billingCycle?: string;
};
type Props = {
    client: AdminClient & {
        phone?: string | null;
        subscription?: Subscription;
    };
    plans?: AdminPlan[];
    users?: AdminUser[];
    audit?: AdminAuditEvent[];
};

function SubscriptionEditor({
    client,
    subscription,
    plans = [],
}: {
    client: AdminClient;
    subscription: Subscription;
    plans?: AdminPlan[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <CreditCard className="size-5 text-primary" />
                    Editar assinatura
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    method="patch"
                    action={adminRoutes.clientSubscription(client.id)}
                    className="space-y-5"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField name="plan_id" label="Plano" required>
                            <select
                                name="plan_id"
                                defaultValue={subscription.planId ?? ''}
                                required
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Selecione um plano</option>
                                {plans.map((plan) => (
                                    <option key={plan.id} value={plan.id}>
                                        {plan.name} ·{' '}
                                        {formatAdminMoney(plan.priceCents)}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <FormField name="status" label="Status" required>
                            <select
                                name="status"
                                defaultValue={
                                    subscription.status ?? client.status
                                }
                                required
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="trial">Em teste</option>
                                <option value="active">Ativo</option>
                                <option value="grace">Em carência</option>
                                <option value="past_due">
                                    Pagamento pendente
                                </option>
                                <option value="cancelled">Cancelado</option>
                                <option value="expired">Expirado</option>
                            </select>
                        </FormField>
                        <FormField name="billing_cycle" label="Ciclo">
                            <select
                                name="billing_cycle"
                                defaultValue={
                                    subscription.billingCycle ?? 'monthly'
                                }
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="monthly">Mensal</option>
                                <option value="quarterly">Trimestral</option>
                                <option value="yearly">Anual</option>
                            </select>
                        </FormField>
                        <FormField name="starts_at" label="Início">
                            <Input
                                name="starts_at"
                                type="date"
                                defaultValue={subscription.startedAt?.slice(
                                    0,
                                    10,
                                )}
                            />
                        </FormField>
                        <FormField name="ends_at" label="Fim / renovação">
                            <Input
                                name="ends_at"
                                type="date"
                                defaultValue={(
                                    subscription.endsAt ?? subscription.renewsAt
                                )?.slice(0, 10)}
                            />
                        </FormField>
                    </div>
                    <FormActions label="Salvar assinatura" />
                </Form>
            </CardContent>
        </Card>
    );
}

function UserManager({
    client,
    users = [],
}: {
    client: AdminClient;
    users?: AdminUser[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3">
                <CardTitle className="flex items-center gap-2">
                    <UsersRound className="size-5 text-primary" />
                    Usuários ({client.members})
                </CardTitle>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button size="sm">
                            <UserPlus className="size-4" />
                            Adicionar
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Convidar usuário</DialogTitle>
                            <DialogDescription>
                                O convite será enviado para o e-mail informado
                                após o cadastro.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            method="post"
                            action={adminRoutes.clientUserInvite(client.id)}
                            onSuccess={() => setOpen(false)}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <FormField
                                        name="name"
                                        label="Nome"
                                        required
                                        error={errors.name}
                                    >
                                        <Input name="name" required />
                                    </FormField>
                                    <FormField
                                        name="email"
                                        label="E-mail"
                                        required
                                        error={errors.email}
                                    >
                                        <Input
                                            name="email"
                                            type="email"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        name="role"
                                        label="Papel"
                                        required
                                        error={errors.role}
                                    >
                                        <select
                                            name="role"
                                            defaultValue="staff"
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="staff">
                                                Equipe
                                            </option>
                                            <option value="owner">
                                                Responsável
                                            </option>
                                        </select>
                                    </FormField>
                                    <FormActions
                                        processing={processing}
                                        label="Enviar convite"
                                    />
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardHeader>
            <CardContent>
                {users.length === 0 ? (
                    <EmptyState
                        title="Nenhum usuário adicional"
                        description="Adicione membros para dividir a operação com o cliente."
                    />
                ) : (
                    <div className="divide-y">
                        {users.map((user) => (
                            <div
                                key={user.id}
                                className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <div>
                                    <p className="text-sm font-medium">
                                        {user.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {user.email} · {user.role ?? 'Membro'}
                                        {user.status === 'revoked'
                                            ? ' · Revogado'
                                            : ''}
                                    </p>
                                </div>
                                {user.status !== 'revoked' ? (
                                    <div className="flex gap-2">
                                        <Form
                                            method="post"
                                            action={adminRoutes.clientUserAccess(
                                                client.id,
                                                user.id,
                                            )}
                                        >
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                type="submit"
                                            >
                                                <Mail className="size-4" />
                                                Reenviar acesso
                                            </Button>
                                        </Form>
                                        <Form
                                            method="delete"
                                            action={adminRoutes.clientMembershipRevoke(
                                                client.id,
                                                user.id,
                                            )}
                                        >
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                type="submit"
                                                className="text-destructive"
                                            >
                                                <UserMinus className="size-4" />
                                                Revogar
                                            </Button>
                                        </Form>
                                    </div>
                                ) : null}
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function PlatformClientShow({
    client,
    plans = [],
    users = [],
    audit = [],
}: Props) {
    const subscription = client.subscription ?? {
        startedAt: client.createdAt,
        renewsAt: client.renewsAt,
        seats: client.members,
        paymentMethod: null,
        status: client.status,
    };
    const isSuspended = client.status === 'suspended';

    return (
        <>
            <Head title={client.name} />
            <PageCanvas className="gap-8">
                <AdminPageHeader
                    title={client.name}
                    description={client.company ?? client.email}
                    backHref={adminRoutes.clients}
                    actionHref={adminRoutes.clients}
                    actionLabel="Todos os clientes"
                />
                <div className="flex flex-wrap items-center gap-3">
                    <AdminStatusBadge status={client.status} />
                    <span className="text-sm text-muted-foreground">
                        Cliente desde {formatAdminDate(client.createdAt)}
                    </span>
                    <div className="ml-auto">
                        <Form
                            action={
                                isSuspended
                                    ? adminRoutes.clientActivate(client.id)
                                    : adminRoutes.clientSuspend(client.id)
                            }
                            method="post"
                        >
                            <Button
                                variant={isSuspended ? 'default' : 'outline'}
                                type="submit"
                            >
                                {isSuspended ? (
                                    <PlayCircle className="size-4" />
                                ) : (
                                    <PauseCircle className="size-4" />
                                )}
                                {isSuspended
                                    ? 'Reativar conta'
                                    : 'Suspender conta'}
                            </Button>
                        </Form>
                    </div>
                </div>
                <section className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="size-5 text-primary" />
                                Assinatura atual
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Plano
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {client.plan}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {formatAdminMoney(client.monthlyValueCents)}{' '}
                                    / ciclo
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Próxima cobrança
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {formatAdminDate(
                                        subscription.renewsAt ??
                                            subscription.endsAt,
                                    )}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {subscription.paymentMethod ??
                                        'Forma não informada'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Início do plano
                                </p>
                                <p className="mt-1 font-medium">
                                    {formatAdminDate(subscription.startedAt)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Assentos utilizados
                                </p>
                                <p className="mt-1 font-medium">
                                    {client.members} de{' '}
                                    {subscription.seats ?? client.members}{' '}
                                    usuários
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Dados de contato</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <div className="flex size-9 items-center justify-center rounded-lg bg-secondary">
                                    <Mail className="size-4" />
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        E-mail principal
                                    </p>
                                    <p className="text-sm font-medium">
                                        {client.email}
                                    </p>
                                </div>
                            </div>
                            {client.phone ? (
                                <div className="flex items-center gap-3">
                                    <div className="flex size-9 items-center justify-center rounded-lg bg-secondary">
                                        <UsersRound className="size-4" />
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Telefone
                                        </p>
                                        <p className="text-sm font-medium">
                                            {client.phone}
                                        </p>
                                    </div>
                                </div>
                            ) : null}
                            <div className="flex items-center gap-3">
                                <div className="flex size-9 items-center justify-center rounded-lg bg-secondary">
                                    <ShieldCheck className="size-4" />
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Conta
                                    </p>
                                    <p className="text-sm font-medium">
                                        Acesso administrativo habilitado
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>
                <SubscriptionEditor
                    client={client}
                    subscription={subscription}
                    plans={plans}
                />
                <UserManager client={client} users={users} />
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CalendarClock className="size-5 text-primary" />
                            Auditoria
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {audit.length === 0 ? (
                            <EmptyState
                                title="Nenhum evento registrado"
                                description="Alterações administrativas deste cliente aparecerão aqui."
                            />
                        ) : (
                            <ol className="space-y-5">
                                {audit.map((event) => (
                                    <li key={event.id} className="flex gap-3">
                                        <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                        <div>
                                            <p className="text-sm font-medium">
                                                {event.description ??
                                                    event.action}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {event.actor ?? 'Sistema'} ·{' '}
                                                {formatAdminDate(
                                                    event.createdAt,
                                                )}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </PageCanvas>
        </>
    );
}

PlatformClientShow.layout = {
    breadcrumbs: [
        { title: 'Administração', href: adminRoutes.dashboard },
        { title: 'Clientes', href: adminRoutes.clients },
    ],
};
