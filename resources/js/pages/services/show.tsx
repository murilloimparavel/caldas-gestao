import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Clock3, Scissors, UsersRound } from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    parseBrazilianCurrency,
    RelationCheckboxes,
    RelationList,
    ResourceHeader,
    StatusBadge,
} from '@/components/operational';
import type { RelationOption, ResourceStatus } from '@/components/operational';
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
import { Input } from '@/components/ui/input';
import services from '@/routes/services';
import type { SharedPageProps } from '@/types';

type ProfessionalSummary = {
    id: string;
    name: string;
};

type Service = {
    description: string | null;
    duration_minutes: number;
    id: string;
    lock_version: number;
    name: string;
    price_cents: number;
    professionals: ProfessionalSummary[];
    status: ResourceStatus;
};

type Props = {
    options?: {
        professionals?: RelationOption[];
    };
    professionalOptions?: RelationOption[];
    service: Service;
};

function ServicePriceField({
    disabled = false,
    initialCents,
}: {
    disabled?: boolean;
    initialCents: number;
}) {
    const [displayValue, setDisplayValue] = useState(
        (initialCents / 100).toFixed(2).replace('.', ','),
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
                disabled={disabled}
                aria-describedby="price-help"
            />
            <input type="hidden" name="price_cents" value={cents} />
            <p id="price-help" className="text-xs text-muted-foreground">
                Preço atual: {formatMoney(initialCents)}. Informe em reais.
            </p>
        </>
    );
}

export default function ServiceShow({
    service,
    options,
    professionalOptions,
}: Props) {
    const [updateKey] = useState(() => createIdempotencyKey('service-update'));
    const [destroyKey] = useState(() =>
        createIdempotencyKey('service-destroy'),
    );
    const [reactivateKey] = useState(() =>
        createIdempotencyKey('service-reactivate'),
    );
    const [inactivateOpen, setInactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('service.manage');
    const availableProfessionals =
        professionalOptions ?? options?.professionals ?? service.professionals;
    const hasProfessionalOptions =
        professionalOptions !== undefined ||
        options?.professionals !== undefined;

    return (
        <>
            <Head title={service.name} />
            <PageCanvas>
                <div>
                    <Button asChild variant="ghost" className="mb-4 -ml-3">
                        <Link href={services.index()}>
                            <ArrowLeft aria-hidden="true" />
                            Voltar para serviços
                        </Link>
                    </Button>
                    <ResourceHeader
                        eyebrow="Cadastro de serviço"
                        title={service.name}
                        description="Preço e duração são capturados no momento da operação para preservar o histórico da comanda."
                        action={<StatusBadge status={service.status} />}
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,0.85fr)]">
                    <section className="surface-panel p-5 sm:p-6">
                        <div className="mb-6 space-y-1">
                            <h2 className="text-base font-semibold">
                                Dados do serviço
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Alterações valem para novas operações. O
                                histórico não é reescrito.
                            </p>
                        </div>
                        <Form
                            {...services.update.form(service.id)}
                            headers={{ 'X-Idempotency-Key': updateKey }}
                            className="space-y-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="sm:col-span-2">
                                            <FormField
                                                label="Nome do serviço"
                                                name="name"
                                                error={errors.name}
                                            >
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    defaultValue={service.name}
                                                    required
                                                    disabled={!canManage}
                                                />
                                            </FormField>
                                        </div>
                                        <FormField
                                            label="Duração (minutos)"
                                            name="duration_minutes"
                                            error={errors.duration_minutes}
                                        >
                                            <Input
                                                id="duration_minutes"
                                                name="duration_minutes"
                                                type="number"
                                                min={1}
                                                max={1440}
                                                defaultValue={
                                                    service.duration_minutes
                                                }
                                                required
                                                disabled={!canManage}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Preço"
                                            name="price_cents"
                                            error={errors.price_cents}
                                        >
                                            <ServicePriceField
                                                disabled={!canManage}
                                                initialCents={
                                                    service.price_cents
                                                }
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
                                                defaultValue={service.status}
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
                                                label="Descrição"
                                                name="description"
                                                error={errors.description}
                                            >
                                                <textarea
                                                    id="description"
                                                    name="description"
                                                    rows={4}
                                                    defaultValue={
                                                        service.description ??
                                                        ''
                                                    }
                                                    disabled={!canManage}
                                                    className="min-h-28 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                                />
                                            </FormField>
                                        </div>
                                    </div>
                                    {hasProfessionalOptions ? (
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium text-foreground">
                                                Profissionais habilitados
                                            </p>
                                            <RelationCheckboxes
                                                name="professional_ids"
                                                options={availableProfessionals}
                                                selectedIds={service.professionals.map(
                                                    (professional) =>
                                                        professional.id,
                                                )}
                                                disabled={!canManage}
                                            />
                                        </div>
                                    ) : (
                                        <>
                                            {service.professionals.map(
                                                (professional) => (
                                                    <input
                                                        key={professional.id}
                                                        type="hidden"
                                                        name="professional_ids[]"
                                                        value={professional.id}
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
                                                value={service.lock_version}
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
                                Resumo operacional
                            </h2>
                            <div className="mt-5 grid gap-4">
                                <div className="flex items-start gap-3">
                                    <Clock3
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground"
                                    />
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Duração
                                        </p>
                                        <p className="mt-0.5 text-sm font-medium">
                                            {service.duration_minutes} minutos
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <Scissors
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground"
                                    />
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Preço vigente
                                        </p>
                                        <p className="mt-0.5 text-sm font-medium">
                                            {formatMoney(service.price_cents)}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <UsersRound
                                        aria-hidden="true"
                                        className="mt-0.5 size-4 text-muted-foreground"
                                    />
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Profissionais
                                        </p>
                                        <p className="mt-0.5 text-sm font-medium">
                                            {service.professionals.length}{' '}
                                            {service.professionals.length === 1
                                                ? 'vínculo'
                                                : 'vínculos'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section className="surface-panel p-5 sm:p-6">
                            <h2 className="text-base font-semibold">
                                Profissionais habilitados
                            </h2>
                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                Somente estes profissionais devem aparecer para
                                este serviço.
                            </p>
                            <div className="mt-5">
                                <RelationList
                                    items={service.professionals}
                                    emptyLabel="Nenhum profissional vinculado"
                                />
                            </div>
                        </section>
                        {canManage ? (
                            service.status === 'inactive' ? (
                                <section className="surface-panel border-emerald-500/30 bg-emerald-50/20 p-5 sm:p-6 dark:bg-emerald-950/20">
                                    <div className="flex items-start gap-3">
                                        <Scissors
                                            aria-hidden="true"
                                            className="mt-0.5 size-4 text-emerald-600 dark:text-emerald-400"
                                        />
                                        <div>
                                            <h2 className="text-base font-semibold text-foreground">
                                                Reativar serviço
                                            </h2>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                Este serviço está atualmente inativo. Reative o cadastro para disponibilizá-lo novamente para agendamentos e comandas.
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
                                                    Reativar serviço?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    O serviço voltará a ficar ativo e poderá ser selecionado em novos atendimentos e agendamentos.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <Form
                                                {...services.reactivate.form(
                                                    service.id,
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
                                                                service.lock_version
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
                                        <Scissors
                                            aria-hidden="true"
                                            className="mt-0.5 size-4 text-destructive"
                                        />
                                        <div>
                                            <h2 className="text-base font-semibold">
                                                Desativar serviço
                                            </h2>
                                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                                O serviço deixa de aparecer em novas
                                                operações, mas o histórico continua
                                                íntegro.
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
                                                Desativar serviço
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Desativar serviço?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    O serviço deixará de aparecer no catálogo de agendamento e novas comandas.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <Form
                                                {...services.destroy.form(
                                                    service.id,
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
                                                                service.lock_version
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

ServiceShow.layout = {
    breadcrumbs: [
        { title: 'Serviços', href: services.index() },
        { title: 'Cadastro', href: services.index() },
    ],
};
