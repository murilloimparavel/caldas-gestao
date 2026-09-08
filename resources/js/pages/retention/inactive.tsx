import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    RefreshCw,
    ShieldAlert,
    UserRound,
} from 'lucide-react';
import {
    createIdempotencyKey,
    EmptyState,
    PageCanvas,
    ResourceHeader,
} from '@/components/operational';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import customers from '@/routes/customers';
import customerRetention from '@/routes/customers/retention';
import { inactive as retentionInactive } from '@/routes/retention';
import type { SharedPageProps } from '@/types';

type CommunicationPreference = {
    channel: 'email' | 'sms' | 'whatsapp';
    opted_in: boolean;
    revoked_at: string | null;
};

type InactiveCustomer = {
    communication_preferences: CommunicationPreference[];
    email: string | null;
    id: string;
    last_activity_at: string | null;
    name: string;
    phone: string | null;
    retention_status: 'none' | 'at_risk' | 'reactivated';
};

type Props = {
    customers: InactiveCustomer[];
    days: number;
};

const channelLabel: Record<CommunicationPreference['channel'], string> = {
    email: 'E-mail',
    sms: 'SMS',
    whatsapp: 'WhatsApp',
};

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Sem atividade registrada';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function consentSummary(preferences: CommunicationPreference[]): string {
    const optedInChannels = preferences
        .filter((preference) => preference.opted_in)
        .map((preference) => channelLabel[preference.channel]);

    if (optedInChannels.length > 0) {
        return `Consentimento: ${optedInChannels.join(', ')}`;
    }

    if (preferences.length > 0) {
        return 'Opt-out registrado';
    }

    return 'Sem consentimento registrado';
}

function retentionStatusLabel(
    status: InactiveCustomer['retention_status'],
): string {
    return {
        none: 'Pendente',
        at_risk: 'Em retenção',
        reactivated: 'Reativado',
    }[status];
}

export default function RetentionInactive({
    customers: inactiveCustomers,
    days,
}: Props) {
    const { props } = usePage<SharedPageProps>();
    const canManage = props.auth.permissions.includes('retention.manage');

    function changeWindow(value: string): void {
        const parsedDays = Number.parseInt(value, 10);

        if (
            !Number.isInteger(parsedDays) ||
            parsedDays < 1 ||
            parsedDays > 3650
        ) {
            return;
        }

        router.get(
            retentionInactive.url({ query: { days: parsedDays } }),
            {},
            { preserveScroll: true, replace: true },
        );
    }

    return (
        <PageCanvas>
            <Head title="Clientes inativos" />

            <ResourceHeader
                eyebrow="Relacionamento"
                title="Clientes inativos"
                description="Priorize contatos de reativação respeitando o consentimento de cada cliente."
                action={
                    <Button variant="outline" asChild>
                        <Link href={customers.index.url()}>
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Clientes
                        </Link>
                    </Button>
                }
            />

            <div className="space-y-6">
                <Alert>
                    <ShieldAlert className="h-4 w-4" />
                    <AlertTitle>Contato com consentimento</AlertTitle>
                    <AlertDescription>
                        Use apenas os canais consentidos. Opt-out e ausência de
                        consentimento não autorizam disparos.
                    </AlertDescription>
                </Alert>

                <div className="flex flex-wrap items-end justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div>
                        <p className="text-sm font-medium">
                            Janela de inatividade
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Mostrando clientes sem atividade há pelo menos{' '}
                            {days} dias.
                        </p>
                    </div>
                    <label className="grid gap-1 text-sm font-medium">
                        Dias
                        <Input
                            className="w-28"
                            type="number"
                            min={1}
                            max={3650}
                            defaultValue={days}
                            onBlur={(event) => changeWindow(event.target.value)}
                        />
                    </label>
                </div>

                {inactiveCustomers.length === 0 ? (
                    <EmptyState
                        icon={UserRound}
                        title="Nenhum cliente inativo nesta janela"
                        description="Ajuste o período ou mantenha o acompanhamento nas próximas visitas."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                        <div className="divide-y divide-border">
                            {inactiveCustomers.map((customer) => {
                                const isOptedIn =
                                    customer.communication_preferences.some(
                                        (preference) => preference.opted_in,
                                    );

                                return (
                                    <article
                                        key={customer.id}
                                        className="grid gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:p-6"
                                    >
                                        <div className="min-w-0 space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={customers.show.url(
                                                        customer.id,
                                                    )}
                                                    className="truncate font-semibold hover:underline"
                                                >
                                                    {customer.name}
                                                </Link>
                                                <Badge
                                                    variant={
                                                        customer.retention_status ===
                                                        'at_risk'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {retentionStatusLabel(
                                                        customer.retention_status,
                                                    )}
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        isOptedIn
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {consentSummary(
                                                        customer.communication_preferences,
                                                    )}
                                                </Badge>
                                            </div>
                                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                                {customer.email && (
                                                    <span>
                                                        {customer.email}
                                                    </span>
                                                )}
                                                {customer.phone && (
                                                    <span>
                                                        {customer.phone}
                                                    </span>
                                                )}
                                                <span>
                                                    Última atividade:{' '}
                                                    {formatDateTime(
                                                        customer.last_activity_at,
                                                    )}
                                                </span>
                                            </div>
                                        </div>

                                        {canManage && (
                                            <div className="flex flex-wrap gap-2 sm:justify-end">
                                                {customer.retention_status !==
                                                    'at_risk' && (
                                                    <Form
                                                        {...customerRetention.mark.form(
                                                            customer.id,
                                                        )}
                                                        headers={{
                                                            'X-Idempotency-Key':
                                                                createIdempotencyKey(
                                                                    'retention-mark',
                                                                    customer.id,
                                                                ),
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <>
                                                                <input
                                                                    type="hidden"
                                                                    name="days"
                                                                    value={days}
                                                                />
                                                                <Button
                                                                    type="submit"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    <AlertCircle className="mr-1.5 h-3.5 w-3.5" />
                                                                    {processing
                                                                        ? 'Marcando…'
                                                                        : 'Marcar risco'}
                                                                </Button>
                                                            </>
                                                        )}
                                                    </Form>
                                                )}
                                                {customer.retention_status !==
                                                    'reactivated' && (
                                                    <Form
                                                        {...customerRetention.reactivate.form(
                                                            customer.id,
                                                        )}
                                                        headers={{
                                                            'X-Idempotency-Key':
                                                                createIdempotencyKey(
                                                                    'retention-reactivate',
                                                                    customer.id,
                                                                ),
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                                                                {processing
                                                                    ? 'Reativando…'
                                                                    : 'Reativar'}
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                            </div>
                                        )}
                                    </article>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </PageCanvas>
    );
}
