import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    Mail,
    Phone,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
    RelationCheckboxes,
    RelationList,
    ResourceHeader,
    StatusBadge,
} from '@/components/operational';
import type { RelationOption, ResourceStatus } from '@/components/operational';
import { Button } from '@/components/ui/button';
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
    lock_version: number;
    name: string;
    phone: string | null;
    services: ServiceSummary[];
    status: ResourceStatus;
};

type Props = {
    options?: {
        services?: RelationOption[];
    };
    professional: Professional;
    serviceOptions?: RelationOption[];
};

export default function ProfessionalShow({
    professional,
    options,
    serviceOptions,
}: Props) {
    const [updateKey] = useState(() =>
        createIdempotencyKey('professional-update'),
    );
    const [destroyKey] = useState(() =>
        createIdempotencyKey('professional-destroy'),
    );
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('professional.manage');
    const availableServices =
        serviceOptions ?? options?.services ?? professional.services;
    const hasServiceOptions =
        serviceOptions !== undefined || options?.services !== undefined;

    return (
        <>
            <Head title={professional.name} />
            <PageCanvas>
                <div>
                    <Button asChild variant="ghost" className="mb-4 -ml-3">
                        <Link href={professionals.index()}>
                            <ArrowLeft aria-hidden="true" />
                            Voltar para profissionais
                        </Link>
                    </Button>
                    <ResourceHeader
                        eyebrow="Cadastro de profissional"
                        title={professional.name}
                        description="Mantenha os dados da equipe e os serviços que podem ser selecionados na agenda."
                        action={<StatusBadge status={professional.status} />}
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <section className="surface-panel p-5 sm:p-6">
                        <div className="mb-6 space-y-1">
                            <h2 className="text-base font-semibold">
                                Dados principais
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Profissionais inativos deixam de aparecer em
                                novos agendamentos.
                            </p>
                        </div>
                        <Form
                            {...professionals.update.form(professional.id)}
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
                                                    defaultValue={
                                                        professional.name
                                                    }
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
                                                    professional.email ?? ''
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
                                                    professional.phone ?? ''
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
                                                defaultValue={
                                                    professional.status
                                                }
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
                                    </div>
                                    {hasServiceOptions ? (
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium text-foreground">
                                                Serviços habilitados
                                            </p>
                                            <RelationCheckboxes
                                                name="service_ids"
                                                options={availableServices}
                                                selectedIds={professional.services.map(
                                                    (service) => service.id,
                                                )}
                                                disabled={!canManage}
                                            />
                                        </div>
                                    ) : (
                                        <>
                                            {professional.services.map(
                                                (service) => (
                                                    <input
                                                        key={service.id}
                                                        type="hidden"
                                                        name="service_ids[]"
                                                        value={service.id}
                                                    />
                                                ),
                                            )}
                                            <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground">
                                                Os vínculos atuais são
                                                preservados ao salvar. A seleção
                                                ficará disponível quando as
                                                opções da unidade forem
                                                carregadas.
                                            </p>
                                        </>
                                    )}
                                    {canManage ? (
                                        <>
                                            <input
                                                type="hidden"
                                                name="lock_version"
                                                value={
                                                    professional.lock_version
                                                }
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

                    <aside className="space-y-5">
                        <section className="surface-panel p-5 sm:p-6">
                            <h2 className="text-base font-semibold">
                                Serviços habilitados
                            </h2>
                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                A agenda usará estes vínculos para oferecer
                                escolhas válidas.
                            </p>
                            <div className="mt-5">
                                <RelationList
                                    items={professional.services}
                                    emptyLabel="Nenhum serviço vinculado"
                                />
                            </div>
                        </section>
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
                                            {professional.phone ||
                                                'Não informado'}
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
                                            {professional.email ||
                                                'Não informado'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <BriefcaseBusiness
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-xs text-muted-foreground">
                                            Vínculos
                                        </p>
                                        <p className="mt-0.5 text-sm font-medium">
                                            {professional.services.length}{' '}
                                            {professional.services.length === 1
                                                ? 'serviço'
                                                : 'serviços'}
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
                                            Desativar profissional
                                        </h2>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            O histórico permanece disponível e o
                                            status pode ser reativado.
                                        </p>
                                    </div>
                                </div>
                                <Form
                                    {...professionals.destroy.form(
                                        professional.id,
                                    )}
                                    headers={{
                                        'X-Idempotency-Key': destroyKey,
                                    }}
                                    className="mt-4"
                                    onSubmit={(event) => {
                                        if (
                                            !window.confirm(
                                                'Desativar este profissional?',
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
                                                value={
                                                    professional.lock_version
                                                }
                                            />
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                {processing
                                                    ? 'Desativando…'
                                                    : 'Desativar profissional'}
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

ProfessionalShow.layout = {
    breadcrumbs: [
        { title: 'Profissionais', href: professionals.index() },
        { title: 'Cadastro', href: professionals.index() },
    ],
};
