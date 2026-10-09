import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Clock3, Plus, Scissors, UsersRound } from 'lucide-react';
import { useState } from 'react';
import {
    useIdempotencyKey,
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
import { ImageUploader } from '@/components/ui/image-uploader';
import { Input } from '@/components/ui/input';
import professionals from '@/routes/professionals';
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
    image_url?: string | null;
    price_cents: number;
    professionals: ProfessionalSummary[];
    status: ResourceStatus;
};

type Props = {
    hasProfessionalOptions?: boolean;
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
    hasProfessionalOptions = false,
}: Props) {
    const [updateKey, rotateUpdateKey] = useIdempotencyKey('service-update');
    const [destroyKey, rotateDestroyKey] = useIdempotencyKey('service-destroy');
    const [reactivateKey, rotateReactivateKey] =
        useIdempotencyKey('service-reactivate');
    const [inactivateOpen, setInactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);
    const [hasImageError, setHasImageError] = useState(false);
    const [professionalsDialogOpen, setProfessionalsDialogOpen] =
        useState(false);
    const [selectedProfessionalIds, setSelectedProfessionalIds] = useState(() =>
        service.professionals.map((professional) => professional.id),
    );
    const [draftProfessionalIds, setDraftProfessionalIds] = useState<string[]>(
        [],
    );
    const [selectedPhoto, setSelectedPhoto] = useState<File | string | null>(
        service.image_url ?? null,
    );
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('service.manage');
    const availableProfessionals =
        professionalOptions ?? options?.professionals ?? service.professionals;
    const displayedProfessionals = hasProfessionalOptions
        ? availableProfessionals.filter((professional) =>
              selectedProfessionalIds.includes(professional.id),
          )
        : service.professionals;

    function openProfessionalsDialog(): void {
        setDraftProfessionalIds(selectedProfessionalIds);
        setProfessionalsDialogOpen(true);
    }

    function cancelProfessionalsDialog(): void {
        setDraftProfessionalIds(selectedProfessionalIds);
        setProfessionalsDialogOpen(false);
    }

    function applyProfessionalSelection(): void {
        setSelectedProfessionalIds(draftProfessionalIds);
        setProfessionalsDialogOpen(false);
    }

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
                            id="service-update-form"
                            headers={{ 'X-Idempotency-Key': updateKey }}
                            onChange={rotateUpdateKey}
                            onSuccess={rotateUpdateKey}
                            className="space-y-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="sm:col-span-2">
                                            <FormField
                                                label="Foto do serviço"
                                                name="photo"
                                                error={errors.photo}
                                            >
                                                <ImageUploader
                                                    value={selectedPhoto}
                                                    onChange={setSelectedPhoto}
                                                    disabled={!canManage}
                                                    error={errors.photo}
                                                    aspectRatio="video"
                                                />
                                            </FormField>
                                        </div>
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
                                    {(hasProfessionalOptions
                                        ? selectedProfessionalIds
                                        : service.professionals.map(
                                              (professional) => professional.id,
                                          )
                                    ).map((professionalId) => (
                                        <input
                                            key={professionalId}
                                            type="hidden"
                                            name="professional_ids[]"
                                            value={professionalId}
                                        />
                                    ))}
                                    {!hasProfessionalOptions ? (
                                        <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs leading-5 text-muted-foreground">
                                            Os vínculos atuais são preservados
                                            ao salvar. A seleção ficará
                                            disponível quando as opções da
                                            unidade forem carregadas.
                                        </p>
                                    ) : null}
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
                            {!hasImageError && service.image_url ? (
                                <div className="mt-4 overflow-hidden rounded-xl border border-border">
                                    <img
                                        src={service.image_url}
                                        alt={service.name}
                                        className="aspect-video max-h-72 w-full bg-muted/30 object-contain sm:max-h-80"
                                        onError={() => setHasImageError(true)}
                                    />
                                </div>
                            ) : null}
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
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h2 className="text-base font-semibold">
                                        Profissionais habilitados
                                    </h2>
                                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                        Somente estes profissionais devem
                                        aparecer para este serviço.
                                    </p>
                                </div>
                                <span className="rounded-full bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground">
                                    {displayedProfessionals.length}
                                </span>
                            </div>
                            <div className="mt-5 space-y-3">
                                <RelationList
                                    items={displayedProfessionals}
                                    emptyLabel="Nenhum profissional vinculado"
                                />
                                {canManage && hasProfessionalOptions ? (
                                    <Dialog
                                        open={professionalsDialogOpen}
                                        onOpenChange={(open) => {
                                            if (open) {
                                                openProfessionalsDialog();
                                            } else {
                                                cancelProfessionalsDialog();
                                            }
                                        }}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                className="w-full"
                                                onClick={
                                                    openProfessionalsDialog
                                                }
                                            >
                                                <Plus aria-hidden="true" />
                                                {displayedProfessionals.length >
                                                0
                                                    ? 'Gerenciar profissionais'
                                                    : 'Adicionar primeiro profissional'}
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent className="max-h-[calc(100dvh-1rem)] w-[calc(100%-1rem)] overflow-y-auto p-4 sm:w-full sm:max-w-lg sm:p-6">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Gerenciar profissionais
                                                </DialogTitle>
                                                <DialogDescription>
                                                    Selecione quem pode realizar{' '}
                                                    {service.name}.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <div className="space-y-4">
                                                <p className="text-xs text-muted-foreground">
                                                    {
                                                        draftProfessionalIds.length
                                                    }{' '}
                                                    selecionado
                                                    {draftProfessionalIds.length ===
                                                    1
                                                        ? ''
                                                        : 's'}
                                                </p>
                                                <RelationCheckboxes
                                                    name="professional_ids"
                                                    options={
                                                        availableProfessionals
                                                    }
                                                    resource="professionals"
                                                    selectedIds={
                                                        draftProfessionalIds
                                                    }
                                                    onSelectionChange={
                                                        setDraftProfessionalIds
                                                    }
                                                    disabled={!canManage}
                                                />
                                            </div>
                                            <DialogFooter className="sticky bottom-0 -mx-4 -mb-4 flex-col gap-2 border-t border-border bg-background/95 p-4 backdrop-blur sm:static sm:m-0 sm:flex-row sm:justify-between sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="w-full sm:w-auto"
                                                    onClick={
                                                        cancelProfessionalsDialog
                                                    }
                                                >
                                                    Cancelar
                                                </Button>
                                                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        className="w-full sm:w-auto"
                                                    >
                                                        <Link
                                                            href={professionals.index()}
                                                        >
                                                            <Plus aria-hidden="true" />
                                                            Criar novo
                                                            profissional
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        className="w-full sm:w-auto"
                                                        onClick={
                                                            applyProfessionalSelection
                                                        }
                                                    >
                                                        Aplicar seleção
                                                    </Button>
                                                </div>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                ) : null}
                            </div>
                            {!hasProfessionalOptions ? (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    As opções de seleção ainda não foram
                                    carregadas.
                                </p>
                            ) : null}
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
                                                Este serviço está atualmente
                                                inativo. Reative o cadastro para
                                                disponibilizá-lo novamente para
                                                agendamentos e comandas.
                                            </p>
                                        </div>
                                    </div>
                                    <Dialog
                                        open={reactivateOpen}
                                        onOpenChange={(open) => {
                                            if (open) {
                                                rotateReactivateKey();
                                            }

                                            setReactivateOpen(open);
                                        }}
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
                                                    O serviço voltará a ficar
                                                    ativo e poderá ser
                                                    selecionado em novos
                                                    atendimentos e agendamentos.
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
                                                onSuccess={() => {
                                                    rotateReactivateKey();
                                                    setReactivateOpen(false);
                                                }}
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
                                                O serviço deixa de aparecer em
                                                novas operações, mas o histórico
                                                continua íntegro.
                                            </p>
                                        </div>
                                    </div>
                                    <Dialog
                                        open={inactivateOpen}
                                        onOpenChange={(open) => {
                                            if (open) {
                                                rotateDestroyKey();
                                            }

                                            setInactivateOpen(open);
                                        }}
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
                                                    O serviço deixará de
                                                    aparecer no catálogo de
                                                    agendamento e novas
                                                    comandas.
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
                                                onSuccess={() => {
                                                    rotateDestroyKey();
                                                    setInactivateOpen(false);
                                                }}
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
