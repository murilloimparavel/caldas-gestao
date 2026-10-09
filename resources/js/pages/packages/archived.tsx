import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArchiveRestore, History, Search } from 'lucide-react';
import {
    EmptyState,
    formatMoney,
    PageCanvas,
    Pagination,
    ResourceHeader,
    useIdempotencyKey,
} from '@/components/operational';
import type { Paginated } from '@/components/operational';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import customerPackageActions from '@/actions/App/Http/Controllers/CustomerPackageController';
import packagesRoutes from '@/routes/packages';

type ArchivedPackage = {
    archived_at: string | null;
    archived_from_status: 'completed' | 'exhausted' | 'cancelled' | 'expired';
    customer?: { id: string; name: string; phone?: string | null } | null;
    created_at: string;
    eligible_services_snapshot?: Array<{ id: string; name: string }> | null;
    expires_at: string | null;
    financial_obligation?: {
        amount_cents: number;
        paid_date: string | null;
        payment_method: string | null;
        status: string;
    } | null;
    id: string;
    name_snapshot: string | null;
    price_cents_snapshot: number | null;
    remaining_sessions: number;
    service_balances?: Array<{
        allocated_quantity: number;
        remaining_quantity: number;
        service?: { id: string; name: string } | null;
    }>;
    total_sessions: number;
    usages?: Array<{
        created_at: string;
        sessions_consumed: number;
        service?: { id: string; name: string } | null;
        user?: { id: string; name: string } | null;
    }>;
};

function RestorePackageForm({ packageId }: { packageId: string }) {
    const [idempotencyKey, rotateKey] = useIdempotencyKey(
        'customer-package-restore',
        packageId,
    );

    return (
        <Form
            method="post"
            action={customerPackageActions.restore(packageId).url}
            headers={{ 'X-Idempotency-Key': idempotencyKey }}
            onSuccess={rotateKey}
        >
            <Button type="submit" variant="outline">
                <ArchiveRestore className="mr-2 size-4" />
                Restaurar pacote
            </Button>
        </Form>
    );
}

type Props = {
    canManage: boolean;
    canViewFinance: boolean;
    filters: { search: string };
    packages: Paginated<ArchivedPackage>;
};

const statusLabels: Record<ArchivedPackage['archived_from_status'], string> = {
    completed: 'Concluído',
    exhausted: 'Concluído',
    cancelled: 'Cancelado',
    expired: 'Vencido',
};

function paymentMethodLabel(method: string | null): string {
    const labels: Record<string, string> = {
        boleto: 'Boleto',
        cartao_credito: 'Cartão de crédito',
        cartao_debito: 'Cartão de débito',
        dinheiro: 'Dinheiro',
        pix: 'PIX',
        transferencia: 'Transferência',
        outros: 'Outro',
    };

    return method ? (labels[method] ?? method) : 'Forma não informada';
}

export default function ArchivedPackages({
    canManage,
    canViewFinance,
    filters,
    packages,
}: Props) {
    return (
        <PageCanvas>
            <Head title="Pacotes arquivados" />
            <div className="mb-4">
                <Button asChild variant="ghost" size="sm">
                    <Link href={packagesRoutes.index().url}>
                        <ArrowLeft className="mr-2 size-4" />
                        Voltar para Pacotes
                    </Link>
                </Button>
            </div>
            <ResourceHeader
                title="Pacotes arquivados"
                subtitle="Consulte o histórico de pacotes retirados das telas dos clientes. Restaurar não altera pagamentos, sessões ou consumo."
            />

            <form
                action={packagesRoutes.archived().url}
                method="get"
                className="mb-5 flex max-w-xl gap-2"
            >
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Buscar pacotes arquivados"
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Buscar por pacote ou cliente..."
                        className="pl-9"
                    />
                </div>
                <Button type="submit" variant="outline">
                    Buscar
                </Button>
            </form>

            {packages.data.length === 0 ? (
                <EmptyState
                    icon={ArchiveRestore}
                    title="Nenhum pacote arquivado"
                    description="Pacotes concluídos, cancelados ou vencidos que forem arquivados aparecerão aqui."
                />
            ) : (
                <div className="space-y-4">
                    {packages.data.map((pkg) => (
                        <article
                            key={pkg.id}
                            className="rounded-xl border bg-card p-5 shadow-xs"
                        >
                            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                <div className="min-w-0 space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-base font-semibold">
                                            {pkg.name_snapshot ??
                                                'Pacote de serviços'}
                                        </h2>
                                        <Badge variant="outline">
                                            Arquivado
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            Antes:{' '}
                                            {
                                                statusLabels[
                                                    pkg.archived_from_status
                                                ]
                                            }
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        Cliente:{' '}
                                        {pkg.customer?.name ??
                                            'Cliente não informado'}
                                        {pkg.customer?.phone
                                            ? ` · ${pkg.customer.phone}`
                                            : ''}
                                    </p>
                                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <span>
                                            Comprado em{' '}
                                            {new Date(
                                                pkg.created_at,
                                            ).toLocaleDateString('pt-BR')}
                                        </span>
                                        {pkg.archived_at && (
                                            <span>
                                                Arquivado em{' '}
                                                {new Date(
                                                    pkg.archived_at,
                                                ).toLocaleDateString('pt-BR')}
                                            </span>
                                        )}
                                        {pkg.expires_at && (
                                            <span>
                                                Validade até{' '}
                                                {new Date(
                                                    pkg.expires_at,
                                                ).toLocaleDateString('pt-BR')}
                                            </span>
                                        )}
                                        {pkg.price_cents_snapshot !== null && (
                                            <span>
                                                Valor:{' '}
                                                {formatMoney(
                                                    pkg.price_cents_snapshot,
                                                )}
                                            </span>
                                        )}
                                        <span>
                                            {pkg.remaining_sessions} de{' '}
                                            {pkg.total_sessions} sessões
                                            disponíveis
                                        </span>
                                    </div>
                                </div>
                                {canManage && (
                                    <RestorePackageForm packageId={pkg.id} />
                                )}
                            </div>

                            <details className="mt-4 border-t pt-3">
                                <summary className="flex cursor-pointer list-none items-center gap-2 text-sm font-medium">
                                    <History className="size-4" /> Ver detalhes
                                    e histórico
                                </summary>
                                <div className="mt-3 space-y-3 text-sm text-muted-foreground">
                                    {pkg.eligible_services_snapshot?.length ? (
                                        <p>
                                            Serviços do pacote:{' '}
                                            {pkg.eligible_services_snapshot
                                                .map((service) => service.name)
                                                .join(', ')}
                                        </p>
                                    ) : null}
                                    {canViewFinance && (
                                        <p>
                                            {pkg.financial_obligation
                                                ? `Pagamento ${pkg.financial_obligation.status === 'paid' ? 'recebido' : 'registrado'}: ${paymentMethodLabel(pkg.financial_obligation.payment_method)} · ${formatMoney(pkg.financial_obligation.amount_cents)}${pkg.financial_obligation.paid_date ? ` · em ${new Date(pkg.financial_obligation.paid_date).toLocaleDateString('pt-BR')}` : ''}`
                                                : 'Sem lançamento financeiro associado.'}
                                        </p>
                                    )}
                                    {pkg.service_balances?.length ? (
                                        <div>
                                            <p className="font-medium text-foreground">
                                                Saldo por serviço
                                            </p>
                                            <ul className="mt-1 space-y-1">
                                                {pkg.service_balances.map(
                                                    (balance) => (
                                                        <li
                                                            key={
                                                                balance.service
                                                                    ?.id ??
                                                                balance.allocated_quantity
                                                            }
                                                        >
                                                            {balance.service
                                                                ?.name ??
                                                                'Serviço'}
                                                            :{' '}
                                                            {
                                                                balance.remaining_quantity
                                                            }{' '}
                                                            de{' '}
                                                            {
                                                                balance.allocated_quantity
                                                            }{' '}
                                                            disponíveis
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    ) : null}
                                    {pkg.usages?.length ? (
                                        <ul className="space-y-1">
                                            {pkg.usages.map((usage, index) => (
                                                <li
                                                    key={`${usage.created_at}-${index}`}
                                                >
                                                    {usage.sessions_consumed ===
                                                    1
                                                        ? '1 sessão utilizada'
                                                        : `${usage.sessions_consumed} sessões utilizadas`}
                                                    {usage.service
                                                        ? ` · ${usage.service.name}`
                                                        : ''}
                                                    {usage.user
                                                        ? ` por ${usage.user.name}`
                                                        : ''}
                                                    {' · '}
                                                    {new Date(
                                                        usage.created_at,
                                                    ).toLocaleString('pt-BR')}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p>
                                            Nenhuma sessão registrada como
                                            utilizada.
                                        </p>
                                    )}
                                </div>
                            </details>
                        </article>
                    ))}
                    <Pagination links={packages.links} />
                </div>
            )}
        </PageCanvas>
    );
}
