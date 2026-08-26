import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, UserRound } from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
    Pagination,
    RelationCheckboxes,
    RelationList,
    ResourceHeader,
    SearchToolbar,
    StatusBadge,
} from '@/components/operational';
import type {
    Paginated,
    RelationOption,
    ResourceFilters,
} from '@/components/operational';
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
import professionals from '@/routes/professionals';
import type { SharedPageProps } from '@/types';

type ServiceSummary = {
    id: string;
    name: string;
};

type Professional = {
    email: string | null;
    id: string;
    name: string;
    phone: string | null;
    services: ServiceSummary[];
    status: 'active' | 'inactive';
};

type Props = {
    filters: ResourceFilters;
    options?: {
        services?: RelationOption[];
    };
    professionals: Paginated<Professional>;
    serviceOptions?: RelationOption[];
};

export default function ProfessionalsIndex({
    professionals: paginator,
    filters,
    options,
    serviceOptions,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [createKey] = useState(() =>
        createIdempotencyKey('professional-create'),
    );
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('professional.manage');

    const handleStatusChange = (status: 'active' | 'inactive' | 'all') => {
        router.get(
            professionals.index.url(),
            { ...filters, status },
            { preserveState: true, preserveScroll: true },
        );
    };

    const availableServices = serviceOptions ?? options?.services ?? [];

    return (
        <>
            <Head title="Profissionais" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Relacionamento"
                    title="Profissionais"
                    description="Organize quem atende, seus contatos e os serviços disponíveis para a agenda."
                    action={
                        canManage ? (
                            <Dialog
                                open={createOpen}
                                onOpenChange={setCreateOpen}
                            >
                                <DialogTrigger asChild>
                                    <Button className="w-full sm:w-auto">
                                        <Plus aria-hidden="true" />
                                        Novo profissional
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Novo profissional
                                        </DialogTitle>
                                        <DialogDescription>
                                            Cadastre a pessoa que participa da
                                            operação desta unidade.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...professionals.store.form()}
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
                                                                placeholder="Ex.: Beatriz Lima"
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
                                                            placeholder="beatriz@exemplo.com"
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
                                                    <div className="space-y-2 sm:col-span-2">
                                                        <p className="text-sm font-medium text-foreground">
                                                            Serviços habilitados
                                                        </p>
                                                        <RelationCheckboxes
                                                            name="service_ids"
                                                            options={
                                                                availableServices
                                                            }
                                                        />
                                                        <p className="text-xs text-muted-foreground">
                                                            Selecione os
                                                            serviços que esta
                                                            pessoa poderá
                                                            realizar.
                                                        </p>
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
                                                    label="Cadastrar profissional"
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
                    action={professionals.index.url()}
                    defaultValue={filters.search}
                    status={filters.status ?? 'active'}
                    onStatusChange={handleStatusChange}
                    placeholder="Buscar por nome"
                    resultLabel={`${paginator.total} ${paginator.total === 1 ? 'profissional encontrado' : 'profissionais encontrados'}`}
                />

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title={
                            filters.search || filters.status === 'inactive'
                                ? 'Nenhum profissional encontrado'
                                : 'Nenhum profissional cadastrado'
                        }
                        description={
                            filters.search || filters.status === 'inactive'
                                ? 'Tente outro termo ou altere o filtro de status.'
                                : 'Cadastre a equipe que atende nesta unidade para liberar as escolhas da agenda.'
                        }
                        action={
                            !filters.search &&
                            (!filters.status || filters.status === 'active') &&
                            canManage ? (
                                <Button onClick={() => setCreateOpen(true)}>
                                    <Plus aria-hidden="true" />
                                    Cadastrar primeiro profissional
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <section
                        aria-label="Lista de profissionais"
                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                    >
                        {paginator.data.map((professional) => (
                            <article
                                key={professional.id}
                                className="surface-panel flex min-h-52 flex-col gap-5 p-5 transition-colors hover:border-primary/40"
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
                                                {professional.name}
                                            </h2>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {professional.phone ||
                                                    professional.email ||
                                                    'Sem contato informado'}
                                            </p>
                                        </div>
                                    </div>
                                    <StatusBadge status={professional.status} />
                                </div>
                                <div className="space-y-2">
                                    <p className="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                                        Serviços habilitados
                                    </p>
                                    <RelationList
                                        items={professional.services}
                                    />
                                </div>
                                <div className="mt-auto flex justify-end border-t border-border pt-4">
                                    <Button asChild variant="outline" size="sm">
                                        <Link
                                            href={professionals.show(
                                                professional.id,
                                            )}
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

ProfessionalsIndex.layout = {
    breadcrumbs: [{ title: 'Profissionais', href: professionals.index() }],
};
