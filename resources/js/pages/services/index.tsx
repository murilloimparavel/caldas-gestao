import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Clock3, Plus, Scissors } from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    EmptyState,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    Pagination,
    parseBrazilianCurrency,
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
import { QuickCreateProfessionalModal } from '@/components/operational/quick-create-dialogs';
import type { CreatedEntity } from '@/components/operational/quick-create-dialogs';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ImageUploader } from '@/components/ui/image-uploader';
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
    name: string;
    photo_url?: string | null;
    price_cents: number;
    professionals: ProfessionalSummary[];
    status: 'active' | 'inactive';
};

type Props = {
    filters: ResourceFilters;
    options?: {
        professionals?: RelationOption[];
    };
    professionalOptions?: RelationOption[];
    services: Paginated<Service>;
};

function ServicePriceField({ initialCents = 0 }: { initialCents?: number }) {
    const [displayValue, setDisplayValue] = useState(
        initialCents > 0
            ? (initialCents / 100).toFixed(2).replace('.', ',')
            : '',
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
                placeholder="0,00"
                aria-describedby="price-help"
            />
            <input
                type="hidden"
                name="price_cents"
                value={Number.isFinite(cents) ? cents : 0}
            />
            <p id="price-help" className="text-xs text-muted-foreground">
                Informe o preço em reais. Ex.: 85,00
            </p>
        </>
    );
}

export default function ServicesIndex({
    services: paginator,
    filters,
    options,
    professionalOptions: initialProfessionalOptions,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [selectedPhoto, setSelectedPhoto] = useState<File | null>(null);
    const [createKey] = useState(() => createIdempotencyKey('service-create'));
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('service.manage');

    const [professionalOptions, setProfessionalOptions] = useState<
        RelationOption[]
    >(initialProfessionalOptions ?? options?.professionals ?? []);
    const [quickProfessionalOpen, setQuickProfessionalOpen] = useState(false);

    const handleProfessionalCreated = (created: CreatedEntity) => {
        const newOpt: RelationOption = { id: created.id, name: created.name };
        setProfessionalOptions((prev) => [
            ...prev.filter((p) => p.id !== created.id),
            newOpt,
        ]);
    };

    const handleStatusChange = (status: 'active' | 'inactive' | 'all') => {
        router.get(
            services.index.url(),
            { ...filters, status },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Serviços" />
            <PageCanvas>
                <ResourceHeader
                    eyebrow="Gestão"
                    title="Serviços"
                    description="Defina duração, preço e quem pode realizar cada serviço para alimentar agenda e comanda."
                    action={
                        canManage ? (
                            <Dialog
                                open={createOpen}
                                onOpenChange={(open) => {
                                    setCreateOpen(open);

                                    if (!open) {
                                        setSelectedPhoto(null);
                                    }
                                }}
                            >
                                <DialogTrigger asChild>
                                    <Button className="w-full sm:w-auto">
                                        <Plus aria-hidden="true" />
                                        Novo serviço
                                    </Button>
                                </DialogTrigger>

                                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
                                    <DialogHeader>
                                        <DialogTitle>Novo serviço</DialogTitle>
                                        <DialogDescription>
                                            Cadastre a oferta que estará
                                            disponível para agenda e comanda.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <Form
                                        {...services.store.form()}
                                        headers={{
                                            'X-Idempotency-Key': createKey,
                                        }}
                                        resetOnSuccess
                                        onSuccess={() => {
                                            setCreateOpen(false);
                                            setSelectedPhoto(null);
                                        }}
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
                                                            label="Foto do serviço"
                                                            name="photo"
                                                            error={errors.photo}
                                                        >
                                                            <ImageUploader
                                                                value={
                                                                    selectedPhoto
                                                                }
                                                                onChange={
                                                                    setSelectedPhoto
                                                                }
                                                                error={
                                                                    errors.photo
                                                                }
                                                                aspectRatio="auto"
                                                                previewHeight="120px"
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
                                                                required
                                                                autoFocus
                                                                placeholder="Ex.: Corte feminino"
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <FormField
                                                        label="Duração (minutos)"
                                                        name="duration_minutes"
                                                        error={
                                                            errors.duration_minutes
                                                        }
                                                    >
                                                        <Input
                                                            id="duration_minutes"
                                                            name="duration_minutes"
                                                            type="number"
                                                            min={1}
                                                            max={1440}
                                                            required
                                                            placeholder="60"
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        label="Preço"
                                                        name="price_cents"
                                                        error={
                                                            errors.price_cents
                                                        }
                                                    >
                                                        <ServicePriceField />
                                                    </FormField>
                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Descrição"
                                                            name="description"
                                                            error={
                                                                errors.description
                                                            }
                                                        >
                                                            <textarea
                                                                id="description"
                                                                name="description"
                                                                rows={3}
                                                                placeholder="O que está incluído neste serviço"
                                                                className="min-h-24 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="space-y-2 sm:col-span-2">
                                                        <div className="flex items-center justify-between">
                                                            <p className="text-sm font-medium text-foreground">
                                                                Profissionais
                                                                habilitados
                                                            </p>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setQuickProfessionalOpen(
                                                                        true,
                                                                    )
                                                                }
                                                                className="text-xs font-semibold text-primary hover:underline focus:outline-none"
                                                            >
                                                                + Novo
                                                                Profissional
                                                            </button>
                                                        </div>
                                                        <RelationCheckboxes
                                                            name="professional_ids"
                                                            options={
                                                                professionalOptions
                                                            }
                                                        />
                                                        <p className="text-xs text-muted-foreground">
                                                            Selecione quem
                                                            poderá receber este
                                                            serviço na agenda.
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
                                                    label="Cadastrar serviço"
                                                />
                                            </>
                                        )}
                                    </Form>

                                    <QuickCreateProfessionalModal
                                        open={quickProfessionalOpen}
                                        onOpenChange={setQuickProfessionalOpen}
                                        onSuccess={handleProfessionalCreated}
                                    />
                                </DialogContent>
                            </Dialog>
                        ) : null
                    }
                />

                <SearchToolbar
                    action={services.index.url()}
                    defaultValue={filters.search}
                    status={filters.status ?? 'active'}
                    onStatusChange={handleStatusChange}
                    placeholder="Buscar por nome"
                    resultLabel={`${paginator.total} ${paginator.total === 1 ? 'serviço encontrado' : 'serviços encontrados'}`}
                />

                {paginator.data.length === 0 ? (
                    <EmptyState
                        title={
                            filters.search || filters.status === 'inactive'
                                ? 'Nenhum serviço encontrado'
                                : 'Nenhum serviço cadastrado'
                        }
                        description={
                            filters.search || filters.status === 'inactive'
                                ? 'Tente outro nome ou altere o filtro de status.'
                                : 'Cadastre os serviços para que a equipe possa montar uma agenda real.'
                        }
                        action={
                            !filters.search &&
                            (!filters.status || filters.status === 'active') &&
                            canManage ? (
                                <Button onClick={() => setCreateOpen(true)}>
                                    <Plus aria-hidden="true" />
                                    Cadastrar primeiro serviço
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <section
                        aria-label="Lista de serviços"
                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                    >
                        {paginator.data.map((service) => (
                            <article
                                key={service.id}
                                className="surface-panel flex min-h-56 flex-col gap-5 p-5 transition-colors hover:border-primary/40"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex min-w-0 items-center gap-3">
                                        {service.photo_url ? (
                                            <img
                                                src={service.photo_url}
                                                alt={service.name}
                                                className="size-11 shrink-0 rounded-2xl border border-border object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-accent text-accent-foreground">
                                                <Scissors
                                                    aria-hidden="true"
                                                    className="size-5"
                                                />
                                            </div>
                                        )}
                                        <div className="min-w-0">
                                            <h2 className="truncate font-semibold text-foreground">
                                                {service.name}
                                            </h2>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {service.description ||
                                                    'Sem descrição'}
                                            </p>
                                        </div>
                                    </div>
                                    <StatusBadge status={service.status} />
                                </div>
                                <div className="flex items-center justify-between gap-3 border-y border-border py-3">
                                    <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                        <Clock3
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        {service.duration_minutes} min
                                    </span>
                                    <span className="font-semibold text-foreground">
                                        {formatMoney(service.price_cents)}
                                    </span>
                                </div>
                                <div className="space-y-2">
                                    <p className="text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                                        Profissionais
                                    </p>
                                    <RelationList
                                        items={service.professionals}
                                    />
                                </div>
                                <div className="mt-auto flex justify-end">
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={services.show(service.id)}>
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

ServicesIndex.layout = {
    breadcrumbs: [{ title: 'Serviços', href: services.index() }],
};
