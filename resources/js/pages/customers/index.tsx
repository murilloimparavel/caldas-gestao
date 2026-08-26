import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, UserRound, UserRoundCheck } from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
    Pagination,
    ResourceHeader,
    SearchToolbar,
    StatusBadge,
} from '@/components/operational';
import type { Paginated, ResourceFilters } from '@/components/operational';
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
import customers from '@/routes/customers';
import type { SharedPageProps } from '@/types';

type Customer = {
    birth_date: string | null;
    email: string | null;
    id: string;
    name: string;
    phone: string | null;
    status: 'active' | 'inactive';
};

type Props = {
    customers: Paginated<Customer>;
    filters: ResourceFilters;
};

export default function CustomersIndex({
    customers: paginator,
    filters,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [createKey] = useState(() => createIdempotencyKey('customer-create'));
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('customer.manage');

    const handleStatusChange = (status: 'active' | 'inactive' | 'all') => {
        router.get(
            customers.index.url(),
            { ...filters, status },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Clientes" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Relacionamento"
                    title="Clientes"
                    description="Mantenha uma base confiável para a agenda e acompanhe o histórico de cada pessoa atendida."
                    action={
                        canManage ? (
                            <Dialog
                                open={createOpen}
                                onOpenChange={setCreateOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button className="w-full sm:w-auto">
                                        <Plus aria-hidden="true" />
                                        Novo cliente
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
                                    <DialogHeader>
                                        <DialogTitle>Novo cliente</DialogTitle>
                                        <DialogDescription>
                                            Cadastre os dados essenciais. Você
                                            poderá completar o perfil depois.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...customers.store.form()}
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
                                                            label="Nome completo"
                                                            name="name"
                                                            error={errors.name}
                                                        >
                                                            <Input
                                                                id="name"
                                                                name="name"
                                                                required
                                                                autoFocus
                                                                placeholder="Ex.: Ana Souza"
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
                                                            placeholder="ana@exemplo.com"
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
                                                            placeholder="(00) 00000-0000"
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        label="Data de nascimento"
                                                        name="birth_date"
                                                        error={
                                                            errors.birth_date
                                                        }
                                                    >
                                                        <Input
                                                            id="birth_date"
                                                            name="birth_date"
                                                            type="date"
                                                        />
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
                                                                rows={3}
                                                                placeholder="Preferências, cuidados ou informações úteis"
                                                                className="min-h-24 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                                            />
                                                        </FormField>
                                                    </div>
                                                </div>
                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="active"
                                                />
                                                <FormActions
                                                    processing={processing}
                                                    onCancel={() =>
                                                        setCreateOpen(false)
                                                    }
                                                    label="Cadastrar cliente"
                                                />
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        ) : null
                    }
                />

                <SearchToolbar
                    action={customers.index.url()}
                    defaultValue={filters.search}
                    status={filters.status ?? 'active'}
                    onStatusChange={handleStatusChange}
                    placeholder="Buscar por nome ou telefone"
                    resultLabel={`${paginator.total} ${paginator.total === 1 ? 'cliente encontrado' : 'clientes encontrados'}`}
                />

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title={
                            filters.search || filters.status === 'inactive'
                                ? 'Nenhum cliente encontrado'
                                : 'Sua base começa aqui'
                        }
                        description={
                            filters.search || filters.status === 'inactive'
                                ? 'Tente outro termo de busca ou altere o filtro de status.'
                                : 'Cadastre o primeiro cliente para começar a organizar seus atendimentos.'
                        }
                        action={
                            !filters.search &&
                            (!filters.status || filters.status === 'active') &&
                            canManage ? (
                                <Button onClick={() => setCreateOpen(true)}>
                                    <Plus aria-hidden="true" />
                                    Cadastrar primeiro cliente
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <section
                        aria-label="Lista de clientes"
                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                    >
                        {paginator.data.map((customer) => (
                            <article
                                key={customer.id}
                                className="surface-panel flex min-h-48 flex-col gap-5 p-5 transition-colors hover:border-primary/40"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-secondary text-secondary-foreground">
                                            <UserRound
                                                aria-hidden="true"
                                                className="size-5"
                                            />
                                        </div>
                                        <div className="min-w-0">
                                            <h2 className="truncate font-semibold text-foreground">
                                                {customer.name}
                                            </h2>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {customer.phone ||
                                                    customer.email ||
                                                    'Sem contato informado'}
                                            </p>
                                        </div>
                                    </div>
                                    <StatusBadge status={customer.status} />
                                </div>
                                <div className="grid gap-2 text-sm text-muted-foreground">
                                    <p>
                                        {customer.email ||
                                            'E-mail não informado'}
                                    </p>
                                    <p>
                                        {customer.birth_date
                                            ? `Nascimento: ${customer.birth_date.slice(0, 10).split('-').reverse().join('/')}`
                                            : 'Data de nascimento não informada'}
                                    </p>
                                </div>
                                <div className="mt-auto flex items-center justify-between gap-3 border-t border-border pt-4">
                                    <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <UserRoundCheck
                                            aria-hidden="true"
                                            className="size-3.5"
                                        />
                                        Cadastro da unidade
                                    </span>
                                    <Button asChild variant="outline" size="sm">
                                        <Link
                                            href={customers.show(customer.id)}
                                        >
                                            Ver cadastro
                                        </Link>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </section>
                )}

                <Pagination links={paginator.links} />
            </PageCanvas>
        </>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [{ title: 'Clientes', href: customers.index() }],
};
