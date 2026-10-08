import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Gift, Plus, Scissors } from 'lucide-react';
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
    ResourceHeader,
    SearchToolbar,
    StatusBadge,
} from '@/components/operational';
import type {
    Paginated,
    RelationOption,
    ResourceFilters,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
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
import packagesRoutes from '@/routes/packages';
import type { SharedPageProps } from '@/types';

type ServiceSummary = {
    id: string;
    name: string;
    price_cents: number;
};

type PackageTemplate = {
    customer_packages_count?: number;
    description: string | null;
    id: string;
    is_active: boolean;
    name: string;
    price_cents: number;
    services: ServiceSummary[];
    total_sessions: number;
    validity_days: number;
};

type Props = {
    filters: ResourceFilters;
    packages: Paginated<PackageTemplate>;
    serviceOptions?: RelationOption[];
};

function ServiceQuantityFields({ options }: { options: RelationOption[] }) {
    const [selected, setSelected] = useState<Record<string, number>>({});
    const totalSessions = Object.values(selected).reduce(
        (total, quantity) => total + quantity,
        0,
    );

    return (
        <div className="space-y-3">
            <input type="hidden" name="total_sessions" value={totalSessions} />
            <div className="grid gap-2 sm:grid-cols-2">
                {options.map((option) => {
                    const quantity = selected[option.id] ?? 0;

                    return (
                        <div
                            key={option.id}
                            className="flex items-center gap-3 rounded-lg border bg-muted/20 p-3"
                        >
                            <input
                                type="checkbox"
                                name="service_ids[]"
                                value={option.id}
                                checked={quantity > 0}
                                onChange={(event) =>
                                    setSelected((current) => ({
                                        ...current,
                                        [option.id]: event.target.checked
                                            ? Math.max(
                                                  1,
                                                  current[option.id] ?? 1,
                                              )
                                            : 0,
                                    }))
                                }
                                className="h-4 w-4 accent-primary"
                            />
                            <span className="min-w-0 flex-1 truncate text-sm">
                                {option.name}
                            </span>
                            <input
                                type="number"
                                name={`service_quantities[${option.id}]`}
                                min="1"
                                max="1000"
                                value={quantity || ''}
                                disabled={quantity === 0}
                                onChange={(event) =>
                                    setSelected((current) => ({
                                        ...current,
                                        [option.id]: Math.max(
                                            1,
                                            Number(event.target.value) || 1,
                                        ),
                                    }))
                                }
                                aria-label={`Quantidade de ${option.name}`}
                                className="h-9 w-20 rounded-md border bg-background px-2 text-center text-sm"
                            />
                        </div>
                    );
                })}
            </div>
            <div className="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm">
                <span className="text-muted-foreground">Total do pacote: </span>
                <strong>
                    {totalSessions} {totalSessions === 1 ? 'sessão' : 'sessões'}
                </strong>
                <p className="mt-1 text-xs text-muted-foreground">
                    O total é calculado automaticamente pela soma dos serviços.
                </p>
            </div>
        </div>
    );
}

function PackagePriceField({ initialCents = 0 }: { initialCents?: number }) {
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
                Informe o valor total do pacote em reais. Ex.: 350,00
            </p>
        </>
    );
}

export default function PackagesIndex({
    packages: paginator,
    filters,
    serviceOptions = [],
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [createKey] = useState(() => createIdempotencyKey('package-create'));
    const { props } = usePage<SharedPageProps>();
    const permissions = new Set(props.auth.permissions);
    const canManage = permissions.has('package.manage');

    return (
        <PageCanvas>
            <Head title="Pacotes de Serviços" />

            <ResourceHeader
                title="Pacotes de Serviços"
                subtitle="Configure pacotes de sessões pré-pagas com validade para seus clientes."
                actions={
                    canManage && (
                        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                            <DialogTrigger asChild>
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Novo Pacote
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                                <DialogHeader>
                                    <DialogTitle>
                                        Criar Pacote de Serviços
                                    </DialogTitle>
                                    <DialogDescription>
                                        Defina o nome, sessões inclusas, preço e
                                        validade do pacote.
                                    </DialogDescription>
                                </DialogHeader>

                                <Form
                                    method="post"
                                    action={packagesRoutes.store().url}
                                    headers={{ 'X-Idempotency-Key': createKey }}
                                    onSuccess={() => setCreateOpen(false)}
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <FormErrorSummary errors={errors} />

                                            <FormField
                                                id="name"
                                                label="Nome do Pacote"
                                                required
                                                error={errors.name}
                                            >
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    required
                                                    placeholder="Ex.: Combo Barba & Cabelo (5 Sessões)"
                                                />
                                            </FormField>

                                            <FormField
                                                id="description"
                                                label="Descrição"
                                                error={errors.description}
                                            >
                                                <Input
                                                    id="description"
                                                    name="description"
                                                    placeholder="Breve descrição dos benefícios ou regras..."
                                                />
                                            </FormField>

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <FormField
                                                    id="price_display"
                                                    label="Preço Total"
                                                    required
                                                    error={errors.price_cents}
                                                >
                                                    <PackagePriceField />
                                                </FormField>

                                                <FormField
                                                    id="validity_days"
                                                    label="Validade (dias)"
                                                    required
                                                    error={errors.validity_days}
                                                >
                                                    <Input
                                                        id="validity_days"
                                                        name="validity_days"
                                                        type="number"
                                                        min="1"
                                                        max="3650"
                                                        defaultValue={90}
                                                        required
                                                    />
                                                </FormField>
                                            </div>

                                            {serviceOptions.length === 0 && (
                                                <FormField
                                                    id="total_sessions"
                                                    label="Total de sessões"
                                                    required
                                                    error={
                                                        errors.total_sessions
                                                    }
                                                >
                                                    <Input
                                                        id="total_sessions"
                                                        name="total_sessions"
                                                        type="number"
                                                        min="1"
                                                        max="1000"
                                                        defaultValue={5}
                                                        required
                                                    />
                                                </FormField>
                                            )}

                                            {serviceOptions.length > 0 && (
                                                <FormField
                                                    id="service_ids"
                                                    label="Serviços Inclusos"
                                                    error={errors.service_ids}
                                                >
                                                    <ServiceQuantityFields
                                                        options={serviceOptions}
                                                    />
                                                </FormField>
                                            )}

                                            <FormActions
                                                cancelLabel="Cancelar"
                                                onCancel={() =>
                                                    setCreateOpen(false)
                                                }
                                                submitLabel="Criar Pacote"
                                                submitting={processing}
                                            />
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )
                }
            />

            <div className="space-y-4">
                <div className="flex justify-end">
                    <Button asChild variant="outline" size="sm">
                        <Link href={packagesRoutes.archived().url}>
                            Ver pacotes arquivados
                        </Link>
                    </Button>
                </div>
                <SearchToolbar
                    searchPlaceholder="Buscar pacotes..."
                    initialSearch={filters.search ?? ''}
                    initialStatus={filters.status ?? 'active'}
                    onFilterChange={(newFilters) => {
                        router.get(packagesRoutes.index().url, newFilters, {
                            preserveState: true,
                            preserveScroll: true,
                        });
                    }}
                />

                {paginator.data.length === 0 ? (
                    <EmptyState
                        icon={Gift}
                        title="Nenhum pacote encontrado"
                        description="Crie modelos de pacotes para vender combos e sessões pré-pagas aos clientes."
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {paginator.data.map((pkg) => (
                            <Link
                                key={pkg.id}
                                href={packagesRoutes.show(pkg.id).url}
                                className="group relative flex flex-col justify-between rounded-xl border bg-card p-5 shadow-xs transition-all hover:border-primary/40 hover:shadow-md"
                            >
                                <div className="space-y-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <h3 className="font-semibold text-foreground transition-colors group-hover:text-primary">
                                                {pkg.name}
                                            </h3>
                                            {pkg.description && (
                                                <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                                    {pkg.description}
                                                </p>
                                            )}
                                        </div>
                                        <StatusBadge
                                            status={
                                                pkg.is_active
                                                    ? 'active'
                                                    : 'inactive'
                                            }
                                        />
                                    </div>

                                    <div className="flex items-baseline gap-2">
                                        <span className="text-2xl font-bold text-foreground">
                                            {formatMoney(pkg.price_cents)}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            / {pkg.total_sessions}{' '}
                                            {pkg.total_sessions === 1
                                                ? 'sessão'
                                                : 'sessões'}
                                        </span>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 border-t pt-2 text-xs text-muted-foreground">
                                        <span className="rounded-md bg-muted px-2 py-0.5 font-medium text-foreground">
                                            {pkg.validity_days} dias de validade
                                        </span>
                                        {typeof pkg.customer_packages_count ===
                                            'number' && (
                                            <span>
                                                {pkg.customer_packages_count}{' '}
                                                {pkg.customer_packages_count ===
                                                1
                                                    ? 'vendido'
                                                    : 'vendidos'}
                                            </span>
                                        )}
                                    </div>

                                    {pkg.services.length > 0 && (
                                        <div className="flex flex-wrap gap-1 pt-1">
                                            {pkg.services
                                                .slice(0, 3)
                                                .map((srv) => (
                                                    <Badge
                                                        key={srv.id}
                                                        variant="secondary"
                                                        className="text-3xs font-normal"
                                                    >
                                                        <Scissors className="mr-1 h-3 w-3" />
                                                        {srv.name}
                                                    </Badge>
                                                ))}
                                            {pkg.services.length > 3 && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-3xs font-normal"
                                                >
                                                    +{pkg.services.length - 3}{' '}
                                                    mais
                                                </Badge>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                <Pagination links={paginator.links} />
            </div>
        </PageCanvas>
    );
}
