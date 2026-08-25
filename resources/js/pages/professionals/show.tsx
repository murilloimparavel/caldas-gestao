import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    Clock,
    Lock,
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

type AvailabilityRuleSummary = {
    ends_at: string;
    id: string;
    lock_version: number;
    starts_at: string;
    status: string;
    timezone: string;
    weekday: number;
};

type ScheduleBlockSummary = {
    ends_at: string;
    id: string;
    lock_version: number;
    reason?: string | null;
    starts_at: string;
    status: string;
    timezone: string;
};

type Professional = {
    availability_rules?: AvailabilityRuleSummary[];
    availabilityRules?: AvailabilityRuleSummary[];
    email: string | null;
    id: string;
    lock_version: number;
    name: string;
    phone: string | null;
    schedule_blocks?: ScheduleBlockSummary[];
    scheduleBlocks?: ScheduleBlockSummary[];
    services: ServiceSummary[];
    status: ResourceStatus;
};

const weekdays = [
    { day: 0, name: 'Domingo', short: 'Dom' },
    { day: 1, name: 'Segunda-feira', short: 'Seg' },
    { day: 2, name: 'Terça-feira', short: 'Ter' },
    { day: 3, name: 'Quarta-feira', short: 'Qua' },
    { day: 4, name: 'Quinta-feira', short: 'Qui' },
    { day: 5, name: 'Sexta-feira', short: 'Sex' },
    { day: 6, name: 'Sábado', short: 'Sáb' },
];


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
                    <div className="space-y-5">
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

                    <section className="surface-panel p-5 sm:p-6">
                        <div className="mb-5 flex items-center justify-between">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Jornada de trabalho e disponibilidade
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Horários semanais de atendimento
                                    configurados para este profissional.
                                </p>
                            </div>
                            <Clock
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {weekdays.map(({ day, name, short }) => {
                                const dayRules = (
                                    professional.availability_rules ??
                                    professional.availabilityRules ??
                                    []
                                ).filter(
                                    (rule) =>
                                        rule.weekday === day &&
                                        rule.status === 'active',
                                );

                                return (
                                    <div
                                        key={day}
                                        className={`rounded-lg border p-3 ${
                                            dayRules.length > 0
                                                ? 'border-border bg-card'
                                                : 'border-dashed border-border/80 bg-muted/20 text-muted-foreground'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-semibold">
                                                {short} – {name}
                                            </span>
                                            {dayRules.length > 0 ? (
                                                <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    Ativo
                                                </span>
                                            ) : (
                                                <span className="text-[10px] text-muted-foreground">
                                                    Folga
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-2 space-y-1">
                                            {dayRules.length > 0 ? (
                                                dayRules.map((rule) => (
                                                    <div
                                                        key={rule.id}
                                                        className="text-xs font-medium text-foreground"
                                                    >
                                                        {rule.starts_at.slice(
                                                            0,
                                                            5,
                                                        )}{' '}
                                                        –{' '}
                                                        {rule.ends_at.slice(
                                                            0,
                                                            5,
                                                        )}
                                                    </div>
                                                ))
                                            ) : (
                                                <p className="text-xs italic text-muted-foreground">
                                                    Sem atendimento
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    <section className="surface-panel p-5 sm:p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Bloqueios e pausas programadas
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Pausas operacionais, consultas e ausências
                                    registradas na agenda.
                                </p>
                            </div>
                            <Lock
                                className="size-5 text-amber-600 dark:text-amber-400"
                                aria-hidden="true"
                            />
                        </div>

                        {(
                            professional.schedule_blocks ??
                            professional.scheduleBlocks ??
                            []
                        ).length > 0 ? (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {(
                                    professional.schedule_blocks ??
                                    professional.scheduleBlocks ??
                                    []
                                ).map((block) => (
                                    <div
                                        key={block.id}
                                        className="flex items-start gap-3 rounded-lg border border-amber-300/60 bg-amber-50/70 p-3 text-xs dark:border-amber-700/60 dark:bg-amber-950/40"
                                    >
                                        <Lock
                                            className="mt-0.5 size-3.5 shrink-0 text-amber-700 dark:text-amber-400"
                                            aria-hidden="true"
                                        />
                                        <div className="min-w-0">
                                            <p className="font-semibold text-amber-950 dark:text-amber-100">
                                                {block.reason ||
                                                    'Bloqueio de horário'}
                                            </p>
                                            <p className="mt-0.5 text-amber-900/80 dark:text-amber-300/80">
                                                {new Date(
                                                    block.starts_at,
                                                ).toLocaleString('pt-BR', {
                                                    dateStyle: 'short',
                                                    timeStyle: 'short',
                                                })}{' '}
                                                –{' '}
                                                {new Date(
                                                    block.ends_at,
                                                ).toLocaleTimeString('pt-BR', {
                                                    timeStyle: 'short',
                                                })}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-lg border border-dashed border-border bg-muted/20 px-4 py-6 text-center">
                                <p className="text-xs text-muted-foreground">
                                    Nenhum bloqueio ou ausência programada para
                                    este profissional.
                                </p>
                            </div>
                        )}
                    </section>
                    </div>

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
