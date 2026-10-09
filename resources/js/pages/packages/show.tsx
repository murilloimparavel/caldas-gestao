import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Clock,
    DollarSign,
    History,
    Scissors,
    User,
} from 'lucide-react';
import { useState } from 'react';
import customerPackageActions from '@/actions/App/Http/Controllers/CustomerPackageController';
import { CustomerPicker } from '@/components/customer-picker';
import { RemoteOptionPicker } from '@/components/remote-option-picker';
import {
    createIdempotencyKey,
    useIdempotencyKey,
    FormActions,
    FormErrorSummary,
    FormField,
    formatMoney,
    PageCanvas,
    Pagination,
    parseBrazilianCurrency,
    ResourceHeader,
    StatusBadge,
} from '@/components/operational';
import type { Paginated, RelationOption } from '@/components/operational';
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
import customers from '@/routes/customers';
import packagesRoutes from '@/routes/packages';
import type { SharedPageProps } from '@/types';

type ServiceSummary = {
    duration_minutes?: number;
    id: string;
    name: string;
    price_cents: number;
    pivot?: { included_quantity?: number };
};

type PackageUsageRecord = {
    created_at: string;
    id: string;
    reversal_reason?: string | null;
    reversed_at?: string | null;
    sessions_consumed: number;
    service?: { id: string; name: string } | null;
    user?: { id: string; name: string } | null;
};

type CustomerPackageRecord = {
    created_at: string;
    customer: {
        email?: string | null;
        id: string;
        name: string;
        phone?: string | null;
    };
    expires_at: string | null;
    id: string;
    name_snapshot?: string | null;
    eligible_services_snapshot?: Array<{ id: string; name: string }> | null;
    price_cents_snapshot?: number | null;
    remaining_sessions: number;
    status: string;
    archived_at?: string | null;
    total_sessions: number;
    total_sessions_snapshot?: number | null;
    validity_days_snapshot?: number | null;
    activated_at?: string | null;
    usages: PackageUsageRecord[];
    service_balances?: Array<{
        allocated_quantity: number;
        remaining_quantity: number;
        service?: { id: string; name: string } | null;
    }>;
    financial_obligation?: {
        amount_cents: number;
        paid_date: string | null;
        payment_method: string | null;
        status: string;
    } | null;
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
    can_view_finance?: boolean;
    customerPackages: Paginated<CustomerPackageRecord>;
    package: PackageTemplate;
    package_finance_available?: boolean;
    professionals?: RelationOption[];
    serviceOptions?: RelationOption[];
};

function PackageLifecycleBadge({ status }: { status: string }) {
    const labels: Record<string, string> = {
        pending: 'Pendente de pagamento',
        active: 'Ativo',
        completed: 'Concluído',
        exhausted: 'Concluído',
        expired: 'Expirado',
        cancelled: 'Cancelado',
        review_required: 'Precisa de revisão',
        archived: 'Arquivado',
    };

    return <Badge variant="outline">{labels[status] ?? status}</Badge>;
}

function packageLifecycleDescription(status: string): string {
    const descriptions: Record<string, string> = {
        pending:
            'Aguardando o fechamento e a confirmação do pagamento da comanda. As sessões ainda não estão disponíveis.',
        active: 'Pacote liberado para consumo. Use as sessões ainda disponíveis.',
        completed: 'Todas as sessões deste pacote já foram utilizadas.',
        exhausted: 'Todas as sessões deste pacote já foram utilizadas.',
        expired:
            'A validade terminou. Este pacote não está disponível para consumo.',
        cancelled: 'Este pacote foi cancelado e não pode mais ser usado.',
        review_required:
            'Os dados deste pacote precisam ser conferidos. Ele não pode ser consumido até a revisão.',
        archived:
            'Este pacote está arquivado e pode ser consultado na tela de pacotes arquivados.',
    };

    return (
        descriptions[status] ??
        'Consulte o histórico para ver a situação deste pacote.'
    );
}

function paymentMethodLabel(method: string | null): string {
    const labels: Record<string, string> = {
        boleto: 'Boleto Bancário',
        cartao_credito: 'Cartão de Crédito',
        cartao_debito: 'Cartão de Débito',
        dinheiro: 'Dinheiro',
        pix: 'PIX',
        transferencia: 'Transferência Bancária',
        outros: 'Outro',
    };

    return method ? (labels[method] ?? method) : 'Não informado';
}

function ServiceQuantityFields({
    options,
    initial,
}: {
    options: RelationOption[];
    initial: Record<string, number>;
}) {
    const [selected, setSelected] = useState<Record<string, number>>(initial);
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

export default function PackageShow({
    package: pkg,
    serviceOptions = [],
    customerPackages,
    can_view_finance = false,
    package_finance_available = false,
    professionals = [],
}: Props) {
    const [updateOpen, setUpdateOpen] = useState(false);
    const [deactivateOpen, setDeactivateOpen] = useState(false);
    const [reactivateOpen, setReactivateOpen] = useState(false);
    const [sellOpen, setSellOpen] = useState(false);
    const [sellCustomerId, setSellCustomerId] = useState('');
    const [sellProfessionalId, setSellProfessionalId] = useState('');
    const [usageToReverse, setUsageToReverse] = useState<{
        customerPackageId: string;
        idempotencyKey: string;
        usage: PackageUsageRecord;
    } | null>(null);
    const showPackageFinancialObligations =
        can_view_finance && package_finance_available;

    const [updateKey, rotateUpdateKey] = useIdempotencyKey('package-update');
    const [deactivateKey, rotateDeactivateKey] =
        useIdempotencyKey('package-deactivate');
    const [reactivateKey, rotateReactivateKey] =
        useIdempotencyKey('package-reactivate');
    const [sellKey, rotateSellKey] = useIdempotencyKey(
        'customer-package-sell',
        pkg.id,
    );

    const { props } = usePage<SharedPageProps>();
    const permissions = new Set(props.auth.permissions);
    const canManage = permissions.has('package.manage');
    const canConsume = canManage || permissions.has('package.consume');
    const canSell = canManage || permissions.has('package.sell');
    const canStartSale =
        canSell &&
        permissions.has('sale.manage') &&
        permissions.has('sale.view');

    const unitPriceCents =
        pkg.total_sessions > 0
            ? Math.round(pkg.price_cents / pkg.total_sessions)
            : 0;
    const selectedServiceQuantities = Object.fromEntries(
        pkg.services.map((service) => [
            service.id,
            service.pivot?.included_quantity ?? 1,
        ]),
    );

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
                subtitle={
                    pkg.description ??
                    'Detalhes e histórico de vendas do pacote.'
                }
                status={
                    <div className="space-y-1">
                        <p className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                            Modelo para novas vendas
                        </p>
                        <StatusBadge
                            status={pkg.is_active ? 'active' : 'inactive'}
                        />
                    </div>
                }
                actions={
                    canManage && (
                        <div className="flex items-center gap-2">
                            <Dialog
                                open={updateOpen}
                                onOpenChange={(open) => {
                                    if (open) {
                                        rotateUpdateKey();
                                    }

                                    setUpdateOpen(open);
                                }}
                            >
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        Editar Pacote
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Editar Pacote de Serviços
                                        </DialogTitle>
                                        <DialogDescription>
                                            Atualize as informações, sessões e
                                            serviços vinculados.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <Form
                                        method="put"
                                        action={
                                            packagesRoutes.update(pkg.id).url
                                        }
                                        headers={{
                                            'X-Idempotency-Key': updateKey,
                                        }}
                                        onChange={rotateUpdateKey}
                                        onSuccess={() => {
                                            rotateUpdateKey();
                                            setUpdateOpen(false);
                                        }}
                                        className="space-y-4"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <FormErrorSummary
                                                    errors={errors}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="lock_version"
                                                    value={pkg.lock_version}
                                                />

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
                                                        defaultValue={
                                                            pkg.description ??
                                                            ''
                                                        }
                                                    />
                                                </FormField>

                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <FormField
                                                        id="price_display"
                                                        label="Preço Total"
                                                        required
                                                        error={
                                                            errors.price_cents
                                                        }
                                                    >
                                                        <PackagePriceField
                                                            initialCents={
                                                                pkg.price_cents
                                                            }
                                                        />
                                                    </FormField>

                                                    <FormField
                                                        id="validity_days"
                                                        label="Validade (dias)"
                                                        required
                                                        error={
                                                            errors.validity_days
                                                        }
                                                    >
                                                        <Input
                                                            id="validity_days"
                                                            name="validity_days"
                                                            type="number"
                                                            min="1"
                                                            max="3650"
                                                            defaultValue={
                                                                pkg.validity_days
                                                            }
                                                            required
                                                        />
                                                    </FormField>
                                                </div>

                                                {serviceOptions.length ===
                                                    0 && (
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
                                                            defaultValue={
                                                                pkg.total_sessions
                                                            }
                                                            required
                                                        />
                                                    </FormField>
                                                )}

                                                {serviceOptions.length > 0 && (
                                                    <FormField
                                                        id="service_ids"
                                                        label="Serviços Inclusos"
                                                        error={
                                                            errors.service_ids
                                                        }
                                                    >
                                                        <ServiceQuantityFields
                                                            options={
                                                                serviceOptions
                                                            }
                                                            initial={
                                                                selectedServiceQuantities
                                                            }
                                                        />
                                                    </FormField>
                                                )}

                                                <FormActions
                                                    cancelLabel="Cancelar"
                                                    onCancel={() =>
                                                        setUpdateOpen(false)
                                                    }
                                                    submitLabel="Salvar Alterações"
                                                    submitting={processing}
                                                />
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>

                            {pkg.is_active ? (
                                <Dialog
                                    open={deactivateOpen}
                                    onOpenChange={(open) => {
                                        if (open) {
                                            rotateDeactivateKey();
                                        }

                                        setDeactivateOpen(open);
                                    }}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="text-destructive hover:text-destructive"
                                        >
                                            Desativar para novas vendas
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Desativar modelo de pacote
                                            </DialogTitle>
                                            <DialogDescription>
                                                Isso impede novas vendas com
                                                este modelo. Os pacotes já
                                                vendidos aos clientes mantêm seu
                                                status e saldo atuais.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            method="delete"
                                            action={
                                                packagesRoutes.destroy(pkg.id)
                                                    .url
                                            }
                                            headers={{
                                                'X-Idempotency-Key':
                                                    deactivateKey,
                                            }}
                                            onSuccess={() => {
                                                rotateDeactivateKey();
                                                setDeactivateOpen(false);
                                            }}
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="lock_version"
                                                        value={pkg.lock_version}
                                                    />
                                                    <DialogFooter>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setDeactivateOpen(
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
                                                                ? 'Desativando...'
                                                                : 'Desativar modelo'}
                                                        </Button>
                                                    </DialogFooter>
                                                </>
                                            )}
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            ) : (
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
                                        <Button variant="outline">
                                            Reativar para novas vendas
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Reativar modelo de pacote
                                            </DialogTitle>
                                            <DialogDescription>
                                                Isso permite abrir novas
                                                comandas com este modelo. Os
                                                pacotes já vendidos aos clientes
                                                não terão o status alterado.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Form
                                            method="patch"
                                            action={
                                                packagesRoutes.reactivate(
                                                    pkg.id,
                                                ).url
                                            }
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
                                                        value={pkg.lock_version}
                                                    />
                                                    <DialogFooter>
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
                                                        >
                                                            {processing
                                                                ? 'Reativando...'
                                                                : 'Reativar modelo'}
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
                        {formatMoney(unitPriceCents)} por sessão (
                        {pkg.total_sessions}{' '}
                        {pkg.total_sessions === 1 ? 'sessão' : 'sessões'})
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
                        A validade começa quando a comanda do pacote é paga.
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardDescription className="flex items-center gap-1">
                            <Scissors className="h-4 w-4 text-muted-foreground" />
                            Serviços Inclusos
                        </CardDescription>
                        <CardTitle className="text-2xl font-bold">
                            {pkg.services.length}{' '}
                            {pkg.services.length === 1 ? 'serviço' : 'serviços'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-1">
                        {pkg.services.map((srv) => (
                            <Badge
                                key={srv.id}
                                variant="secondary"
                                className="text-xs"
                            >
                                {srv.name}
                            </Badge>
                        ))}
                    </CardContent>
                </Card>
            </div>

            <div className="mt-8 space-y-4">
                {can_view_finance && !package_finance_available && (
                    <div
                        role="status"
                        className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                    >
                        Os dados de pagamento dos pacotes estão temporariamente
                        indisponíveis. O histórico e o saldo de sessões seguem
                        disponíveis.
                    </div>
                )}
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-semibold tracking-tight">
                        Vendas deste modelo ({customerPackages.total})
                    </h3>
                    {canStartSale && pkg.is_active && (
                        <Dialog
                            open={sellOpen}
                            onOpenChange={(open) => {
                                if (open) {
                                    rotateSellKey();
                                }

                                setSellOpen(open);

                                if (!open) {
                                    setSellCustomerId('');
                                    setSellProfessionalId('');
                                }
                            }}
                        >
                            <DialogTrigger asChild>
                                <Button>Vender para Cliente</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Vender pacote para cliente
                                    </DialogTitle>
                                    <DialogDescription>
                                        O cliente receberá {pkg.total_sessions}{' '}
                                        {pkg.total_sessions === 1
                                            ? 'sessão'
                                            : 'sessões'}{' '}
                                        por {formatMoney(pkg.price_cents)}. A
                                        validade de {pkg.validity_days} dias
                                        começa após o pagamento.
                                    </DialogDescription>
                                </DialogHeader>

                                <Form
                                    method="post"
                                    action={customerPackageActions.store().url}
                                    headers={{
                                        'X-Idempotency-Key': sellKey,
                                    }}
                                    onChange={rotateSellKey}
                                    onSuccess={() => {
                                        rotateSellKey();
                                        setSellOpen(false);
                                        setSellCustomerId('');
                                        setSellProfessionalId('');
                                    }}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <FormErrorSummary errors={errors} />
                                            <input
                                                type="hidden"
                                                name="package_template_id"
                                                value={pkg.id}
                                            />
                                            <input
                                                type="hidden"
                                                name="start_sale"
                                                value="1"
                                            />
                                            <CustomerPicker
                                                label="Cliente"
                                                required
                                                value={sellCustomerId}
                                                onChange={setSellCustomerId}
                                                options={[]}
                                                error={errors.customer_id}
                                            />
                                            <FormField
                                                id="professional_id"
                                                label="Profissional Executor"
                                                required
                                                error={errors.professional_id}
                                            >
                                                <RemoteOptionPicker
                                                    id="professional_id"
                                                    name="professional_id"
                                                    options={professionals}
                                                    placeholder="Selecione um profissional..."
                                                    resource="professionals"
                                                    value={sellProfessionalId}
                                                    onChange={
                                                        setSellProfessionalId
                                                    }
                                                    required
                                                />
                                            </FormField>
                                            <DialogFooter className="mt-4">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setSellOpen(false)
                                                    }
                                                >
                                                    Cancelar
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        processing ||
                                                        sellCustomerId === '' ||
                                                        sellProfessionalId ===
                                                            ''
                                                    }
                                                >
                                                    {processing
                                                        ? 'Vendendo…'
                                                        : 'Confirmar venda'}
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                    {canSell && !canStartSale && pkg.is_active && (
                        <p className="max-w-sm text-right text-sm text-muted-foreground">
                            Para vender este pacote, seu usuário precisa também
                            poder gerenciar e visualizar comandas.
                        </p>
                    )}
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
                                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <User className="h-4 w-4 text-muted-foreground" />
                                                <Link
                                                    href={
                                                        customers.show(
                                                            cp.customer.id,
                                                        ).url
                                                    }
                                                    className="font-medium text-foreground hover:underline"
                                                >
                                                    {cp.customer.name}
                                                </Link>
                                                <PackageLifecycleBadge
                                                    status={cp.status}
                                                />
                                            </div>
                                            <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                {cp.customer.phone && (
                                                    <span>
                                                        {cp.customer.phone}
                                                    </span>
                                                )}
                                                <span>
                                                    Registrado em{' '}
                                                    {new Date(
                                                        cp.created_at,
                                                    ).toLocaleDateString(
                                                        'pt-BR',
                                                    )}
                                                </span>
                                                {cp.expires_at && (
                                                    <span>
                                                        Validade até{' '}
                                                        {new Date(
                                                            cp.expires_at,
                                                        ).toLocaleDateString(
                                                            'pt-BR',
                                                        )}
                                                    </span>
                                                )}
                                                {cp.activated_at && (
                                                    <span>
                                                        Ativado em{' '}
                                                        {new Date(
                                                            cp.activated_at,
                                                        ).toLocaleDateString(
                                                            'pt-BR',
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {packageLifecycleDescription(
                                                    cp.status,
                                                )}
                                            </p>
                                            <div className="flex flex-wrap gap-2 text-3xs text-muted-foreground">
                                                <span className="rounded-md bg-muted px-2 py-1">
                                                    Pacote:{' '}
                                                    {cp.name_snapshot ??
                                                        pkg.name}
                                                </span>
                                                <span className="rounded-md bg-muted px-2 py-1">
                                                    {cp.validity_days_snapshot ??
                                                        pkg.validity_days}{' '}
                                                    dias de validade
                                                </span>
                                                {cp.price_cents_snapshot !=
                                                    null && (
                                                    <span className="rounded-md bg-muted px-2 py-1">
                                                        Venda:{' '}
                                                        {formatMoney(
                                                            cp.price_cents_snapshot,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                            {showPackageFinancialObligations && (
                                                <p className="text-xs text-muted-foreground">
                                                    {cp.financial_obligation ? (
                                                        <>
                                                            {cp
                                                                .financial_obligation
                                                                .status ===
                                                            'paid'
                                                                ? 'Pagamento recebido: '
                                                                : cp
                                                                        .financial_obligation
                                                                        .status ===
                                                                    'cancelled'
                                                                  ? 'Cobrança cancelada: '
                                                                  : 'Pagamento pendente: '}
                                                            {paymentMethodLabel(
                                                                cp
                                                                    .financial_obligation
                                                                    .payment_method,
                                                            )}
                                                            {' · '}
                                                            {formatMoney(
                                                                cp
                                                                    .financial_obligation
                                                                    .amount_cents,
                                                            )}
                                                            {cp
                                                                .financial_obligation
                                                                .paid_date && (
                                                                <>
                                                                    {
                                                                        ' · recebido em '
                                                                    }
                                                                    {new Date(
                                                                        cp
                                                                            .financial_obligation
                                                                            .paid_date,
                                                                    ).toLocaleDateString(
                                                                        'pt-BR',
                                                                    )}
                                                                </>
                                                            )}
                                                        </>
                                                    ) : (
                                                        'Sem lançamento financeiro associado.'
                                                    )}
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <div className="text-right">
                                                <div className="text-sm font-semibold">
                                                    {cp.remaining_sessions} de{' '}
                                                    {cp.total_sessions} sessões
                                                    disponíveis
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {cp.total_sessions -
                                                        cp.remaining_sessions}{' '}
                                                    utilizadas
                                                </div>
                                            </div>
                                            {canSell &&
                                                cp.status === 'pending' && (
                                                    <Form
                                                        method="post"
                                                        action={
                                                            customerPackageActions.cancel(
                                                                cp.id,
                                                            ).url
                                                        }
                                                        onSubmit={(event) => {
                                                            if (
                                                                !window.confirm(
                                                                    'Cancelar este pacote pendente?',
                                                                )
                                                            ) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="reason"
                                                            value="Cancelamento manual de pacote pendente"
                                                        />
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                            className="text-destructive"
                                                        >
                                                            Cancelar pendente
                                                        </Button>
                                                    </Form>
                                                )}
                                            {canManage &&
                                                [
                                                    'completed',
                                                    'exhausted',
                                                    'cancelled',
                                                    'expired',
                                                ].includes(cp.status) && (
                                                    <Form
                                                        method="post"
                                                        action={
                                                            customerPackageActions.archive(
                                                                cp.id,
                                                            ).url
                                                        }
                                                        onSubmit={(event) => {
                                                            if (
                                                                !window.confirm(
                                                                    'Arquivar este pacote? Ele sairá desta lista e continuará disponível em Pacotes arquivados.',
                                                                )
                                                            ) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            Arquivar
                                                        </Button>
                                                    </Form>
                                                )}
                                        </div>
                                    </div>

                                    {cp.usages && cp.usages.length > 0 && (
                                        <div className="mt-4 border-t pt-3">
                                            <p className="mb-2 flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                                <History className="h-3 w-3" />{' '}
                                                Histórico de uso:
                                            </p>
                                            <div className="space-y-1">
                                                {cp.usages.map((usage) => (
                                                    <div
                                                        key={usage.id}
                                                        className="flex flex-col gap-2 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
                                                    >
                                                        <span>
                                                            {
                                                                usage.sessions_consumed
                                                            }{' '}
                                                            {usage.sessions_consumed ===
                                                            1
                                                                ? '1 sessão utilizada'
                                                                : `${usage.sessions_consumed} sessões utilizadas`}
                                                            {usage.user
                                                                ? ` por ${usage.user.name}`
                                                                : ''}
                                                            {usage.reversed_at
                                                                ? ' · revertido'
                                                                : ''}
                                                        </span>
                                                        <div className="flex items-center gap-2">
                                                            <span>
                                                                {new Date(
                                                                    usage.created_at,
                                                                ).toLocaleString(
                                                                    'pt-BR',
                                                                )}
                                                            </span>
                                                            {canConsume &&
                                                                !usage.reversed_at && (
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        className="h-7 px-2 text-destructive hover:text-destructive"
                                                                        onClick={() =>
                                                                            setUsageToReverse(
                                                                                {
                                                                                    customerPackageId:
                                                                                        cp.id,
                                                                                    idempotencyKey:
                                                                                        createIdempotencyKey(
                                                                                            'package-usage-reverse',
                                                                                            usage.id,
                                                                                        ),
                                                                                    usage,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        Reverter
                                                                    </Button>
                                                                )}
                                                        </div>
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

            <Dialog
                open={usageToReverse !== null}
                onOpenChange={(open) => !open && setUsageToReverse(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reverter consumo?</DialogTitle>
                        <DialogDescription>
                            Esta ação devolverá as sessões ao saldo do cliente e
                            ficará registrada no histórico operacional.
                        </DialogDescription>
                    </DialogHeader>
                    {usageToReverse && (
                        <Form
                            method="post"
                            action={
                                customerPackageActions.reverseUsage({
                                    customer_package:
                                        usageToReverse.customerPackageId,
                                    package_usage: usageToReverse.usage.id,
                                }).url
                            }
                            headers={{
                                'X-Idempotency-Key':
                                    usageToReverse.idempotencyKey,
                            }}
                            onSuccess={() => setUsageToReverse(null)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormErrorSummary errors={errors} />
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setUsageToReverse(null)
                                            }
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Revertendo…'
                                                : 'Confirmar reversão'}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </PageCanvas>
    );
}
