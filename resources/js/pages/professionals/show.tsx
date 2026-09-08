import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    Clock,
    Check,
    Lock,
    Mail,
    Phone,
    Plus,
    Search,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import {
    destroy as destroyAvailabilityRule,
    store as storeAvailabilityRule,
    update as updateAvailabilityRule,
} from '@/routes/availability_rules';
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

type AvailabilityEditorProps = {
    canConfigure: boolean;
    day: (typeof weekdays)[number];
    onClose: () => void;
    professional: Professional;
    rules: AvailabilityRuleSummary[];
    timezone: string;
};

function minutesBetween(start: string, end: string): number {
    const [startHour, startMinute] = start.split(':').map(Number);
    const [endHour, endMinute] = end.split(':').map(Number);

    return Math.max(0, endHour * 60 + endMinute - startHour * 60 - startMinute);
}

function AvailabilityEditor({
    canConfigure,
    day,
    onClose,
    professional,
    rules,
    timezone,
}: AvailabilityEditorProps) {
    const [draftCount, setDraftCount] = useState(1);
    const [mutationKey] = useState(() =>
        createIdempotencyKey(`availability-rule-${day.day}`),
    );

    const goBackToProfessional = () => {
        router.visit(professionals.show(professional.id));
    };

    return (
        <div className="space-y-4">
            <div className="rounded-xl border border-primary/20 bg-primary/5 px-4 py-3">
                <div className="flex items-center gap-2 text-sm font-semibold">
                    <Clock className="size-4 text-primary" aria-hidden="true" />
                    {day.name}
                </div>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    Adicione intervalos separados para almoço, pausas ou
                    jornadas divididas. Sem intervalos ativos, o dia fica como
                    folga.
                </p>
            </div>

            {rules.map((rule) => (
                <Form
                    key={rule.id}
                    {...updateAvailabilityRule.form(rule.id)}
                    headers={{ 'X-Idempotency-Key': mutationKey }}
                    className="rounded-xl border border-border bg-card p-4"
                    onSuccess={goBackToProfessional}
                >
                    {({ errors, processing }) => (
                        <>
                            <FormErrorSummary errors={errors} />
                            <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                <FormField
                                    label="Início"
                                    name="starts_at"
                                    error={errors.starts_at}
                                >
                                    <Input
                                        name="starts_at"
                                        type="time"
                                        defaultValue={rule.starts_at.slice(
                                            0,
                                            5,
                                        )}
                                        required
                                        disabled={!canConfigure}
                                    />
                                </FormField>
                                <FormField
                                    label="Fim"
                                    name="ends_at"
                                    error={errors.ends_at}
                                >
                                    <Input
                                        name="ends_at"
                                        type="time"
                                        defaultValue={rule.ends_at.slice(0, 5)}
                                        required
                                        disabled={!canConfigure}
                                    />
                                </FormField>
                                <div className="flex gap-2 sm:pb-0.5">
                                    <input
                                        type="hidden"
                                        name="professional_id"
                                        value={professional.id}
                                    />
                                    <input
                                        type="hidden"
                                        name="weekday"
                                        value={day.day}
                                    />
                                    <input
                                        type="hidden"
                                        name="timezone"
                                        value={timezone}
                                    />
                                    <input
                                        type="hidden"
                                        name="status"
                                        value="active"
                                    />
                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value={rule.lock_version}
                                    />
                                    <Button
                                        type="submit"
                                        size="icon"
                                        aria-label={`Salvar intervalo de ${day.name}`}
                                        disabled={!canConfigure || processing}
                                    >
                                        <Check aria-hidden="true" />
                                    </Button>
                                    <Form
                                        {...destroyAvailabilityRule.form(
                                            rule.id,
                                        )}
                                        headers={{
                                            'X-Idempotency-Key': mutationKey,
                                        }}
                                        onSuccess={goBackToProfessional}
                                    >
                                        {({ processing: deleting }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="lock_version"
                                                    value={rule.lock_version}
                                                />
                                                <Button
                                                    type="submit"
                                                    size="icon"
                                                    variant="outline"
                                                    aria-label={`Remover intervalo de ${day.name}`}
                                                    disabled={
                                                        !canConfigure ||
                                                        deleting
                                                    }
                                                >
                                                    <Trash2 aria-hidden="true" />
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            ))}

            {Array.from({ length: draftCount }).map((_, index) => (
                <Form
                    key={`new-${index}`}
                    {...storeAvailabilityRule.form()}
                    headers={{ 'X-Idempotency-Key': `${mutationKey}-${index}` }}
                    className="rounded-xl border border-dashed border-primary/35 bg-primary/[0.03] p-4"
                    onSuccess={goBackToProfessional}
                >
                    {({ errors, processing }) => (
                        <>
                            {index === 0 ? (
                                <p className="mb-3 text-xs font-semibold text-primary">
                                    Novo intervalo
                                </p>
                            ) : null}
                            <FormErrorSummary errors={errors} />
                            <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                <FormField
                                    label="Início"
                                    name="starts_at"
                                    error={errors.starts_at}
                                >
                                    <Input
                                        name="starts_at"
                                        type="time"
                                        defaultValue="09:00"
                                        required
                                        disabled={!canConfigure}
                                    />
                                </FormField>
                                <FormField
                                    label="Fim"
                                    name="ends_at"
                                    error={errors.ends_at}
                                >
                                    <Input
                                        name="ends_at"
                                        type="time"
                                        defaultValue="18:00"
                                        required
                                        disabled={!canConfigure}
                                    />
                                </FormField>
                                <div className="sm:pb-0.5">
                                    <input
                                        type="hidden"
                                        name="professional_id"
                                        value={professional.id}
                                    />
                                    <input
                                        type="hidden"
                                        name="weekday"
                                        value={day.day}
                                    />
                                    <input
                                        type="hidden"
                                        name="timezone"
                                        value={timezone}
                                    />
                                    <input
                                        type="hidden"
                                        name="status"
                                        value="active"
                                    />
                                    <Button
                                        type="submit"
                                        size="icon"
                                        aria-label={`Criar intervalo de ${day.name}`}
                                        disabled={!canConfigure || processing}
                                    >
                                        <Check aria-hidden="true" />
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            ))}

            {canConfigure ? (
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    onClick={() => setDraftCount((count) => count + 1)}
                >
                    <Plus aria-hidden="true" /> Adicionar outro intervalo
                </Button>
            ) : null}
            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onClose}>
                    Fechar
                </Button>
            </DialogFooter>
        </div>
    );
}

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
    const [servicesDialogOpen, setServicesDialogOpen] = useState(false);
    const [serviceSearch, setServiceSearch] = useState('');
    const [availabilityDay, setAvailabilityDay] = useState<
        (typeof weekdays)[number] | null
    >(null);
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('professional.manage');
    const canConfigureCalendar =
        props.auth.permissions.includes('calendar.configure');
    const availabilityRules =
        professional.availability_rules ?? professional.availabilityRules ?? [];
    const activeAvailabilityRules = availabilityRules.filter(
        (rule) => rule.status === 'active',
    );
    const timezone =
        activeAvailabilityRules[0]?.timezone ??
        props.workspace?.activeUnit?.timezone ??
        props.workspace?.tenant.timezone ??
        'UTC';
    const weeklySummary = useMemo(() => {
        const totalMinutes = activeAvailabilityRules.reduce(
            (total, rule) =>
                total + minutesBetween(rule.starts_at, rule.ends_at),
            0,
        );

        return {
            activeDays: new Set(
                activeAvailabilityRules.map((rule) => rule.weekday),
            ).size,
            hours: Math.floor(totalMinutes / 60),
            minutes: totalMinutes % 60,
        };
    }, [activeAvailabilityRules]);
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
                    <Button asChild variant="ghost" className="mb-4 -ml-3">
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
                            <AvatarFallback className="bg-secondary text-lg font-medium text-secondary-foreground">
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
                                <p className="text-sm text-muted-foreground">
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
                                                <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground">
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
                            <div className="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Jornada de trabalho e disponibilidade
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Horários semanais de atendimento
                                        configurados para este profissional.
                                    </p>
                                </div>
                                <div className="grid grid-cols-2 gap-2 sm:min-w-48">
                                    <div className="rounded-lg bg-primary/10 px-3 py-2">
                                        <p className="text-[10px] font-semibold tracking-wide text-primary uppercase">
                                            Dias ativos
                                        </p>
                                        <p className="mt-1 text-lg font-semibold">
                                            {weeklySummary.activeDays}
                                            <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                / 7
                                            </span>
                                        </p>
                                    </div>
                                    <div className="rounded-lg bg-emerald-500/10 px-3 py-2">
                                        <p className="text-[10px] font-semibold tracking-wide text-emerald-700 uppercase dark:text-emerald-400">
                                            Horas/semana
                                        </p>
                                        <p className="mt-1 text-lg font-semibold">
                                            {weeklySummary.hours}h
                                            {weeklySummary.minutes
                                                ? ` ${weeklySummary.minutes}m`
                                                : ''}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {!canConfigureCalendar ? (
                                <p className="mb-4 rounded-lg border border-dashed border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                                    Consulta liberada. A edição exige a
                                    permissão calendar.configure.
                                </p>
                            ) : null}
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {weekdays.map((weekday) => {
                                    const dayRules =
                                        activeAvailabilityRules.filter(
                                            (rule) =>
                                                rule.weekday === weekday.day,
                                        );
                                    const canEdit = canConfigureCalendar;

                                    return (
                                        <button
                                            key={weekday.day}
                                            type="button"
                                            className={`group min-h-28 rounded-lg border p-3 text-left transition hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-sm focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 ${dayRules.length > 0 ? 'border-border bg-card' : 'border-dashed border-border/80 bg-muted/20 text-muted-foreground'}`}
                                            onClick={() =>
                                                setAvailabilityDay(weekday)
                                            }
                                            aria-label={`${canEdit ? 'Editar' : 'Consultar'} disponibilidade de ${weekday.name}`}
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-xs font-semibold">
                                                    {weekday.short} –{' '}
                                                    {weekday.name}
                                                </span>
                                                <span
                                                    className={`rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${dayRules.length > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}
                                                >
                                                    {dayRules.length > 0
                                                        ? 'Ativo'
                                                        : 'Folga'}
                                                </span>
                                            </div>
                                            <div className="mt-3 space-y-1">
                                                {dayRules.length > 0 ? (
                                                    dayRules.map((rule) => (
                                                        <p
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
                                                        </p>
                                                    ))
                                                ) : (
                                                    <p className="text-xs italic">
                                                        Sem atendimento
                                                    </p>
                                                )}
                                            </div>
                                            <p className="mt-3 text-[10px] font-medium text-muted-foreground group-hover:text-primary">
                                                {canEdit
                                                    ? dayRules.length > 0
                                                        ? 'Editar horários'
                                                        : 'Adicionar horário'
                                                    : 'Somente consulta'}
                                            </p>
                                        </button>
                                    );
                                })}
                            </div>
                        </section>

                        <Dialog
                            open={availabilityDay !== null}
                            onOpenChange={(open) => {
                                if (!open) {
                                    setAvailabilityDay(null);
                                }
                            }}
                        >
                            <DialogContent className="max-h-[calc(100dvh-1rem)] w-[calc(100%-1rem)] overflow-y-auto p-4 sm:max-w-xl sm:p-6">
                                <DialogHeader>
                                    <DialogTitle>
                                        Disponibilidade semanal
                                    </DialogTitle>
                                    <DialogDescription>
                                        Edite os intervalos de atendimento ou
                                        adicione uma nova jornada para o dia
                                        selecionado.
                                    </DialogDescription>
                                </DialogHeader>
                                {availabilityDay ? (
                                    <AvailabilityEditor
                                        canConfigure={canConfigureCalendar}
                                        day={availabilityDay}
                                        onClose={() => setAvailabilityDay(null)}
                                        professional={professional}
                                        rules={activeAvailabilityRules.filter(
                                            (rule) =>
                                                rule.weekday ===
                                                availabilityDay.day,
                                        )}
                                        timezone={timezone}
                                    />
                                ) : null}
                            </DialogContent>
                        </Dialog>

                        <section className="surface-panel p-5 sm:p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Bloqueios e pausas programadas
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
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
                                <div className="rounded-lg border border-dashed border-border bg-muted/20 px-4 py-6 text-center">
                                    <p className="text-xs text-muted-foreground">
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
                                <div className="min-w-0">
                                    <h2 className="text-base font-semibold">
                                        Serviços deste profissional
                                    </h2>
                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        Disponíveis para seleção na agenda.
                                    </p>
                                </div>
                                <span className="rounded-full bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground">
                                    {professional.services.length}
                                </span>
                            </div>
                            <div className="mt-5 space-y-3">
                                <RelationList
                                    items={professional.services}
                                    emptyLabel="Nenhum serviço vinculado"
                                />
                                {canManage && hasServiceOptions ? (
                                    <Dialog
                                        open={servicesDialogOpen}
                                        onOpenChange={(open) => {
                                            setServicesDialogOpen(open);

                                            if (!open) {
                                                setServiceSearch('');
                                            }
                                        }}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                className="w-full"
                                                variant="secondary"
                                            >
                                                <Plus aria-hidden="true" />
                                                {professional.services.length >
                                                0
                                                    ? 'Gerenciar serviços'
                                                    : 'Adicionar primeiro serviço'}
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent className="max-h-[calc(100dvh-1rem)] w-[calc(100%-1rem)] overflow-y-auto p-4 sm:w-full sm:max-w-lg sm:p-6">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Adicionar serviços
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Selecione os serviços que{' '}
                                                    {professional.name} realiza.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <div className="space-y-4">
                                                <div className="relative">
                                                    <Search
                                                        aria-hidden="true"
                                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                    />
                                                    <Input
                                                        aria-label="Pesquisar serviços"
                                                        placeholder="Pesquisar serviço"
                                                        value={serviceSearch}
                                                        onChange={(event) =>
                                                            setServiceSearch(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="pl-9"
                                                        autoFocus
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
                                            </div>
                                            <DialogFooter className="sticky bottom-0 -mx-4 -mb-4 flex-col gap-2 border-t border-border bg-background/95 p-4 backdrop-blur sm:static sm:m-0 sm:flex-row sm:justify-between sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    className="w-full sm:w-auto"
                                                >
                                                    <Link
                                                        href={services.index()}
                                                    >
                                                        <Plus aria-hidden="true" />
                                                        Criar novo serviço
                                                    </Link>
                                                </Button>
                                                <Button
                                                    type="button"
                                                    className="w-full sm:w-auto"
                                                    onClick={() =>
                                                        setServicesDialogOpen(
                                                            false,
                                                        )
                                                    }
                                                >
                                                    Concluir seleção
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                ) : null}
                            </div>
                            {!hasServiceOptions ? (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    As opções de seleção ainda não foram
                                    carregadas.
                                </p>
                            ) : null}
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
                            professional.status === 'inactive' ? (
                                <section className="surface-panel border-emerald-500/30 bg-emerald-50/20 p-5 sm:p-6 dark:bg-emerald-950/20">
                                    <div className="flex items-start gap-3">
                                        <UserRound
                                            aria-hidden="true"
                                            className="mt-0.5 size-4 text-emerald-600 dark:text-emerald-400"
                                        />
                                        <div>
                                            <h2 className="text-base font-semibold text-foreground">
                                                Reativar profissional
                                            </h2>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
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
                                            className="mt-0.5 size-4 text-destructive"
                                        />
                                        <div>
                                            <h2 className="text-base font-semibold">
                                                Desativar profissional
                                            </h2>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
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
