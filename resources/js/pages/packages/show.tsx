import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    DollarSign,
    Gift,
    History,
    Scissors,
    User,
} from 'lucide-react';
import { useState } from 'react';
import {
    createIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    Pagination,
    parseBrazilianCurrency,
    RelationCheckboxes,
    ResourceHeader,
    StatusBadge,
} from '@/components/operational';
import type {
    Paginated,
    RelationOption,
    ResourceStatus,
} from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import packagesRoutes from '@/routes/packages';
import type { SharedPageProps } from '@/types';

type ServiceSummary = {
    duration_minutes?: number;
    id: string;
    name: string;
    price_cents: number;
};

type PackageUsageRecord = {
    created_at: string;
    id: string;
    sessions_consumed: number;
    user?: { id: string; name: string } | null;
};

type CustomerPackageRecord = {
    created_at: string;
    customer: { email?: string | null; id: string; name: string; phone?: string | null };
    expires_at: string | null;
    id: string;
    remaining_sessions: number;
    status: ResourceStatus;
    total_sessions: number;
    usages: PackageUsageRecord[];
};

type PackageTemplate = {
    description: string | null;
    id: string;
    is_active: boolean;
    lock_version: number;
    name: string;
    price_cents: number;
    services: ServiceSummary[];
    total_sessions: number;
    validity_days: number;
};

type Props = {
    customerPackages: Paginated<CustomerPackageRecord>;
    package: PackageTemplate;
    serviceOptions?: RelationOption[];
};

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

export default function PackageShow({
    package: pkg,
    serviceOptions = [],
    customerPackages,
}: Props) {
    const [updateOpen, setUpdateOpen] = useState(false);
    const [deactivateOpen, setDeactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);

    const [updateKey] = useState(() => createIdempotencyKey('package-update'));
    const [deactivateKey] = useState(() => createIdempotencyKey('package-deactivate'));
    const [reactivateKey] = useState(() => createIdempotencyKey('package-reactivate'));

    const { props } = usePage<SharedPageProps>();
    const permissions = new Set(props.auth.permissions);
    const canManage = permissions.has('package.manage');

    const unitPriceCents = pkg.total_sessions > 0 ? Math.round(pkg.price_cents / pkg.total_sessions) : 0;
    const selectedServiceIds = pkg.services.map((s) => s.id);

    return (
        <PageCanvas>
            <Head title={`Pacote: ${pkg.name}`} />

            <div className="mb-4">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={packagesRoutes.index().url}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Voltar para Pacotes
                    </Link>
                </Button>
            </div>

            <ResourceHeader
                title={pkg.name}
                subtitle={pkg.description ?? 'Detalhes e histórico de vendas do pacote.'}
                status={<StatusBadge status={pkg.is_active ? 'active' : 'inactive'} />}
                actions={
                    canManage && (
                        <div className="flex items-center gap-2">
                            <Dialog open={updateOpen} onOpenChange={setUpdateOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline">Editar Pacote</Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                                    <DialogHeader>
                                        <DialogTitle>Editar Pacote de Serviços</DialogTitle>
                                        <DialogDescription>
                                            Atualize as informações, sessões e serviços vinculados.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <Form
                                        method="put"
                                        action={packagesRoutes.update(pkg.id).url}
                                        headers={{ 'X-Idempotency-Key': updateKey }}
                                        onSuccess={() => setUpdateOpen(false)}
                                        className="space-y-4"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <FormErrorSummary errors={errors} />
                                                <input type="hidden" name="lock_version" value={pkg.lock_version} />

                                                <FormField
                                                    id="name"
                                                    label="Nome do Pacote"
                                                    required
                                                    error={errors.name}
                                                >
                                                    <Input
                                                        id="name"
                                                        name="name"
                                                        defaultValue={pkg.name}
                                                        required
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
                                                        defaultValue={pkg.description ?? ''}
                                                    />
                                                </FormField>

                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                                    <FormField
                                                        id="price_display"
                                                        label="Preço Total"
                                                        required
                                                        error={errors.price_cents}
                                                    >
                                                        <PackagePriceField initialCents={pkg.price_cents} />
                                                    </FormField>

                                                    <FormField
                                                        id="total_sessions"
                                                        label="Qtd. de Sessões"
                                                        required
                                                        error={errors.total_sessions}
                                                    >
                                                        <Input
                                                            id="total_sessions"
                                                            name="total_sessions"
                                                            type="number"
                                                            min="1"
                                                            max="1000"
                                                            defaultValue={pkg.total_sessions}
                                                            required
                                                        />
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
                                                            defaultValue={pkg.validity_days}
                                                            required
                                                        />
                                                    </FormField>
                                                </div>

                                                {serviceOptions.length > 0 && (
                                                    <FormField
                                                        id="service_ids"
                                                        label="Serviços Inclusos"
                                                        error={errors.service_ids}
                                                    >
                                                        <RelationCheckboxes
                                                            name="service_ids"
                                                            options={serviceOptions}
                                                            initialSelected={selectedServiceIds}
                                                        />
                                                    </FormField>
                                                )}

                                                <FormActions
                                                    cancelLabel="Cancelar"
                                                    onCancel={() => setUpdateOpen(false)}
                                                    submitLabel="Salvar Alterações"
                                                    submitting={processing}
                                                />
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>

                            {pkg.is_active ? (
                                <Dialog open={deactivateOpen} onOpenChange={setDeactivateOpen}>
                                    <DialogTrigger asChild>
                                        <Button variant="outline" className="text-destructive hover:text-destructive">
                                            Desativar
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Desativar Pacote</DialogTitle>
                                            <DialogDescription>
                                                Tem certeza de que deseja desativar este pacote? Novos pacotes não poderão ser vendidos com este modelo.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            method="delete"
                                            action={packagesRoutes.destroy(pkg.id).url}
                                            headers={{ 'X-Idempotency-Key': deactivateKey }}
                                            onSuccess={() => setDeactivateOpen(false)}
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input type="hidden" name="lock_version" value={pkg.lock_version} />
                                                    <DialogFooter>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() => setDeactivateOpen(false)}
                                                        >
                                                            Cancelar
                                                        </Button>
                                                        <Button
                                                            type="submit"
                                                            variant="destructive"
                                                            disabled={processing}
                                                        >
                                                            {processing ? 'Desativando...' : 'Confirmar Desativação'}
                                                        </Button>
                                                    </DialogFooter>
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            ) : (
                                <Dialog open={reactivateOpen} onOpenChange={setReactivateOpen}>
                                    <DialogTrigger asChild>
                                        <Button variant="outline">Reativar</Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Reativar Pacote</DialogTitle>
                                            <DialogDescription>
                                                Deseja reativar este pacote para permitir novas vendas?
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            method="patch"
                                            action={packagesRoutes.reactivate(pkg.id).url}
                                            headers={{ 'X-Idempotency-Key': reactivateKey }}
                                            onSuccess={() => setReactivateOpen(false)}
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input type="hidden" name="lock_version" value={pkg.lock_version} />
                                                    <DialogFooter>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() => setReactivateOpen(false)}
                                                        >
                                                            Cancelar
                                                        </Button>
                                                        <Button
                                                            type="submit"
                                                            disabled={processing}
                                                        >
                                                            {processing ? 'Reativando...' : 'Confirmar Reativação'}
                                                        </Button>
                                                    </DialogFooter>
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            )}
                        </div>
                    )
                }
            />

            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Card>
                    <CardHeader className="pb-2">
                        <CardDescription className="flex items-center gap-1">
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                            Valores
                        </CardDescription>
                        <CardTitle className="text-2xl font-bold">
                            {formatMoney(pkg.price_cents)}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-xs text-muted-foreground">
                        {formatMoney(unitPriceCents)} por sessão ({pkg.total_sessions} {pkg.total_sessions === 1 ? 'sessão' : 'sessões'})
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardDescription className="flex items-center gap-1">
                            <Clock className="h-4 w-4 text-muted-foreground" />
                            Validade
                        </CardDescription>
                        <CardTitle className="text-2xl font-bold">
                            {pkg.validity_days} dias
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-xs text-muted-foreground">
                        Prazo padrão contado a partir do momento da venda ao cliente.
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardDescription className="flex items-center gap-1">
                            <Scissors className="h-4 w-4 text-muted-foreground" />
                            Serviços Inclusos
                        </CardDescription>
                        <CardTitle className="text-2xl font-bold">
                            {pkg.services.length} {pkg.services.length === 1 ? 'serviço' : 'serviços'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-1">
                        {pkg.services.map((srv) => (
                            <Badge key={srv.id} variant="secondary" className="text-xs">
                                {srv.name}
                            </Badge>
                        ))}
                    </CardContent>
                </Card>
            </div>

            <div className="mt-8 space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-semibold tracking-tight">Pacotes Vendidos ({customerPackages.total})</h3>
                </div>

                {customerPackages.data.length === 0 ? (
                    <Card>
                        <CardContent className="p-8 text-center text-sm text-muted-foreground">
                            Nenhum cliente comprou este pacote ainda.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {customerPackages.data.map((cp) => (
                            <Card key={cp.id} className="overflow-hidden">
                                <CardContent className="p-4 sm:p-5">
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <User className="h-4 w-4 text-muted-foreground" />
                                                <Link
                                                    href={`/customers/${cp.customer.id}`}
                                                    className="font-medium text-foreground hover:underline"
                                                >
                                                    {cp.customer.name}
                                                </Link>
                                                <StatusBadge status={cp.status} />
                                            </div>
                                            <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                {cp.customer.phone && <span>{cp.customer.phone}</span>}
                                                <span>Vendido em {new Date(cp.created_at).toLocaleDateString('pt-BR')}</span>
                                                {cp.expires_at && (
                                                    <span>Validade até {new Date(cp.expires_at).toLocaleDateString('pt-BR')}</span>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <div className="text-right">
                                                <div className="text-sm font-semibold">
                                                    {cp.remaining_sessions} de {cp.total_sessions} sessões
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {cp.total_sessions - cp.remaining_sessions} consumidas
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {cp.usages && cp.usages.length > 0 && (
                                        <div className="mt-4 pt-3 border-t">
                                            <p className="text-xs font-medium text-muted-foreground mb-2 flex items-center gap-1">
                                                <History className="h-3 w-3" /> Histórico de consumo recente:
                                            </p>
                                            <div className="space-y-1">
                                                {cp.usages.map((usage) => (
                                                    <div key={usage.id} className="text-xs text-muted-foreground flex items-center justify-between">
                                                        <span>
                                                            {usage.sessions_consumed} {usage.sessions_consumed === 1 ? 'sessão consumida' : 'sessões consumidas'}
                                                            {usage.user ? ` por ${usage.user.name}` : ''}
                                                        </span>
                                                        <span>{new Date(usage.created_at).toLocaleString('pt-BR')}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}

                        <Pagination links={customerPackages.links} />
                    </div>
                )}
            </div>
        </PageCanvas>
    );
}
