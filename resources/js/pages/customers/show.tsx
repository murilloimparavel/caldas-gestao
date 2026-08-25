import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    CalendarDays,
    Clock,
    ExternalLink,
    Mail,
    Phone,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { statusLabels } from '@/components/calendar';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
    ResourceHeader,
    StatusBadge,
} from '@/components/operational';
import type { ResourceStatus } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as calendarIndex } from '@/routes/calendar';
import customers from '@/routes/customers';
import type { SharedPageProps } from '@/types';

type CustomerAppointment = {
    ends_at: string;
    id: string;
    notes?: string | null;
    professional?: { id: string; name: string } | null;
    starts_at: string;
    status: string;
};

type Customer = {
    appointments?: CustomerAppointment[];
    birth_date: string | null;
    created_at?: string;
    email: string | null;
    id: string;
    lock_version: number;
    name: string;
    notes: string | null;
    phone: string | null;
    status: ResourceStatus;
};

type Props = {
    customer: Customer;
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

export default function CustomerShow({ customer }: Props) {
    const [updateKey] = useState(() => createIdempotencyKey('customer-update'));
    const [destroyKey] = useState(() =>
        createIdempotencyKey('customer-destroy'),
    );
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('customer.manage');
    const appointments = customer.appointments ?? [];

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
                        description="Atualize os dados de contato e mantenha o cadastro pronto para a próxima visita."
                        action={<StatusBadge status={customer.status} />}
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <div className="space-y-5">
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
                                            pode ser reativado editando o
                                            status.
                                        </p>
                                    </div>
                                </div>
                                <Form
                                    {...customers.destroy.form(customer.id)}
                                    headers={{
                                        'X-Idempotency-Key': destroyKey,
                                    }}
                                    className="mt-4"
                                    onSubmit={(event) => {
                                        if (
                                            !window.confirm(
                                                'Desativar este cliente?',
                                            )
                                        ) {
                                            event.preventDefault();
                                        }
                                    }}
                                >
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="lock_version"
                                                value={customer.lock_version}
                                            />
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                {processing
                                                    ? 'Desativando…'
                                                    : 'Desativar cliente'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </section>
                        ) : null}
                    </aside>
                </div>
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
