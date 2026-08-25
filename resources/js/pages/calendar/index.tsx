import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Plus, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import {
    CalendarError,
    CalendarLoading,
    CalendarToolbar,
    DayAgenda,
    EmptyCalendar,
    FilterSummary,
    MonthAgenda,
    StatusChip,
    WeekCalendar,
    addDays,
    dateKey,
    dateTimeValue,
    statusLabel,
} from '@/components/calendar';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    PageCanvas,
} from '@/components/operational';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    cancel as cancelAppointment,
    store as storeAppointment,
    update as updateAppointment,
} from '@/routes/appointments';
import { index as calendarIndex } from '@/routes/calendar';
import type { SharedPageProps } from '@/types';
import type {
    AppointmentStatus,
    CalendarAppointment,
    CalendarOption,
    CalendarProps,
} from '@/types/calendar';

const statusOptions: AppointmentStatus[] = [
    'draft',
    'scheduled',
    'confirmed',
    'checked_in',
    'in_service',
    'completed',
    'no_show',
    'cancelled',
];

function optionList(
    primary: CalendarOption[] | undefined,
    fallback: CalendarOption[] | undefined,
): CalendarOption[] {
    return primary ?? fallback ?? [];
}

function AppointmentForm({
    appointment,
    customers,
    onClose,
    professionals,
    services,
    unitTimezone,
}: {
    appointment: CalendarAppointment | null;
    customers: CalendarOption[];
    onClose: () => void;
    professionals: CalendarOption[];
    services: CalendarOption[];
    unitTimezone: string;
}) {
    const [mutationKey] = useState(() =>
        createIdempotencyKey(
            appointment
                ? `appointment-update:${appointment.id}`
                : 'appointment-create',
        ),
    );
    const isEditing = appointment !== null;
    const route = isEditing
        ? updateAppointment.form(appointment.id)
        : storeAppointment.form();

    return (
        <Form
            {...route}
            headers={{ 'X-Idempotency-Key': mutationKey }}
            className="space-y-5"
            onSuccess={onClose}
        >
            {({ errors, processing }) => (
                <>
                    <FormErrorSummary errors={errors} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <FormField
                                label="Cliente"
                                name="customer_id"
                                error={errors.customer_id}
                            >
                                {customers.length > 0 ? (
                                    <select
                                        id="customer_id"
                                        name="customer_id"
                                        defaultValue={
                                            appointment?.customer_id ??
                                            appointment?.customer?.id ??
                                            ''
                                        }
                                        required
                                        className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="">
                                            Selecione um cliente
                                        </option>
                                        {customers.map((customer) => (
                                            <option
                                                key={customer.id}
                                                value={customer.id}
                                            >
                                                {customer.name}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <Input
                                        id="customer_id"
                                        name="customer_id"
                                        defaultValue={
                                            appointment?.customer_id ??
                                            appointment?.customer?.id ??
                                            ''
                                        }
                                        placeholder="ID do cliente"
                                        required
                                    />
                                )}
                            </FormField>
                        </div>
                        <FormField
                            label="Serviço"
                            name="service_id"
                            error={errors.service_id}
                        >
                            {services.length > 0 ? (
                                <select
                                    id="service_id"
                                    name="service_id"
                                    defaultValue={
                                        appointment?.service_id ??
                                        appointment?.service?.id ??
                                        ''
                                    }
                                    required
                                    className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="">
                                        Selecione um serviço
                                    </option>
                                    {services.map((service) => (
                                        <option
                                            key={service.id}
                                            value={service.id}
                                        >
                                            {service.name}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <Input
                                    id="service_id"
                                    name="service_id"
                                    defaultValue={
                                        appointment?.service_id ??
                                        appointment?.service?.id ??
                                        ''
                                    }
                                    placeholder="ID do serviço"
                                    required
                                />
                            )}
                        </FormField>
                        <FormField
                            label="Profissional"
                            name="professional_id"
                            error={errors.professional_id}
                        >
                            {professionals.length > 0 ? (
                                <select
                                    id="professional_id"
                                    name="professional_id"
                                    defaultValue={
                                        appointment?.professional_id ??
                                        appointment?.professional?.id ??
                                        ''
                                    }
                                    required
                                    className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <option value="">
                                        Selecione um profissional
                                    </option>
                                    {professionals.map((professional) => (
                                        <option
                                            key={professional.id}
                                            value={professional.id}
                                        >
                                            {professional.name}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <Input
                                    id="professional_id"
                                    name="professional_id"
                                    defaultValue={
                                        appointment?.professional_id ??
                                        appointment?.professional?.id ??
                                        ''
                                    }
                                    placeholder="ID do profissional"
                                    required
                                />
                            )}
                        </FormField>
                        <FormField
                            label="Horário"
                            name="starts_at"
                            error={errors.starts_at}
                        >
                            <Input
                                id="starts_at"
                                name="starts_at"
                                type="datetime-local"
                                defaultValue={
                                    appointment
                                        ? dateTimeValue(
                                              appointment.starts_at,
                                              unitTimezone,
                                          )
                                        : ''
                                }
                                required
                            />
                        </FormField>
                        <FormField
                            label="Duração (minutos)"
                            name="duration_minutes"
                            error={errors.duration_minutes}
                        >
                            <Input
                                id="duration_minutes"
                                name="duration_minutes"
                                type="number"
                                min={5}
                                max={1440}
                                step={5}
                                defaultValue={
                                    appointment?.duration_minutes ?? 30
                                }
                                required
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
                                    appointment?.status ?? 'scheduled'
                                }
                                className="h-11 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {statusOptions.map((status) => (
                                    <option key={status} value={status}>
                                        {statusLabel(status)}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                        <div className="flex items-center gap-3 rounded-lg border border-border px-3 py-2 sm:col-span-2">
                            <input
                                id="reminder_enabled"
                                name="reminder_enabled"
                                type="checkbox"
                                defaultChecked={
                                    appointment?.reminder_enabled ?? true
                                }
                                className="size-4 accent-primary"
                            />
                            <label
                                htmlFor="reminder_enabled"
                                className="text-sm"
                            >
                                Enviar lembrete ao cliente
                                <span className="block text-xs text-muted-foreground">
                                    O canal e o consentimento são validados pelo
                                    servidor.
                                </span>
                            </label>
                        </div>
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
                                    defaultValue={appointment?.notes ?? ''}
                                    className="min-h-24 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    placeholder="Informações úteis para o atendimento"
                                />
                            </FormField>
                        </div>
                    </div>
                    {isEditing ? (
                        <input
                            type="hidden"
                            name="lock_version"
                            value={appointment.lock_version}
                        />
                    ) : null}
                    <FormActions
                        processing={processing}
                        onCancel={onClose}
                        label={
                            isEditing
                                ? 'Salvar agendamento'
                                : 'Criar agendamento'
                        }
                    />
                </>
            )}
        </Form>
    );
}

function CancelAppointmentForm({
    appointment,
    onClose,
}: {
    appointment: CalendarAppointment;
    onClose: () => void;
}) {
    const [mutationKey] = useState(() =>
        createIdempotencyKey(`appointment-cancel:${appointment.id}`),
    );

    return (
        <Form
            {...cancelAppointment.form(appointment.id)}
            headers={{ 'X-Idempotency-Key': mutationKey }}
            className="mt-4"
            onSubmit={(event) => {
                if (!window.confirm('Cancelar este agendamento?')) {
                    event.preventDefault();
                }
            }}
            onSuccess={onClose}
        >
            {({ errors, processing }) => (
                <>
                    <input
                        type="hidden"
                        name="lock_version"
                        value={appointment.lock_version}
                    />
                    <FormField
                        label="Motivo do cancelamento"
                        name="cancel_reason"
                        error={errors.cancel_reason}
                    >
                        <Input
                            id="cancel_reason"
                            name="cancel_reason"
                            placeholder="Informe o motivo"
                            required
                        />
                    </FormField>
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={processing}
                        className="mt-3 w-full"
                    >
                        {processing ? 'Cancelando…' : 'Cancelar agendamento'}
                    </Button>
                </>
            )}
        </Form>
    );
}

export default function CalendarIndex(props: CalendarProps) {
    const page = usePage<SharedPageProps & CalendarProps>();
    const [createOpen, setCreateOpen] = useState(false);
    const [filterOpen, setFilterOpen] = useState(false);
    const [editing, setEditing] = useState<CalendarAppointment | null>(null);
    const permissions = page.props.auth.permissions;
    const canManage = permissions.some((permission) =>
        ['appointment.manage', 'calendar.manage'].includes(permission),
    );
    const appointments =
        props.calendar?.appointments ?? props.appointments ?? [];
    const filters = props.calendar?.filters ?? props.filters ?? {};
    const view = filters.view ?? 'week';
    const unitTimezone =
        props.unitTimezone ?? props.calendar?.timezone ?? 'UTC';
    const selectedDate =
        filters.date ??
        props.calendar?.range?.start ??
        props.range?.start ??
        dateKey(new Date().toISOString(), unitTimezone);
    const range = props.calendar?.range ??
        props.range ?? {
            start: selectedDate,
            end: addDays(selectedDate, view === 'week' ? 6 : 0),
        };
    const customers = optionList(props.options?.customers, props.customers);
    const professionals = optionList(
        props.options?.professionals,
        props.professionals,
    );
    const services = optionList(props.options?.services, props.services);
    const loadError = typeof props.error === 'string' ? props.error : undefined;
    const selectedProfessionalIds = filters.professional_ids ?? [];
    const selectedStatuses = filters.status ?? [];
    const visibleAppointments = appointments.filter((appointment) => {
        const matchesProfessional =
            selectedProfessionalIds.length === 0 ||
            selectedProfessionalIds.includes(
                appointment.professional_id ??
                    appointment.professional?.id ??
                    '',
            );
        const matchesStatus =
            selectedStatuses.length === 0 ||
            selectedStatuses.includes(appointment.status);

        return matchesProfessional && matchesStatus;
    });

    return (
        <>
            <Head title="Agenda" />
            <PageCanvas>
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                            Operação diária
                        </p>
                        <h1 className="mt-2 font-display text-3xl leading-tight font-semibold tracking-[-0.035em] sm:text-4xl">
                            Agenda
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                            Organize horários, disponibilidade e próximos
                            atendimentos em uma única visão operacional.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <FilterSummary
                            professionals={professionals.filter(
                                (professional) =>
                                    selectedProfessionalIds.includes(
                                        professional.id,
                                    ),
                            )}
                            statuses={selectedStatuses}
                        />
                        <Button
                            variant="outline"
                            onClick={() => setFilterOpen(true)}
                            className="lg:hidden"
                        >
                            <SlidersHorizontal aria-hidden="true" />
                            Filtros
                        </Button>
                    </div>
                </header>

                <CalendarToolbar
                    canManage={canManage}
                    date={selectedDate}
                    filters={filters}
                    onCreate={() => setCreateOpen(true)}
                    onFilter={() => setFilterOpen(true)}
                    range={range}
                    timeZone={unitTimezone}
                    view={view}
                />

                {loadError ? <CalendarError message={loadError} /> : null}

                <section
                    className="hidden gap-4 lg:block"
                    aria-label="Filtros da agenda"
                >
                    <div className="flex flex-wrap items-center gap-2 rounded-xl border border-border bg-muted/20 px-4 py-3">
                        <span className="text-xs font-semibold text-muted-foreground">
                            Profissionais
                        </span>
                        {professionals.slice(0, 8).map((professional) => (
                            <Button
                                key={professional.id}
                                asChild
                                size="sm"
                                variant={
                                    selectedProfessionalIds.includes(
                                        professional.id,
                                    )
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                className="h-8 text-xs"
                            >
                                <Link
                                    href={calendarIndex({
                                        query: {
                                            ...filters,
                                            date: selectedDate,
                                            view,
                                            professional_ids:
                                                selectedProfessionalIds.includes(
                                                    professional.id,
                                                )
                                                    ? selectedProfessionalIds.filter(
                                                          (id) =>
                                                              id !==
                                                              professional.id,
                                                      )
                                                    : [
                                                          ...selectedProfessionalIds,
                                                          professional.id,
                                                      ],
                                        },
                                    })}
                                >
                                    {professional.name}
                                </Link>
                            </Button>
                        ))}
                        {professionals.length === 0 ? (
                            <span className="text-xs text-muted-foreground">
                                Nenhum profissional disponível.
                            </span>
                        ) : null}
                    </div>
                </section>

                {props.loading ? (
                    <CalendarLoading />
                ) : visibleAppointments.length === 0 ? (
                    <EmptyCalendar
                        action={
                            canManage ? (
                                <Button onClick={() => setCreateOpen(true)}>
                                    <Plus aria-hidden="true" />
                                    Novo agendamento
                                </Button>
                            ) : undefined
                        }
                        description={
                            appointments.length === 0
                                ? 'Comece registrando o primeiro horário para acompanhar a operação do dia.'
                                : 'Tente limpar os filtros ou escolher outro período.'
                        }
                    />
                ) : view === 'month' ? (
                    <MonthAgenda
                        appointments={visibleAppointments}
                        onOpen={setEditing}
                        range={range}
                        timeZone={unitTimezone}
                    />
                ) : view === 'day' ? (
                    <DayAgenda
                        appointments={visibleAppointments}
                        date={selectedDate}
                        onOpen={setEditing}
                        timeZone={unitTimezone}
                    />
                ) : (
                    <>
                        <div className="md:hidden">
                            <DayAgenda
                                appointments={visibleAppointments}
                                date={selectedDate}
                                onOpen={setEditing}
                                timeZone={unitTimezone}
                            />
                        </div>
                        <div className="hidden md:block">
                            <WeekCalendar
                                appointments={visibleAppointments}
                                onOpen={setEditing}
                                range={range}
                                timeZone={unitTimezone}
                            />
                        </div>
                    </>
                )}

                <p className="text-xs leading-5 text-muted-foreground">
                    Horários exibidos no fuso da unidade ({unitTimezone}). A
                    duração é enviada ao servidor para calcular o horário final;
                    conflitos, disponibilidade e permissões são confirmados ao
                    salvar.
                </p>
            </PageCanvas>

            <Dialog
                open={createOpen || editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCreateOpen(false);
                        setEditing(null);
                    }
                }}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? 'Editar agendamento'
                                : 'Novo agendamento'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Atualize os dados e confirme a versão mais recente antes de salvar.'
                                : 'Preencha os dados essenciais para reservar um horário na agenda.'}
                        </DialogDescription>
                    </DialogHeader>
                    <AppointmentForm
                        appointment={editing}
                        customers={customers}
                        onClose={() => {
                            setCreateOpen(false);
                            setEditing(null);
                        }}
                        professionals={professionals}
                        services={services}
                        unitTimezone={unitTimezone}
                    />
                    {editing && canManage && editing.status !== 'cancelled' ? (
                        <CancelAppointmentForm
                            appointment={editing}
                            onClose={() => {
                                setEditing(null);
                                setCreateOpen(false);
                            }}
                        />
                    ) : null}
                </DialogContent>
            </Dialog>

            <Sheet open={filterOpen} onOpenChange={setFilterOpen}>
                <SheetContent
                    side="right"
                    className="w-full overflow-y-auto sm:max-w-md"
                >
                    <SheetHeader>
                        <SheetTitle>Filtrar agenda</SheetTitle>
                        <SheetDescription>
                            Escolha os profissionais e situações que deseja
                            acompanhar.
                        </SheetDescription>
                    </SheetHeader>
                    <Form
                        {...calendarIndex.form()}
                        className="flex flex-1 flex-col"
                        onSuccess={() => setFilterOpen(false)}
                    >
                        <input type="hidden" name="date" value={selectedDate} />
                        <input type="hidden" name="view" value={view} />
                        <div className="flex-1 space-y-6 overflow-y-auto px-4 py-5">
                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">
                                    Profissionais
                                </legend>
                                {professionals.map((professional) => (
                                    <label
                                        key={professional.id}
                                        className="flex min-h-11 items-center gap-3 rounded-lg border border-border px-3 py-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            name="professional_ids[]"
                                            value={professional.id}
                                            defaultChecked={selectedProfessionalIds.includes(
                                                professional.id,
                                            )}
                                            className="size-4 accent-primary"
                                        />
                                        {professional.name}
                                    </label>
                                ))}
                                {professionals.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nenhum profissional disponível.
                                    </p>
                                ) : null}
                            </fieldset>
                            <fieldset className="space-y-2">
                                <legend className="text-sm font-semibold">
                                    Situação
                                </legend>
                                {statusOptions.map((status) => (
                                    <label
                                        key={status}
                                        className="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-sm"
                                    >
                                        <span className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                name="status[]"
                                                value={status}
                                                defaultChecked={selectedStatuses.includes(
                                                    status,
                                                )}
                                                className="size-4 accent-primary"
                                            />
                                            {statusLabel(status)}
                                        </span>
                                        <StatusChip status={status} />
                                    </label>
                                ))}
                            </fieldset>
                        </div>
                        <SheetFooter className="border-t border-border">
                            <Button type="submit">
                                <FilterSummary
                                    professionals={[]}
                                    statuses={[]}
                                />
                                Aplicar filtros
                            </Button>
                            <Button asChild variant="ghost">
                                <Link
                                    href={calendarIndex({
                                        query: { date: selectedDate, view },
                                    })}
                                >
                                    Limpar filtros
                                </Link>
                            </Button>
                        </SheetFooter>
                    </Form>
                </SheetContent>
            </Sheet>
        </>
    );
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'Agenda', href: calendarIndex() }],
};
