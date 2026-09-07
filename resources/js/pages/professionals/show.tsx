import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    Clock,
    Lock,
    Mail,
    Phone,
    Plus,
    Search,
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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { ImageUploader } from '@/components/ui/image-uploader';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import professionals from '@/routes/professionals';
import services from '@/routes/services';
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
    avatar_url?: string | null;
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
    const [selectedAvatar, setSelectedAvatar] = useState<File | string | null>(
        professional.avatar_url ?? null,
    );
    const getInitials = useInitials();
    const [updateKey] = useState(() =>
        createIdempotencyKey('professional-update'),
    );
    const [destroyKey] = useState(() =>
        createIdempotencyKey('professional-destroy'),
    );
    const [reactivateKey] = useState(() =>
        createIdempotencyKey('professional-reactivate'),
    );
    const [inactivateOpen, setInactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);
    const [serviceSearch, setServiceSearch] = useState('');
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('professional.manage');
    const availableServices =
        serviceOptions ?? options?.services ?? professional.services;
    const hasServiceOptions =
        serviceOptions !== undefined || options?.services !== undefined;
    const filteredServices = availableServices.filter((service) =>
        service.name
            .toLocaleLowerCase()
            .includes(serviceSearch.toLocaleLowerCase()),
    );

    return (
        <>
            <Head title={professional.name} />
            <PageCanvas>
                <div>
                    <Button asChild variant="ghost" className="-ml-3 mb-4">
                        <Link href={professionals.index()}>
                            <ArrowLeft aria-hidden="true" />
                            Voltar para profissionais
                        </Link>
                    </Button>
                    <div className="flex items-center gap-4">
                        <Avatar className="size-16 shrink-0">
                            {professional.avatar_url ? (
                                <AvatarImage
                                    src={professional.avatar_url}
                                    alt={professional.name}
                                />
                            ) : null}
                            <AvatarFallback className="bg-secondary text-secondary-foreground text-lg font-medium">
                                {getInitials(professional.name) || (
                                    <UserRound
                                        aria-hidden="true"
                                        className="size-8"
                                    />
                                )}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <ResourceHeader
                                eyebrow="Cadastro de profissional"
                                title={professional.name}
                                description="Mantenha os dados da equipe e os serviços que podem ser selecionados na agenda."
                                action={
                                    <StatusBadge status={professional.status} />
                                }
                            />
                        </div>
                    </div>
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <div className="space-y-5">
                        <section className="surface-panel p-5 sm:p-6">
                            <div className="mb-6 space-y-1">
                                <h2 className="text-base font-semibold">
                                    Dados principais
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    Profissionais inativos deixam de aparecer em
                                    novos agendamentos.
                                </p>
                            </div>
                            <Form
                                {...professionals.update.form(professional.id)}
                                id="professional-update-form"
                                headers={{ 'X-Idempotency-Key': updateKey }}
                                className="space-y-5"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormErrorSummary errors={errors} />
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="sm:col-span-2">
                                                <FormField
                                                    label="Foto do profissional"
                                                    name="avatar"
                                                    error={errors.avatar}
                                                >
                                                    <ImageUploader
                                                        value={selectedAvatar}
                                                        onChange={
                                                            setSelectedAvatar
                                                        }
                                                        disabled={!canManage}
                                                        error={errors.avatar}
                                                        aspectRatio="square"
                                                        previewHeight="140px"
                                                    />
                                                </FormField>
                                            </div>
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
                                                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-11 w-full rounded-md border bg-transparent px-3 text-base outline-none focus-visible:ring-[3px] md:text-sm"
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
                                        {!hasServiceOptions ? (
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
                                                <p className="border-border bg-muted/40 text-muted-foreground rounded-lg border border-dashed px-3 py-2 text-xs leading-5">
                                                    Os vínculos atuais são
                                                    preservados ao salvar. A
                                                    seleção ficará disponível
                                                    quando as opções da unidade
                                                    forem carregadas.
                                                </p>
                                            </>
                                        ) : null}
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
                                                className="border-border bg-muted/40 text-muted-foreground rounded-lg border border-dashed px-3 py-2 text-sm"
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
                                    <p className="text-muted-foreground text-sm">
                                        Horários semanais de atendimento
                                        configurados para este profissional.
                                    </p>
                                </div>
                                <Clock
                                    className="text-muted-foreground size-5"
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
                                                    : 'border-border/80 bg-muted/20 text-muted-foreground border-dashed'
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
                                                    <span className="text-muted-foreground text-[10px]">
                                                        Folga
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-2 space-y-1">
                                                {dayRules.length > 0 ? (
                                                    dayRules.map((rule) => (
                                                        <div
                                                            key={rule.id}
                                                            className="text-foreground text-xs font-medium"
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
                                                    <p className="text-muted-foreground text-xs italic">
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
                                    <p className="text-muted-foreground text-sm">
                                        Pausas operacionais, consultas e
                                        ausências registradas na agenda.
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
                                                    ).toLocaleTimeString(
                                                        'pt-BR',
                                                        {
                                                            timeStyle: 'short',
                                                        },
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="border-border bg-muted/20 rounded-lg border border-dashed px-4 py-6 text-center">
                                    <p className="text-muted-foreground text-xs">
                                        Nenhum bloqueio ou ausência programada
                                        para este profissional.
                                    </p>
                                </div>
                            )}
                        </section>
                    </div>

                    <aside className="space-y-5">
                        <section className="surface-panel p-5 sm:p-6">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Serviços habilitados
                                    </h2>
                                    <p className="text-muted-foreground mt-1 text-sm leading-6">
                                        Selecione os serviços que este
                                        profissional realiza.
                                    </p>
                                </div>
                                {canManage ? (
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="secondary"
                                    >
                                        <Link href={services.index()}>
                                            <Plus aria-hidden="true" />
                                            Novo serviço
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                            {hasServiceOptions ? (
                                <div className="mt-5 space-y-3">
                                    <div className="relative">
                                        <Search
                                            aria-hidden="true"
                                            className="text-muted-foreground pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2"
                                        />
                                        <Input
                                            aria-label="Pesquisar serviços"
                                            placeholder="Pesquisar serviço"
                                            value={serviceSearch}
                                            onChange={(event) =>
                                                setServiceSearch(
                                                    event.target.value,
                                                )
                                            }
                                            className="pl-9"
                                        />
                                    </div>
                                    <RelationCheckboxes
                                        name="service_ids"
                                        options={filteredServices}
                                        selectedIds={professional.services.map(
                                            (service) => service.id,
                                        )}
                                        disabled={!canManage}
                                        form="professional-update-form"
                                    />
                                    {canManage ? (
                                        <p className="text-muted-foreground text-xs">
                                            As alterações serão aplicadas ao
                                            clicar em “Salvar alterações”.
                                        </p>
                                    ) : null}
                                </div>
                            ) : (
                                <div className="mt-5">
                                    <RelationList
                                        items={professional.services}
                                        emptyLabel="Nenhum serviço vinculado"
                                    />
                                </div>
                            )}
                        </section>
                        <section className="surface-panel p-5 sm:p-6">
                            <h2 className="text-base font-semibold">
                                Resumo de contato
                            </h2>
                            <div className="mt-5 grid gap-4">
                                <div className="flex items-start gap-3">
                                    <Phone
                                        aria-hidden="true"
                                        className="text-muted-foreground mt-0.5 size-4"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-muted-foreground text-xs">
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
                                        className="text-muted-foreground mt-0.5 size-4"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-muted-foreground text-xs">
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
                                        className="text-muted-foreground mt-0.5 size-4"
                                    />
                                    <div className="min-w-0">
                                        <p className="text-muted-foreground text-xs">
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
                            professional.status === 'inactive' ? (
                                <section className="surface-panel border-emerald-500/30 bg-emerald-50/20 p-5 sm:p-6 dark:bg-emerald-950/20">
                                    <div className="flex items-start gap-3">
                                        <UserRound
                                            aria-hidden="true"
                                            className="mt-0.5 size-4 text-emerald-600 dark:text-emerald-400"
                                        />
                                        <div>
                                            <h2 className="text-foreground text-base font-semibold">
                                                Reativar profissional
                                            </h2>
                                            <p className="text-muted-foreground mt-1 text-sm leading-6">
                                                Este profissional está
                                                atualmente inativo. Reative o
                                                cadastro para disponibilizá-lo
                                                novamente na agenda e
                                                atendimentos.
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
                                                    Reativar profissional?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    O profissional voltará a
                                                    ficar ativo e poderá ser
                                                    selecionado em novos
                                                    agendamentos e atendimentos.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <Form
                                                {...professionals.reactivate.form(
                                                    professional.id,
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
                                                                professional.lock_version
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
                                            className="text-destructive mt-0.5 size-4"
                                        />
                                        <div>
                                            <h2 className="text-base font-semibold">
                                                Desativar profissional
                                            </h2>
                                            <p className="text-muted-foreground mt-1 text-sm leading-6">
                                                O histórico permanece disponível
                                                e o status pode ser reativado.
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
                                                Desativar profissional
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Desativar profissional?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    O profissional deixará de
                                                    aparecer em novos
                                                    agendamentos, preservando o
                                                    histórico existente.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <Form
                                                {...professionals.destroy.form(
                                                    professional.id,
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
                                                                professional.lock_version
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
